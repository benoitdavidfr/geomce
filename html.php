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
    http://gexplor.fr/geomce/export.php/mesure_emprise
    http://gexplor.fr/geomce/export.php/mesure_commune/8a94834dcc5fd3cfe341e4222cb44ede
journal: |
  4/3/2019:
  - version finalisée
*/

if ($_SERVER['SERVER_NAME']=='localhost')
  require __DIR__.'/localtest.inc.php'; // permet de tester les scripts en local
require __DIR__.'/geojson.inc.php';

function doc(array $params=[]) {
  echo "<!DOCTYPE html><html><head><meta charset='UTF-8'><title>export</title></head><body>\n";
  echo "<h2>Affichage HTML de la base GéoMCE</h2>\n";
  echo "Affichage des tables GéoMCE sous la forme d'une table HTML<br>\n";
  echo "Le chemin doit définir la table et éventuellement l'identifiant de la mesure.<br>\n";
  echo "L'affichage d'une seule mesure permet d'appeler une carte de la mesure.<br>\n";
  if ($params)
    echo "paramètres d'appel: ",json_encode($params),"<br>\n";
  echo "<a href='http://$_SERVER[SERVER_NAME]$_SERVER[SCRIPT_NAME]/mesure_emprise'>table mesure_emprise</a><br>\n";
  echo "<a href='http://$_SERVER[SERVER_NAME]$_SERVER[SCRIPT_NAME]/mesure_commune'>table mesure_commune</a><br>\n";
  die();
}

{ // construction des paramètres
  $params = substr($_SERVER['PHP_SELF'], strlen($_SERVER['SCRIPT_NAME'])+1);
  if (!$params) {
    doc();
  }
  $params = explode('/', $params);
  if (count($params) == 2) {
    $table_name = $params[0];
    $tid = $params[1];
  }
  elseif (count($params) == 1) {
    $table_name = $params[0];
    $tid = null;
  }
  else {
    doc($params);
  }
}

// schema de la table
function ischema(string $table_name): array {
  $query = "select table_schema, table_name, column_name, ordinal_position, data_type, udt_name
  from INFORMATION_SCHEMA.COLUMNS where table_schema='public' and table_name = '$table_name'";
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
if (!($ischema = ischema($table_name))) {
  die("Table $table_name incorrecte");
}

// génération de la requête SQL
$columns = []; // liste des nom de colonnes sauf celle correspondant à la géométrie
$geomColumn = null; // nom de la colonne correspondant à la géométrie
foreach ($ischema['byPos'] as $pos => $column) {
  if ($column['udt_name']=='geometry')
    $geomColumn = $column['column_name'];
  else
    $columns[] = $column['column_name'];
}

$query = "SELECT ".implode(', ', $columns).",
ST_AsGeoJSON($geomColumn, ".Geometry::$precision.") as geometry,
ST_Area(ST_Transform($geomColumn, 2154))/10000 as area_ha,
ST_Length(ST_Transform($geomColumn, 2154))/1000 as length_km
FROM public.$table_name";

//echo "query=$query\n";
$result = pg_query($query)
  or die('Query failed: ' . pg_last_error());
  
if (!$tid) {
  $columns = array_diff($columns, ['si_metier','numero_dossier','geometry']);
  $sum = ['area_ha'=> 0, 'length_km'=> 0];
  echo "<table border=1>\n";
  echo '<th>',implode('</th><th>', $columns),"</th><th>surf(ha)</th><th>long(km)</th>\n";
  while ($tuple = pg_fetch_array($result, null, PGSQL_ASSOC)) {
    $id = md5(json_encode($tuple));
    echo "<tr>\n";
    foreach ($tuple as $name => $col_value) {
      if (in_array($name, ['si_metier','numero_dossier','geometry']))
        continue;
      if ($id) {
        $href = "http://$_SERVER[SERVER_NAME]$_SERVER[SCRIPT_NAME]/$table_name/$id";
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
      else
        echo "<td>$name: $col_value</td>\n";
    }
    echo "<tr>\n";
  }
  echo "</table>\n";
  printf("Somme des surfaces: %.0f ha<br>\n", $sum['area_ha']);
  printf("Somme des longueurs: %.0f km<br>\n", $sum['length_km']);
}
else {
  $geometry = null;
  while ($tuple = pg_fetch_array($result, null, PGSQL_ASSOC)) {
    $id = md5(json_encode($tuple));
    if ($id <> $tid)
      continue;
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
    die("Aucun enregistrement correspondant à $tid");
  }
  $geometry = correctProjectError(Geometry::create(json_decode($geometry, true)));
  $center = $geometry->center();
  $zoom = Zoom::zoomForBBoxSize($geometry->bbox()->size());
  $href = "http://$_SERVER[HTTP_HOST]".dirname($_SERVER['SCRIPT_NAME'])."/map.php?"
        ."table=$table_name&amp;mid=$tid&amp;lon=$center[0]&amp;lat=$center[1]&amp;zoom=$zoom";
  echo "<a href='$href'>Carte de la mesure</a><br>\n";
}