<?php
/*PhpDoc:
name: geojson.php
title: geojson.php - génération GeoJson de la base GeoMCE - Benoit DAVID - 2/3/2019 13:00
functions:
classes:
doc: |
  Le chemin doit définir la table et écventuellement l'identifiant de la mesure
  accepte aussi des paramètres GET ou POST bbox et zoom
  En outre cette génération corrige l'erreur de projection de Lambert93 en coord. géo.
  Enfin, deux options complémentaires définies dans le code:
    - $nbMaxOfFeatures permet de limiter le nbre de features pour des tests
    - $dissolveCollection permet de décomposer les collections en leurs éléments
  Exemples d'appel:
    http://gexplor.fr/geomce/geojson.php/mesures_emprises?bbox=-5,45,1,49&zoom=8
    http://gexplor.fr/geomce/geojson.php/mesures_communes/1397
  Je génère pour les points un style associé à chaque GeoJSON Feature
  inspiré de https://github.com/mapbox/simplestyle-spec/tree/master/1.1.0
  J'utilise la propriété 'marker-symbol' dont la valeur doit être un symbole défini dans marker.php
  Cette propriété est ensuite utilisée dans map.php pour définir le symbole à afficher.

journal: |
  11-12/4/2019:
  - adaptation à la livraison du 11/4/2019
  5/3/2019:
  - utilisation de la propriété 'marker-symbol' pour styler les points
  - utilisation de symboles ponctuels différents en fonction de la table
  4/3/2019:
  - généralisation avec désagrégation
*/

require __DIR__.'/geojson.inc.php';

//header('Content-type: text/plain'); print_r($_SERVER);
  
function doc(array $params=[]) {
  echo "<!DOCTYPE html><html><head><meta charset='UTF-8'><title>geojson</title></head><body>\n";
  echo "<h2>Génération GeoJSON de la base MCE</h2>\n";
  if ($params)
    echo "params=",json_encode($params),"<br>\n";
  echo "<a href='http://$_SERVER[SERVER_NAME]$_SERVER[SCRIPT_NAME]/CPII/20190226/direct'>
    export CPII 20190226 direct</a><br>\n";
  echo "<a href='http://$_SERVER[SERVER_NAME]$_SERVER[SCRIPT_NAME]/mesure_commune'>
    export CPII 20190226 commune</a><br>\n";
  echo "<a href='http://$_SERVER[SERVER_NAME]$_SERVER[SCRIPT_NAME]/CPII/20190411/direct'>
    export CPII 20190411 direct</a><br>\n";
  echo "<a href='http://$_SERVER[SERVER_NAME]$_SERVER[SCRIPT_NAME]/CPII/20190411/commune'>
    export CPII 20190411 commune</a><br>\n";
  die();
}

{ // construction des paramètres
  $params = substr($_SERVER['PHP_SELF'], strlen($_SERVER['SCRIPT_NAME'])+1);
  if (!$params) {
    doc();
  }
  $params = explode('/', $params);
  if (count($params) == 3) {
    $params = ['source'=> $params[0], 'date_export'=> $params[1], 'georef'=> $params[2]];
    $tid = null;
  }
  else {
    doc($params);
  }
  //echo "table_name=$table_name\ntid=$tid\n"; die();

  //$table_name = 'mesure_emprise';
  $zoom = isset($_GET['zoom']) ? $_GET['zoom'] : (isset($_POST['zoom']) ? $_POST['zoom'] : -1);
  $nbMaxOfFeatures = 0; // 20; // si <>0 limite à ce nbre le nbre de features
  if ($reqBbox = isset($_GET['bbox']) ? $_GET['bbox'] : (isset($_POST['bbox']) ? $_POST['bbox'] : null)) {
    $reqBbox = explode(',', $reqBbox);
    $reqBbox = new BBox($reqBbox);
  }
  $dissolveCollection = false; // si true remplace les collections par leurs éléments
}

// les symboles des points non généralisés/généralisés
$marker_symbols = ($params['georef'] == 'direct') ? ['circle/0000FF', 'square/3BB9FF'] : ['diam/0000FF', 'square/3BB9FF'];


// schema de la table
function ischema(string $table_name): array {
  $query = "select table_schema, table_name, column_name, ordinal_position, data_type, udt_name
  from INFORMATION_SCHEMA.COLUMNS where table_schema='public' and table_name = 'mce'";
  $result = pg_query($query) or die('Query failed: ' . pg_last_error());

  $ischema = []; // schema de la table
  while ($tuple = pg_fetch_array($result, null, PGSQL_ASSOC)) {
    $ischema['byPos'][(int)$tuple['ordinal_position']] = [
      'column_name'=> $tuple['column_name'],
      'data_type'=> $tuple['data_type'],
      'udt_name'=> $tuple['udt_name'],
    ];
    $ischema['byName'][$tuple['column_name']] = [
      'ordinal_position'=> $tuple['ordinal_position'],
      'data_type'=> $tuple['data_type'],
      'udt_name'=> $tuple['udt_name'],
    ];
  }

  //header('Content-type: text/plain'); echo 'schema='; print_r($ischema); die();
  return $ischema;
}

$dbconn = pg_connect("host=postgresql-bdavid.alwaysdata.net dbname=bdavid_geomce user=bdavid_geomce password=geomce")
    or die('Could not connect: ' . pg_last_error());

// schema de la table
if (!($ischema = ischema('mce'))) {
  die("Table $table_name incorrecte");
}

// génération de la requête SQL
$columns = []; // liste des nom de colonnes sauf celle correspondant à la géométrie
$geomColumn = null; // nom de la colonne correspondant à la géométrie
foreach ($ischema['byPos'] as $pos => $column) {
  if ($column['udt_name']<>'geography')
    $columns[] = $column['column_name'];
  else
    $geomColumn = $column['column_name'];
}

$query = "SELECT ".implode(', ', $columns).",
ST_AsGeoJSON($geomColumn) as geometry,
ST_Area($geomColumn)/10000 as area_ha, ST_Length($geomColumn)/1000 as length_km
FROM public.mce
where source='$params[source]' and date_export='$params[date_export]' and georef='$params[georef]'";

//echo "query=$query\n";
$result = pg_query($query)
  or die('Query failed: ' . pg_last_error());

header('Access-Control-Allow-Origin: *');
header('Content-type: application/json');

echo "{ \"type\": \"FeatureCollection\",\n"
  ."\"parameters\":".json_encode([
    'source'=> $params['source'],
    'date_export'=> $params['date_export'],
    'georef'=> $params['georef'],
    'zoom'=> $zoom,
    'bbox'=> $reqBbox,
    'tid'=> $tid,
    'nbMaxOfFeatures'=> $nbMaxOfFeatures,
    'dissolveCollection'=> $dissolveCollection,
  ]).",\n"
  ."\"features\": [\n";
$featureno = 0;
while ($tuple = pg_fetch_array($result, null, PGSQL_ASSOC)) {
  //echo 'tuple='; var_dump($tuple);
  if (is_null($tuple['geometry']))
    continue;
  $geometry = Geometry::fromGeoJSON(json_decode($tuple['geometry'], true));
  if ($reqBbox && !$reqBbox->intersects($geometry->bbox()))
    continue;
  $feature = ['type'=> 'Feature', 'properties'=>[]];
  $feature['properties']['nbreOfPos'] = $geometry->nbreOfPos();
  foreach ($tuple as $name => $col_value) {
    if (in_array($name, ['area_ha','length_km'])) {
      if ($col_value)
        $feature['properties'][$name] = round($col_value, 2);
    }
    elseif ($name <> 'geometry')
      $feature['properties'][$name] = $col_value;
  }
  if ($tid) { // si j'affiche un seul n-uplet, affichage brut
    $feature['geometry'] = $geometry->asArray();
    if ($featureno++)
      echo ",\n";
    echo json_encode($feature, JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE);
    continue;
  }
  
  foreach (($dissolveCollection ? $geometry->dissolveCollection() : [$geometry]) as $geom0) {
    foreach (generalize($zoom, $geom0, $marker_symbols) as $i => $geom1) {
      $feature['properties']['agg'] = "agg_$i";
      $feature['geometry'] = $geom1->asArray();
      if ($style = $geom1->getStyle())
        $feature['style'] = $style;
      if ($featureno++)
        echo ",\n";
      echo json_encode($feature, JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE);
    }
  }
  if ($nbMaxOfFeatures && ($featureno >= $nbMaxOfFeatures)) break;
}
echo "]\n}\n";
pg_free_result($result);

pg_close($dbconn);

