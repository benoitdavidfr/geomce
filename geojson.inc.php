<?php
/*PhpDoc:
name:  geojson.inc.php
title: geojson.inc.php - fonctions utiles à geojson.php et à export.php - Benoit DAVID
functions:
classes:
journal: |
  28/4/2019:
  - restructuration pour utiliser gegeom et coordsys
  - copie sauvegarde dans bak
  5/3/2019:
  - utilisation de la propriété 'marker-symbol' pour styler les points
  - utilisation de symboles ponctuels différents en fonction de la table
  4/3/2019:
  - généralisation avec désagrégation
  3/3/2019:
  - restructuration du code par définition des classes Geometry, ...
*/
  
require __DIR__.'/../../coordsys/light.inc.php';
require __DIR__.'/../../gegeom/gebox.inc.php';
require __DIR__.'/../../gegeom/gegeom.inc.php';
require __DIR__.'/../../gegeom/zoom.inc.php';

// corrige l'erreur de projection des données initiales
// Les données en entrée sont 
// - soit en projection légale (Lambert93, UTM, ...)
// - soit en coord. géo. Lonlat decdeg généré à partir de coord. en proj. légales comme si elles étaient en Lambert93
// Produit des coordonnées en coord. géo. Lonlat decdeg 
function correctProjectError(Geometry $geom): Geometry {
  $pos = $geom->aPos();
  if ($pos[1] > 100) { // coord. en proj. légales
    $geomGeo = $geom->proj( function($pos) { return Lambert93::geo($pos); });
    $pos = $geomGeo->aPos();
    if ($pos[1] > 53) { // les coordonnées en entrée étaient en UTM-40S
      return $geom->proj( function($pos) { return UTM::geo('40S', $pos); });
    }
    else
      return $geomGeo;
  }
  else { // pseudo coord. géo.
    if ($pos[1] > 53) { // les coordonnées en entrée étaient initialement en UTM-40S et ont été passé en géo par Lambert93
      return $geom->proj( function($geopos) { return UTM::geo('40S', Lambert93::proj($geopos)); });
    }
    else
      return $geom;
  }
}

/*PhpDoc: functions
name: Geometry
title: "function generalize(int $zoom, Geometry $geom, array $marker_symbols): array - généralise la géométrie passée comme geometry GeoJSON en fonction du niveau de zoom"
doc: |
  Le paramètre $marker_symbols contient 2 symboles ponctuels, le premier non généralisé et le second généralisé
  Un Point n'est pas généralisé. De même si le zoom >= 14 ou -1 alors pas de généralisation.
  Dans ce cas, pour les géométries contenant des points, le marker-symbol indique non généralisé (carré)
  Si la géométrie est petite (taille < 10 pixels) alors elle est généralisée par un point avec marker-symbol corr. (circle)
  Sinon, si la géométrie est simple (moins de 100 points) alors pas de généralisation.
  Sinon elle est décomposée en agrégats, chacun étant un ens. de géométries élémentaires (Point/LineString/Polygon),
  en fonction de la proximité des éléments.
  Un élément est agrégé à un agrégat si leur distance est inférieure à 10 pixels au zoom courant
  La fonction renvoie alors la liste des agrégats correspondant à la géométrie en paramètre
  chacun généralisé soit par un rectangle englobant soit par un point avec le symbole correspondant.
  Il y a 2 seuils de zoom:
    - <= 10 (cad la carte 1/1M) généralisation systématique sauf pour les Points
    - 11, 12, 13 : généralisation des géométries grandes et simples
    - >= 14 (cad la carte 1/25K) aucune généralisation
*/
function generalize(int $zoom, Geometry $geom, array $marker_symbols): array {
  if (($geom->type() == 'Point') || ($zoom >= 14) || ($zoom == -1)) { // pas de généralisation
    if (in_array('Point', $geom->eltTypes()))
      $geom->setStyle(['marker-symbol'=>$marker_symbols[0]]);
    return [$geom];
  }
  $d10px = 10 * Zoom::pixelSize($zoom) / Zoom::sizeOfADegreeInMeters(); // distance de 10 pixels en degrés
  $bbox = $geom->bbox();
  // si la géométrie est suffisamment petite alors elle est généralisée par un point avec un style généralisé
  if ($bbox->size() < $d10px) // petite géométrie => generalisation
    return [new Point($bbox->center(), ['marker-symbol'=>$marker_symbols[1]])];
  
  if (($zoom > 10) && ($geom->nbreOfPos() < 100)) { // pas de généralisation
    if (in_array('Point', $geom->eltTypes()))
      $geom->setStyle(['marker-symbol'=>$marker_symbols[0]]);
    return [$geom];
  }

  // traitement des grandes géométries
  $verbose = false;
  if (in_array($geom->type(), ['LineString','Polygon'])) { // cas d'un grand LineString ou Polygon
    $aggs = [ $geom->bbox() ];
  }
  else {
    // fabrication initiale des agrégats
    $aggs = []; // [ bbox de l'agrégat ], chaque agrégat est représenté par son BBox
    foreach ($geom->decompose() as $elt) {
      $bboxOfElt = $elt->bbox();
      $distmin = 1000 * $d10px;
      foreach ($aggs as $idagg => $agg) {
        $dist = $agg->dist($bboxOfElt); // distance entre l'elt et l'agrégat
        if ($verbose)
          echo "dist = $dist<br>\n";
        if ($dist < $distmin) {
          $distmin = $dist;
          $idaggmin = $idagg;
        }
      }
      if ($distmin < $d10px) {
        // ajout de l'élément à l'agrégat $idaggmin
        $aggs[$idaggmin]->union($bboxOfElt);
        if ($verbose)
          echo "ajout de l'élément à l'agrégat $idaggmin dt le bbox devient ",$aggs[$idaggmin]['bbox'],"<br>\n";
      }
      else {
        // création d'un nouvel agrégat
        $bbox = $elt->bbox();
        if ($verbose)
          echo "création d'un nouvel agrégat ",count($aggs)," bbox=$bboxOfElt<br>\n";
        $aggs[] = $bboxOfElt;
      }
    }
    //echo count($aggs)," aggs à la fin de la construction initiale\n";
    // fusion d'éventuels agrégats se recouvrants
    $nbraggs = count($aggs);
    $done = false;
    while (!$done) {
      $done = true;
      for ($idagg1=0; $idagg1 < $nbraggs-1; $idagg1++) {
        if (!isset($aggs[$idagg1])) continue;
        for ($idagg2=$idagg1+1; $idagg2 < $nbraggs; $idagg2++) {
          if (!isset($aggs[$idagg2])) continue;
          if ($aggs[$idagg1]->intersects($aggs[$idagg2])) {
            // fusion des 2 aggs
            //echo "fusion de $idagg2 dans $idagg1\n";
            $aggs[$idagg1]->union($aggs[$idagg2]);
            unset($aggs[$idagg2]);
            $done = false;
            break 2;
          }
        }
      }
    }
    // traitement du cas particulier du MultiPoint généralisé par un MultiPoint identique
    if (($geom->type()=='MultiPoint') && (count($geom->coordinates())==count($aggs))) {
      $geom->setStyle(['marker-symbol'=>$marker_symbols[0]]);
      return [$geom];
    }
  }
  
  // fabrication du résultat
  $result = []; // [Geometry]
  foreach ($aggs as $agg) {
    if ($agg->size() < $d10px)
      $result[] = new Point($agg->center(), ['marker-symbol'=>$marker_symbols[1]]);
    else
      $result[] = new Polygon($agg->polygon());
  }
  return $result;
}

