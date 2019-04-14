<?php
/*PhpDoc:
name:  geojson.inc.php
title: geojson.inc.php - fonctions utiles à geojson.php et à export.php - Benoit DAVID
functions:
classes:
journal: |
  5/3/2019:
  - utilisation de la propriété 'marker-symbol' pour styler les points
  - utilisation de symboles ponctuels différents en fonction de la table
  4/3/2019:
  - généralisation avec désagrégation
  3/3/2019:
  - restructuration du code par définition des classes Geometry, ...
*/
  
require __DIR__.'/coordsys.inc.php';

/*PhpDoc: classes
name: Zoom
title: classe regroupant l'intelligence autour des niveaux de zoom
*/
class Zoom {
  static $maxZoom = 18; // zoom max utilisé notamment pour les points
  // $size0 est la circumférence de la Terre en mètres
  // correspond à 2 * PI * a où a = 6 378 137.0 est le demi-axe majeur de l'ellipsoide WGS 84
  static $size0 = 20037508.3427892476320267 * 2;
  
  // taille du pixel en mètres en fonction du zoom
  static function pixelSize(int $zoom) { return self::$size0 / 256 / pow(2, $zoom); }
  
  // niveau de zoom adapté à la visualisation d'une géométrie définie par la taille de son BBox
  static function zoomForBBoxSize(float $size): int {
    if ($size) {
      $z = log(360.0 / $size, 2);
      //echo "z=$z<br>\n";
      return min(round($z), self::$maxZoom);
    }
    else
      return self::$maxZoom;
  }
  
  // taille d'un degré en mètres
  static function sizeOfADegreeInMeters() { return self::$size0 / 360.0; }
};

if (basename(__FILE__) == basename($_SERVER['PHP_SELF'])) { // Test unitaire de la classe Zoom
  if (!isset($_GET['test']))
    echo "<a href='?test=Zoom'>Test unitaire de la classe Zoom</a><br>\n";
  elseif ($_GET['test']=='Zoom') {
    for($zoom=0; $zoom <= 21; $zoom++)
      printf("zoom=%d pixelSize=%.2f m<br>\n", $zoom, Zoom::pixelSize($zoom));
    printf("sizeOfADegree=%.3f km<br>\n", Zoom::sizeOfADegreeInMeters()/1000);
  }
}


/*PhpDoc: classes
name: BBox
title: Gestion d'une BBox en coord. géo. degrés décimaux, chaque point codé comme [lon, lat]
doc: |
  A sa création la BBox peut être indéterminée
*/
class BBox {
  private $min=null; // null ou [number, number]
  private $max=null; // null ou [number, number], null ssi $min is null
  
  // Soit ne prend pas de paramètre et créée alors une BBox indéterminée,
  // soit prend en paramètre un array de 2 ou 3 nombres interprété comme une position,
  // soit un array de 4 nombres, soit un string dont l'explode donne 4 nombres, interprétés comme 2 positions.
  function __construct($param = null) {
    $this->min = null;
    $this->max = null;
    if (is_null($param))
      return;
    elseif (is_array($param) && ((count($param)==2) || (count($param)==3)) && is_numeric($param[0]))
      $this->bound($param);
    elseif (is_array($param) && (count($param)==4) && is_numeric($param[0])) {
      $this->bound([$param[0], $param[1]]);
      $this->bound([$param[2], $param[3]]);
    }
    elseif (is_string($param) && ($params = explode(',', $param)) && (count($params)==4)) {
      $this->bound([(float)$params[0], (float)$params[1]]);
      $this->bound([(float)$params[2], (float)$params[3]]);
    }
    else
      throw new Exception("Erreur de BBox::__construct(".json_encode($param).")");
  }
  
  function __toString(): string { return is_null($this->min) ? '{}' : json_encode(['min'=>$this->min, 'max'=>$this->max]); }
  
  function undetermined(): bool { return is_null($this->min); }
  
  // retourne le centre de la BBox ou null si elle est indéterminée
  function center(): ?array {
    return is_null($this->min) ? null : [($this->min[0]+$this->max[0])/2, ($this->min[1]+$this->max[1])/2];
  }
  
  // retourne un array d'array avec les 5 positions du polygone de la BBox ou null si elle est indéterminée
  function polygon(): ?array {
    if (is_null($this->min))
      return null;
    else
      return [[
        [$this->min[0], $this->min[1]],
        [$this->max[0], $this->min[1]],
        [$this->max[0], $this->max[1]],
        [$this->min[0], $this->max[1]],
        [$this->min[0], $this->min[1]],
      ]];
  }
  
  // taille max en degrés de longueur constante (Zoom::$size0 / 360) ou null si la BBox est indéterminée
  function size(): ?float {
    if (is_null($this->min))
      return null;
    $cos = cos(($this->max[1] + $this->min[1])/2 / 180 * pi()); // cosinus de la latitude moyenne
    $dLon = ($this->max[0] - $this->min[0]) * $cos; // taille en longitude
    $dLat = ($this->max[1] - $this->min[1]); // taille en latitude
    return max($dLon, $dLat);
  }
  
  // intègre une position à la BBox, renvoie la BBox modifiée
  function bound(array $pos): BBox {
    if (!is_numeric($pos[0]) || !is_numeric($pos[1]))
      throw new Exception("Erreur dans bound sur ".json_encode($pos));
    if ($this->undetermined()) {
      $this->min = $pos;
      $this->max = $pos;
    } else {
      $this->min = [ min($this->min[0], $pos[0]), min($this->min[1], $pos[1])];
      $this->max = [ max($this->max[0], $pos[0]), max($this->max[1], $pos[1])];
    }
    return $this;
  }
  
  // construit la BBox englobant la liste de positions
  static function bboxOfLPos(array $lpos): BBox {
    $bbox = new BBox;
    foreach ($lpos as $pos)
      $bbox->bound($pos);
    return $bbox;
  }
  
  // construit la BBox englobant la liste de listes de positions
  static function bboxOfLLPos(array $llpos): BBox {
    $bbox = new BBox;
    foreach ($llpos as $lpos)
      foreach ($lpos as $pos)
        $bbox->bound($pos);
    return $bbox;
  }
  
  // modifie $this pour qu'il soit l'union de $this et de $b2, renvoie $this
  // la BBox indéterminée est un élément neutre pour l'union
  function unionVerbose(BBox $b2): BBox {
    $u = $this->union($b2);
    echo "BBox::union(b2=$b2)@$this -> $u<br>\n";
    return $u;
  }
  function union(BBox $b2): BBox {
    if ($b2->undetermined())
      return $this;
    elseif ($this->undetermined()) {
      $this->min = $b2->min;
      $this->max = $b2->max;
      return $this;
    }
    else {
      $this->min[0] = min($this->min[0], $b2->min[0]);
      $this->min[1] = min($this->min[1], $b2->min[1]);
      $this->max[0] = max($this->max[0], $b2->max[0]);
      $this->max[1] = max($this->max[1], $b2->max[1]);
      return $this;
    }
  }
  
  // test d'intersection de 2 bbox, génère une erreur si une des 2 BBox est idéterminée
  function intersectsVerbose(BBox $b2): bool {
    $i = $this->intersects($b2);
    echo "BBox::intersects(b2=$b2)@$this -> ",$i ? 'true' : 'false',"<br>\n";
    return $i;
  }
  function intersects(BBox $b2): bool {
    if ($this->undetermined() || $b2->undetermined())
      throw new Exception("Erreur intersection avec une des BBox indéterminée");
    $xmin = max($b2->min[0],$this->min[0]);
    $ymin = max($b2->min[1],$this->min[1]);
    $xmax = min($b2->max[0],$this->max[0]);
    $ymax = min($b2->max[1],$this->max[1]);
    return (($xmax >= $xmin) && ($ymax >= $ymin));
  }
  // Test unitaire de la méthode intersects
  static function intersectsTest() {
    // cas d'intersection d'un point avec un rectangle
    $b1 = new BBox([5.597887,43.343763]);
    $b2 = new BBox([5.5952972173690805,43.34244361213331]);
    $b2->bound([5.600473880767823,43.345084769230894]);
    $b1->intersectsVerbose($b2);
  }

  // distance entre 2 BBox, génère une erreur si une des 2 BBox est indéterminée
  function distVerbose(BBox $b2): float {
    $d = $this->dist($b2);
    echo "BBox::dist(b2=$b2)@$this -> ",$d,"<br>\n";
    return $d;
  }
  function dist(BBox $b2): float {
    if ($this->undetermined() || $b2->undetermined())
      throw new Exception("Erreur de distance avec une des BBox indéterminée");
    $xmin = max($b2->min[0],$this->min[0]);
    $ymin = max($b2->min[1],$this->min[1]);
    $xmax = min($b2->max[0],$this->max[0]);
    $ymax = min($b2->max[1],$this->max[1]);
    if (($xmax >= $xmin) && ($ymax >= $ymin))
      return 0;
    else {
      $cos = cos(($this->max[1] + $this->min[1])/2 / 180 * pi()); // cosinus de la latitude moyenne
      return max(($xmin-$xmax),0)*$cos + max(($ymin-$ymax), 0);
    }
  }
  static function distTest() {
    $b1 = new BBox([0,0,2,2]);
    $b2 = new BBox([1,1,3,3]);
    $b1->distVerbose($b2);
  }
};

if (basename(__FILE__) == basename($_SERVER['PHP_SELF'])) { // Test unitaire de la classe BBox
  if (!isset($_GET['test']))
    echo "<a href='?test=BBox'>Test unitaire de la classe BBox</a><br>\n";
  elseif ($_GET['test']=='BBox') {
    echo "Test de BBox::intersects<br>\n";
    BBox::intersectsTest();
    BBox::distTest();
  }
}

/*PhpDoc: classes
name: LElts
title: Fonctions de gestion de liste d'éléments
*/
class LElts {
  // Nbre d'élts d'une liste de listes d'élts
  static function LLcount(array $llelts) {
    $nbElts = 0;
    foreach ($llelts as $lelts)
      $nbElts += count($lelts);
    return $nbElts;
  }
  // Nbre d'élts d'une liste de listes de listes d'élts
  static function LLLcount(array $lllelts) {
    $nbElts = 0;
    foreach ($lllelts as $llelts)
      $nbElts += self::LLcount($llelts);
    return $nbElts;
  }
}

/*PhpDoc: classes
name: Geometry
title: abstract class Geometry - Gestion d'une Geometry GeoJSON et de quelques opérations
doc: |
  Les coordonnées sont conservées en array comme en GeoJSON et pas structurées avec des objets.
  Chaque type de géométrie correspond à une sous-classe non abstraite.
  Un style peut être associé à une Geometry. Il s'inspire de https://github.com/mapbox/simplestyle-spec/tree/master/1.1.0
*/
abstract class Geometry {
  const HOMOGENEOUSTYPES = ['Point','LineString','Polygon','MultiPoint','MultiLineString','MultiPolygon'];
  static $precision = 6; // nbre de chiffres après la virgule à conserver pour les géométries GeoJSON
  protected $coords; // coordonnées ou Positions, stockées comme array, array(array), ... en fonction de la sous-classe
  protected $style; // un style peut être associé à une géométrie, toujours un array, par défaut []
  
  // crée une géométrie à partir du json_decode() du GeoJSON
  static function fromGeoJSON(array $geom, array $style=[]): Geometry {
    if (isset($geom['type']) && in_array($geom['type'], self::HOMOGENEOUSTYPES) && isset($geom['coordinates']))
      return new $geom['type']($geom['coordinates']);
    elseif (isset($geom['type']) && ($geom['type']=='GeometryCollection') && isset($geom['geometries'])) {
      $geoms = [];
      foreach ($geom['geometries'] as $g)
        $geoms[] = self::fromGeoJSON($g);
      return new GeometryCollection($geoms);
    }
    else
      throw new Exception("Erreur de Geometry::fromGeoJSON(".json_encode($geom).")");
  }
  
  // fonction d'initialisation valable pour toutes les géométries homogènes
  function __construct(array $coords, array $style=[]) { $this->coords = $coords; $this->style = $style; }
  
  // récupère le type
  function type(): string { return get_class($this); }
  // retourne la liste des types élémentaires ('Point','LineString','Polygon') contenus dans la géométrie
  abstract function eltTypes(): array;
  // récupère les coordonnées
  function coordinates() { return $this->coords; }
  
  // définit le style associé et le récupère
  function setStyle(array $style=[]): void { $this->style = $style; }
  function getStyle(): array { return $this->style; }
  
  // génère la réprésentation string GeoJSON
  function __toString(): string { return json_encode($this->asArray()); }
  
  // génère la représentation Php du GoJSON
  function asArray(): array { return ['type'=>get_class($this), 'coordinates'=> $this->coords]; }
  
  // renvoie le centre d'une géométrie
  abstract function center(): array;
    
  // calcule le centre d'une liste de positions, génère une erreur si la liste est vide
  static function centerOfLPos(array $lpos): array {
    //echo "centerOfLPos"; print_r($lpos); echo "<br>\n";
    if (!$lpos)
      throw new Exception("Erreur: Geometry::centerOfLPos() d'une liste de positions vide");
    $c = [0, 0];
    $nbre = 0;
    foreach ($lpos as $pos) {
      $c[0] += $pos[0];
      $c[1] += $pos[1];
      $nbre++;
    }
    // Il semble qu'il y ait un bug Php sur Alwaysdata
    //$c = [round($c[0]/$nbre, self::$precision), round($c[1]/$nbre, self::$precision)];
    //echo "center="; print_r($c); echo "<br>\n";
    $c[0] /= $nbre;
    $c[1] /= $nbre;
    //echo "center="; print_r($c); echo "<br>\n";
    return $c;
  }
  
  abstract function nbreOfPos(): int;
  
  // retourne un point de la géométrie
  abstract function aPos(): array;
  
  // renvoie la BBox de la géométrie
  abstract function bbox(): BBox;
  
  // reprojète ue géométrie, prend en paramètre une fonction de reprojection d'une position, retourne un objet géométrie
  abstract function reproject(callable $reprojPos): Geometry;

  // reprojète une liste de positions et en retourne la liste
  static function reprojLPos(callable $reprojPos, array $lpos): array {
    $coords = [];
    foreach ($lpos as $pos)
      $coords[] = $reprojPos($pos);
    return $coords;
  }

  // reprojète une liste de liste de positions et en retourne la liste
  static function reprojLLPos(callable $reprojPos, array $llpos): array {
    $coords = [];
    foreach ($llpos as $i => $lpos)
      $coords[] = Geometry::reprojLPos($reprojPos, $lpos);
    return $coords;
  }
  
  // arrondie les coordonnées en fonction du nbre de chiffres défini par $precision
  function round(int $precision) {
    return $this->reproject( function($pos) use ($precision) {
      return [round($pos[0], $precision), round($pos[1], $precision)];
    });
  }
    
  function dissolveCollection(): array { return [$this]; }
  
  // Décompose une géométrie en un array de géométries élémentaires (Point/LineString/Polygon)
  function decompose(): array {
    $transfos = ['MultiPoint'=>'Point', 'MultiLineString'=>'LineString', 'MultiPolygon'=>'Polygon'];
    if (isset($transfos[$this->type()])) {
      $elts = [];
      foreach ($this->coords as $eltcoords)
        $elts[] = new $transfos[$this->type()]($eltcoords);
      return $elts;
    }
    else // $this est un élément
      return [$this];
  }
  
  /* agrège un ensemble de géométries élémentaires en une unique Geometry
  static function aggregate(array $elts): Geometry {
    $bbox = new BBox;
    foreach ($elts as $elt)
      $bbox->union($elt->bbox());
    return new Polygon($bbox->polygon()); // temporaireemnt représente chaque agrégat par son BBox
    $elts = array_merge([new Polygon($bbox->polygon())], $elts);
    if (count($elts) == 1)
      return $elts[0];
    $agg = [];
    foreach ($elts as $elt)
      $agg[$elt->type()][] = $elt;
    if (isset($agg['Point']) && !isset($agg['LineString']) && !isset($agg['Polygon']))
      return MultiPoint::haggregate($agg['Point']);
    elseif (!isset($agg['Point']) && isset($agg['LineString']) && !isset($agg['Polygon']))
      return MultiLineString::haggregate($agg['LineString']);
    elseif (!isset($agg['Point']) && !isset($agg['LineString']) && isset($agg['Polygon']))
      return MultiPolygon::haggregate($agg['Polygon']);
    else 
      return new GeometryCollection(array_merge(
        MultiPoint::haggregate($agg['Point']),
        MultiLineString::haggregate($agg['LineString']),
        MultiPolygon::haggregate($agg['Polygon'])
      ));
  }
  */
}

if (basename(__FILE__) == basename($_SERVER['PHP_SELF'])) { // Test unitaire de la classe Geometry
  if (!isset($_GET['test']))
    echo "<a href='?test=Geometry'>Test unitaire de la classe Geometry</a><br>\n";
  elseif ($_GET['test']=='Geometry') {
    $pt = Geometry::fromGeoJSON(['type'=>'Point', 'coordinates'=> [0,0]]);
    echo "pt=$pt<br>\n";
    echo "reproject(pt)=",$pt->reproject(function(array $pos) { return $pos; }),"<br>\n";
    $ls = Geometry::fromGeoJSON(['type'=>'LineString', 'coordinates'=> [[0,0],[1,1]]]);
    echo "ls=$ls<br>\n";
    echo "ls->center()=",json_encode($ls->center()),"<br>\n";
    $mls = Geometry::fromGeoJSON(['type'=>'MultiLineString', 'coordinates'=> [[[0,0],[1,1]]]]);
    echo "mls=$mls<br>\n";
    echo "mls->center()=",json_encode($mls->center()),"<br>\n";
    $pol = Geometry::fromGeoJSON(['type'=>'Polygon', 'coordinates'=> [[[0,0],[1,0],[1,1],[0,0]]]]);
    echo "pol=$pol<br>\n";
    echo "pol->center()=",json_encode($pol->center()),"<br>\n";
    $mpol = Geometry::fromGeoJSON(['type'=>'MultiPolygon', 'coordinates'=> [[[[0,0],[1,0],[1,1],[0,0]]]]]);
    echo "mpol=$mpol<br>\n";
    echo "mpol->center()=",json_encode($mpol->center()),"<br>\n";
    $gc = Geometry::fromGeoJSON(['type'=>'GeometryCollection', 'geometries'=> [$ls->asArray(), $mls->asArray(), $mpol->asArray()]]);
    echo "gc=$gc<br>\n";
    echo "gc->center()=",json_encode($gc->center()),"<br>\n";
    echo "gc->reproject()=",$gc->reproject(function(array $pos) { return $pos; }),"<br>\n";
    
    echo "<b>Test de decompose</b><br>\n";
    foreach ([
      [ 'type'=>'MultiPoint', 'coordinates'=>[[0,0], [1,1]]],
      [ 'type'=>'GeometryCollection',
        'geometries'=> [
          ['type'=>'MultiPoint', 'coordinates'=>[[0,0], [1,1]]],
          ['type'=>'LineString', 'coordinates'=>[[0,0], [1,1]]],
        ],
      ]
    ] as $geom) {
      echo json_encode($geom),' -> [',implode(',',Geometry::fromGeoJSON($geom)->decompose()),"]<br>\n";
    }
  }
}

class Point extends Geometry {
  function eltTypes(): array { return ['Point']; }
  // $coords contient une liste de 2 ou 3 nombres
  function center(): array { return $this->coords; }
  function nbreOfPos(): int { return 1; }
  function aPos(): array { return $this->coords; }
  function bbox(): BBox { return new BBox($this->coords); }
  function reproject(callable $reprojPos): Geometry { return new Point($reprojPos($this->coords)); }
}

// Une liste de points, peut-être vide
class MultiPoint extends Geometry {
  // $coords contient une liste de listes de 2 ou 3 nombres
  function eltTypes(): array { return $this->coords ? ['Point'] : []; }
  function center(): array { return Geometry::centerOfLPos($this->coords); }
  function nbreOfPos(): int { return count($this->coords); }
  function aPos(): array {
    if (!$this->coords)
      throw new Exception("Erreur: MultiPoint::aPos() sur une liste de positions vide");
    return $this->coords[0];
  }
  function bbox(): BBox { return BBox::bboxOfLPos($this->coords); }
  function reproject(callable $reprojPos): Geometry { return new self(Geometry::reprojLPos($reprojPos, $this->coords)); }
  /*static function haggregate(array $elts) - NON UTILISE {
    $coords = [];
    foreach ($elts as $elt)
      $coords[] = $elt->coords;
    return new MultiPoint($coords);
  }*/
}

if (basename(__FILE__) == basename($_SERVER['PHP_SELF'])) { // Test unitaire de la classe MultiPoint
  if (!isset($_GET['test']))
    echo "<a href='?test=MultiPoint'>Test unitaire de la classe MultiPoint</a><br>\n";
  elseif ($_GET['test']=='MultiPoint') {
    $mpt = Geometry::fromGeoJSON(['type'=>'MultiPoint', 'coordinates'=>[]]);
    $mpt = Geometry::fromGeoJSON(['type'=>'MultiPoint', 'coordinates'=>[[0,0],[1,1]]]);
    echo "$mpt ->center() = ",json_encode($mpt->center()),"<br>\n";
    echo "$mpt ->aPos() = ",json_encode($mpt->aPos()),"<br>\n";
    echo "$mpt ->bbox() = ",$mpt->bbox(),"<br>\n";
    echo "$mpt ->reproject() = ",$mpt->reproject(function(array $pos) { return $pos; }),"<br>\n";
  }
}

// contient au moins 2 positions
class LineString extends Geometry {
  function eltTypes(): array { return ['LineString']; }
  // $coords contient une liste de listes de 2 ou 3 nombres
  function center(): array { return Geometry::centerOfLPos($this->coords); }
  function nbreOfPos(): int { return count($this->coords); }
  function aPos(): array { return $this->coords[0]; }
  function bbox(): BBox { return BBox::bboxOfLPos($this->coords); }
  function reproject(callable $reprojPos): Geometry { return new self(Geometry::reprojLPos($reprojPos, $this->coords)); }
}

// contient une liste de liste de positions, chaque liste de positions en contient au moins 2
class MultiLineString extends Geometry {
  // $coords contient une liste de listes de listes de 2 ou 3 nombres
  function eltTypes(): array { return $this->coords ? ['LineString'] : []; }
  
  function center(): array {
    if (!$this->coords)
      throw new Exception("Erreur: MultiLineString::center() sur une liste vide");
    $c = [0, 0];
    $nbre = 0;
    foreach ($this->coords as $lpos) {
      foreach ($lpos as $pos) {
        $c[0] += $pos[0];
        $c[1] += $pos[1];
        $nbre++;
      }
    }
    return [round($c[0]/$nbre, self::$precision), round($c[1]/$nbre, self::$precision)];
  }
  
  function nbreOfPos(): int { return LElts::LLcount($this->coords); }
  
  function aPos(): array {
    if (!$this->coords)
      throw new Exception("Erreur: MultiLineString::aPos() sur une liste vide");
    return $this->coords[0][0];
  }
  function bbox(): BBox { return BBox::bboxOfLLPos($this->coords); }
  function reproject(callable $reprojPos): Geometry { return new self(Geometry::reprojLLPos($reprojPos, $this->coords)); }
  /*static function haggregate(array $elts) - NON UTILISE {
    $coords = [];
    foreach ($elts as $elt)
      $coords[] = $elt->coords;
    return new MultiLineString($coords);
  }*/
}

// Contient un extérieur qui contient au moins 4 points
// Chaque intérieur contient au moins 4 points et est contenu dans l'extérieur
// Les intérieurs ne s'intersectent pas 2 à 2
class Polygon extends Geometry {
  function eltTypes(): array { return ['Polygon']; }
  // $coords contient une liste de listes de listes de 2 ou 3 nombres
  function center(): array { return Geometry::centerOfLPos($this->coords[0]); }
  function nbreOfPos(): int { return LElts::LLcount($this->coords); }
  function aPos(): array { return $this->coords[0][0]; }
  function bbox(): BBox { return BBox::bboxOfLLPos($this->coords); }
  function reproject(callable $reprojPos): Geometry { return new self(Geometry::reprojLLPos($reprojPos, $this->coords)); }
}

// Chaque polygone respecte les contraintes du Polygon
class MultiPolygon extends Geometry {
  // $coords contient une liste de listes de listes de listes de 2 ou 3 nombres
  function eltTypes(): array { return $this->coords ? ['Polygon'] : []; }
  
  function center(): array {
    if (!$this->coords)
      throw new Exception("Erreur: MultiPolygon::center() sur une liste vide");
    $c = [0, 0];
    $nbre = 0;
    foreach ($this->coords as $polygon) {
      foreach ($polygon[0] as $pt) {
        $c[0] += $pt[0];
        $c[1] += $pt[1];
        $nbre++;
      }
    }
    return [round($c[0]/$nbre, self::$precision), round($c[1]/$nbre, self::$precision)];    
  }
  
  function nbreOfPos(): int { return LElts::LLLcount($this->coords); }
  
  function aPos(): array {
    if (!$this->coords)
      throw new Exception("Erreur: MultiPolygon::aPos() sur une liste vide");
    return $this->coords[0][0][0];
  }
  
  function bbox(): BBox {
    $bbox = new BBox;
    foreach ($this->coords as $llpos)
      $bbox->union(BBox::bboxOfLLPos($llpos));
    return $bbox;
  }
  
  function reproject(callable $reprojPos): Geometry {
    $coords = [];
    foreach ($this->coords as $llpos)
      $coords[] = Geometry::reprojLLPos($reprojPos, $llpos);
    $geom = new self($coords);
    return $geom;
  }
  
  /*static function haggregate(array $elts) - NON UTILISE {
    $coords = [];
    foreach ($elts as $elt)
      $coords[] = $elt->coords;
    return new MultiPolygon($coords);
  }*/
}

class GeometryCollection extends Geometry {
  private $geometries; // list of Geometry objects
  
  // prend en paramètre une liste d'objets Geometry
  function __construct(array $geometries, array $style=[]) { $this->geometries = $geometries; $this->style = $style; }
  
  // traduit les géométries en array Php
  function geoms(): array {
    $geoms = [];
    foreach ($this->geometries as $geom)
      $geoms[] = $geom->asArray();
    return $geoms;
  }
  
  function asArray(): array { return ['type'=>'GeometryCollection', 'geometries'=> $this->geoms()]; }
  
  // retourne la liste des types élémentaires ('Point','LineString','Polygon') contenus dans la géométrie
  function eltTypes(): array {
    $allEltTypes = [];
    foreach ($this->geometries as $geom)
      if ($eltTypes = $geom->eltTypes())
        $allEltTypes[$eltTypes[0]] = 1;
    return array_keys($allEltTypes);
  }

  function center(): array {
    if (!$this->geometries)
      throw new Exception("Erreur: GeometryCollection::center() sur une liste vide");
    $c = [0, 0];
    $nbre = 0;
    foreach ($this->geometries as $g) {
      $pt = $g->center();
      $c[0] += $pt[0];
      $c[1] += $pt[1];
      $nbre++;
    }
    return [round($c[0]/$nbre, self::$precision), round($c[1]/$nbre, self::$precision)];    
  }
  
  function nbreOfPos(): int {
    $nbreOfPos = 0;
    foreach ($this->geometries as $g)
      $nbreOfPos += $g->nbreOfPos();
    return $nbreOfPos;
  }
  
  function aPos(): array {
    if (!$this->geometries)
      throw new Exception("Erreur: GeometryCollection::aPos() sur une liste vide");
    return $this->geometries[0]->aPos();
  }
  
  function bbox(): BBox {
    $bbox = new BBox;
    foreach ($this->geometries as $geom)
      $bbox->union($geom->bbox());
    return $bbox;
  }
  
  function reproject(callable $reprojPos): Geometry {
    $geoms = [];
    foreach ($this->geometries as $geom)
      $geoms[] = $geom->reproject($reprojPos);
    return new GeometryCollection($geoms);
  }
  
  function dissolveCollection(): array { return $this->geometries; }
  
  // Décompose une géométrie en un array de géométries élémentaires (Point/LineString/Polygon)
  function decompose(): array {
    $elts = [];
    foreach ($this->geometries as $g)
      $elts = array_merge($elts, $g->decompose());
    return $elts;
  }
}

if (basename(__FILE__) == basename($_SERVER['PHP_SELF'])) { // Test unitaire de la classe GeometryCollection
  if (!isset($_GET['test']))
    echo "<a href='?test=GeometryCollection'>Test unitaire de la classe GeometryCollection</a><br>\n";
  elseif ($_GET['test']=='GeometryCollection') {
    $gc = Geometry::fromGeoJSON(['type'=>'GeometryCollection', 'geometries'=> []]);
    echo "gc=$gc<br>\n";
    //echo "gc->center()=",json_encode($gc->center()),"<br>\n";
    echo "gc->reproject()=",$gc->reproject(function(array $pos) { return $pos; }),"<br>\n";
  }
}


// corrige l'erreur de projection des données initiales
// Les données en entrée sont 
// - soit en projection légale (Lambert93, UTM, ...)
// - soit en coord. géo. Lonlat decdeg généré à partir de coord. en proj. légales comme si elles étaient en Lambert93
// Produit des coordonnées en coord. géo. Lonlat decdeg 
function correctProjectError(Geometry $geom): Geometry {
  $pos = $geom->aPos();
  if ($pos[1] > 100) { // coord. en proj. légales
    $geomGeo = $geom->reproject( function($pos) { return Lambert93::geo($pos); });
    $pos = $geomGeo->aPos();
    if ($pos[1] > 53) { // les coordonnées en entrée étaient en UTM-40S
      return $geom->reproject( function($pos) { return UTM::geo('40S', $pos); });
    }
    else
      return $geomGeo;
  }
  else { // pseudo coord. géo.
    if ($pos[1] > 53) { // les coordonnées en entrée étaient initialement en UTM-40S et ont été passé en géo par Lambert93
      return $geom->reproject( function($geopos) { return UTM::geo('40S', Lambert93::proj($geopos)); });
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

