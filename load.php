<?php
/*PhpDoc:
name: load.php
title: load.php - chargement dans la table de stockage à partir de la table temporaire
functions:
classes:
doc: |
  Script permettant de charger les différentes versions des exports GéoMCE
  Dans un premier temps j'ai choisi de stocker les coord en type geometry et en proj EPSG:4326 (lon/lat)
  Au final, je choisi d'utiliser le type geography qui permet de calculer des longueurs et des surfaces
  Il existe aussi la possibilité de stocker en projection légale en définissant le SRID correctement

journal: |
  13/4/2019:
  - création
*/
require_once __DIR__.'/geojson.inc.php';

if (!$_GET || !isset($_GET['action'])) {
  die("
<a href='?action=truncate'>truncate mce</a><br>
<a href='?action=load&amp;table=mesures_emprises&amp;source=CPII&amp;date=20190411&amp;georef=direct'>
  recopie mesures_emprises dans mce CPII 20190411 direct en changeant la projection</a><br>
<a href='?action=load&amp;table=mesures_communes&amp;source=CPII&amp;date=20190411&amp;georef=commune'>
  recopie mesures_communes dans mce CPII 20190411 commune en changeant la projection</a><br>
  
<a href='?action=load&amp;table=mesure_emprise&amp;source=CPII&amp;date=20190226&amp;georef=direct'>
  recopie mesure_emprise dans mce CPII 20190226 direct en changeant la projection</a><br>
<a href='?action=load&amp;table=mesure_commune&amp;source=CPII&amp;date=20190226&amp;georef=commune'>
  recopie mesure_commune dans mce CPII 20190226 commune en changeant la projection</a><br>

<a href='?action=proj'>afficher public.spatial_ref_sys</a><br>

");
}

if ($_GET['action']=='proj') {
  $dbconn = pg_connect("host=postgresql-bdavid.alwaysdata.net dbname=bdavid_geomce user=bdavid_geomce password=geomce")
      or die('Could not connect: ' . pg_last_error());
  $query = "SELECT * FROM public.spatial_ref_sys";

  //echo "query=$query\n";
  $result = pg_query($query)
    or die('Query failed: ' . pg_last_error());
  echo "<table border=1>";
  while ($tuple = pg_fetch_array($result, null, PGSQL_ASSOC)) {
    //print_r($tuple);
    echo "<tr><td>",implode('</td><td>', $tuple),"</td></tr>\n";
  }
  echo "</table>\n";
  die();
}

if ($_GET['action']=='truncate') {
  $dbconn = pg_connect("host=postgresql-bdavid.alwaysdata.net dbname=bdavid_geomce user=bdavid password=dsbune44")
      or die('Could not connect: ' . pg_last_error());
  $query = "truncate table mce";
  $result = pg_query($query)
    or die('Query failed: ' . pg_last_error());
  die("truncate ok\n");
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

  //header('Content-type: text/plain');
  //echo 'schema='; print_r($ischema); die();
  return $ischema;
}

if ($_GET['action']=='load') {
  $table_name = $_GET['table'];
  // colonnes rajoutées dans mne
  $addedColumns = ['source'=> $_GET['source'], 'date_export'=> $_GET['date'], 'georef'=> $_GET['georef']];
  
  $dbconn = pg_connect("host=postgresql-bdavid.alwaysdata.net dbname=bdavid_geomce user=bdavid password=dsbune44")
      or die('Could not connect: ' . pg_last_error());

  // schema de la table
  if (!($ischema = ischema($table_name))) {
    die("Table $table_name incorrecte");
  }
  
  $query = "delete from mce where source='$addedColumns[source]'"
    ." and date_export='$addedColumns[date_export]' and georef='$addedColumns[georef]'";
  $result = pg_query($query)
    or die('Query failed: ' . pg_last_error());

  // génération de la requête SQL
  $columns = []; // liste des nom de colonnes sauf celle correspondant à la géométrie
  $geomColumn = null; // nom de la colonne correspondant à la géométrie
  foreach ($ischema['byPos'] as $pos => $column) {
    if ($column['udt_name']<>'geometry')
      $columns[] = $column['column_name'];
    else
      $geomColumn = $column['column_name'];
  }
  $query = "SELECT ".implode(', ', $columns).", ST_AsGeoJSON($geomColumn) as geometry FROM public.$table_name";

  //echo "query=$query\n";
  $result = pg_query($query)
    or die('Query failed: ' . pg_last_error());

  $featno = 0;
  header('Content-type: text/plain');
  while ($tuple = pg_fetch_array($result, null, PGSQL_ASSOC)) {
    $geom = $tuple['geometry'] ? Geometry::fromGeoJSON(json_decode($tuple['geometry'], true)) : null;
    //echo "geom="; print_r($geom); echo "\n";
    if ($geom)
      $geom = correctProjectError($geom);
    //echo "geom="; print_r($geom); echo "\n";
    if (!isset($tuple['mesure_id']))
      $tuple['mesure_id'] = $featno;
    $insert = "insert into public.mce (".implode(', ', array_merge(array_keys($addedColumns), $columns)).", geom) values(";
    foreach ($addedColumns as $colname => $value) {
      $insert .= "'$value', ";
    }
    foreach ($columns as $colname) {
      $insert .= $tuple[$colname] ? "'".str_replace("'","''",$tuple[$colname])."', " : "null, ";
    }
    $insert .= ($geom ? "ST_GeomFromGeoJSON('$geom')" : 'null').');';
    if (!pg_query($insert)) {
      echo "$insert\n";
      die('Query failed: ' . pg_last_error());
    }
    $featno++;
    //if ($featno >= 10) break;
  }
  die("-- Fin OK, $featno enregistrements insérés\n\n\n");
}
die("Aucune action");