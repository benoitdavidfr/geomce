<?php
/*PhpDoc:
name: html.php
title: html.php - affichage des tables GéoMCE sous la forme d'une table HTML - Benoit DAVID
functions:
classes:
doc: |
  Le chemin doit définir la table et éventuellement l'identifiant de la mesure.
  L'affichage d'une seule mesure permet d'appeler une carte de la mesure.
  Exemples d'appel:
    http://gexplor.fr/geomce/html.php/mesure_emprise
    http://gexplor.fr/geomce/html.php/mesure_commune/xxxxx
journal: |
  14/3/2019:
  - nvlle version multi livraisons
  4/3/2019:
  - version finalisée
*/
require __DIR__.'/geojson.inc.php';

function doc(array $params=[]) {
  require __DIR__.'/deliveries.inc.php';
  echo "<!DOCTYPE html><html><head><meta charset='UTF-8'><title>export</title></head><body>\n";
  echo "<h2>Affichage HTML de la base GéoMCE</h2>\n";
  echo "Affichage des tables GéoMCE sous la forme d'une table HTML<br>\n";
  echo "Le chemin doit définir la table et éventuellement l'identifiant de la mesure.<br>\n";
  echo "L'affichage d'une seule mesure permet d'appeler une carte de la mesure.<br>\n";
  if ($params)
    echo "paramètres d'appel: ",json_encode($params),"<br>\n";
  echo "<ul>\n";
  foreach ($deliveries as $source => $dates) {
    foreach ($dates as $date) {
      echo "<li>$source $date ",
        "<a href='http://$_SERVER[SERVER_NAME]$_SERVER[SCRIPT_NAME]/mce$source${date}direct'>direct</a>\n",
        "<a href='http://$_SERVER[SERVER_NAME]$_SERVER[SCRIPT_NAME]/mce$source${date}commune'>commune</a></li>\n";
    }
  }
  die("</ul>\n\n");
}

{ // construction des paramètres
  $params = substr($_SERVER['PHP_SELF'], strlen($_SERVER['SCRIPT_NAME'])+1);
  if (!$params) {
    doc();
  }
  $params = explode('/', $params);
  if (count($params) == 2) {
    $table_name = $params[0];
    $mid = $params[1];
  }
  elseif (count($params) == 1) {
    $table_name = $params[0];
    $mid = null;
  }
  else {
    doc($params);
  }
}

// schema de la table
function ischema(string $table_name): array {
  $query = "select table_schema, table_name, column_name, ordinal_position, data_type, udt_name
  from INFORMATION_SCHEMA.COLUMNS where table_schema='public' and table_name = '$table_name'";
  //echo "$query<br>\n";
  $result = pg_query($query) or die('Query failed: ' . pg_last_error());

  $ischema = []; // schema de la table
  while ($tuple = pg_fetch_array($result, null, PGSQL_ASSOC)) {
    //print_r($tuple);
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

$connection_string = require __DIR__.'/pgconn.inc.php';
$dbconn = pg_connect($connection_string)
    or die('Could not connect: ' . pg_last_error());

// schema de la table
if (!($ischema = ischema($table_name))) {
  die("Table $table_name incorrecte");
}

// génération de la requête SQL
$columns = []; // liste des nom de colonnes sauf celle correspondant à la géométrie
$geomColumn = null; // nom de la colonne correspondant à la géométrie
foreach ($ischema['byPos'] as $pos => $column) {
  if ($column['udt_name']=='geography')
    $geomColumn = $column['column_name'];
  else
    $columns[] = $column['column_name'];
}

$query = "SELECT ".implode(', ', $columns).",
ST_AsGeoJSON($geomColumn, ".Geometry::$precision.") as geometry,
ST_Area($geomColumn)/10000 as area_ha, ST_Length($geomColumn)/1000 as length_km
FROM public.$table_name "
.($mid ? "where num=$mid" : '');
echo "<pre>query=$query</pre>\n";

//echo "query=$query\n";
$result = pg_query($query)
  or die('Query failed: ' . pg_last_error());
  
if (!$mid) {
  $columns = array_diff($columns, ['num','si_metier','numero_dossier','geometry']);
  $sum = ['count'=> 0, 'area_ha'=> 0, 'length_km'=> 0];
  echo "<table border=1>\n";
  echo '<th>',implode('</th><th>', $columns),"</th><th>surf(ha)</th><th>long(km)</th>\n";
  while ($tuple = pg_fetch_array($result, null, PGSQL_ASSOC)) {
    echo "<tr>\n";
    foreach ($tuple as $name => $col_value) {
      if (in_array($name, ['si_metier','numero_dossier','geometry']))
        continue;
      if ($name == 'projet') {
        $href = "http://$_SERVER[SERVER_NAME]$_SERVER[SCRIPT_NAME]/$table_name/$tuple[num]";
        echo "<td><a href='$href'>$col_value</a></td>\n";
        $id = '';
      }
      elseif (in_array($name, ['area_ha','length_km'])) {
        if (!$col_value)
          echo '<td></td>';
        else
          printf('<td>%.2f</td>', $col_value);
        $sum[$name] += $col_value;
      }
      elseif ($name <> 'num')
        echo "<td>$col_value</td>\n";
    }
    $sum['count']++;
    echo "<tr>\n";
  }
  echo "</table>\n";
  printf("Nombre de mesures: %d<br>\n", $sum['count']);
  printf("Somme des surfaces: %.0f ha<br>\n", $sum['area_ha']);
  printf("Somme des longueurs: %.0f km<br>\n", $sum['length_km']);
}
else {
  $geometry = null;
  while ($tuple = pg_fetch_array($result, null, PGSQL_ASSOC)) {
    echo "<table border=1>\n";
    foreach ($tuple as $name => $col_value) {
      if (in_array($name, ['area_ha','length_km'])) {
        if ($col_value)
          printf("<tr><td><b>%s</b></td><td>%.2f</td></tr>\n", $name, $col_value);
      }
      else
        echo "<tr><td><b>$name</b></td><td>$col_value</td></tr>\n";
    }
    echo "</table>\n";
    $geometry = $tuple['geometry'];
  }
  if (!$geometry) {
    die("Aucun enregistrement correspondant à $mid");
  }
  $geometry = Geometry::fromGeoJSON(json_decode($geometry, true));
  //echo "geometry=$geometry<br>\n";
  $center = $geometry->center();
  //echo "center="; print_r($center); echo "<br>\n";
  $zoom = Zoom::zoomForGBoxSize($geometry->bbox()->size());
  $href = "http://$_SERVER[HTTP_HOST]".dirname($_SERVER['SCRIPT_NAME'])."/map.php?"
        ."table=$table_name&amp;mid=$mid&amp;lon=$center[0]&amp;lat=$center[1]&amp;zoom=$zoom";
  echo "<a href='$href'>Carte de la mesure</a><br>\n";
}