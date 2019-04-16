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
<a href='?action=load&amp;table=mesures_emprises&amp;source=cpii&amp;date=20190411&amp;georef=direct'>
  recopie mesures_emprises dans mce cpii 20190411 direct en changeant la projection</a><br>
<a href='?action=load&amp;table=mesures_communes&amp;source=cpii&amp;date=20190411&amp;georef=commune'>
  recopie mesures_communes dans mce cpii 20190411 commune en changeant la projection</a><br>
  
<a href='?action=load&amp;table=mesure_emprise&amp;source=cpii&amp;date=20190226&amp;georef=direct'>
  recopie mesure_emprise dans mce cpii 20190226 direct en changeant la projection</a><br>
<a href='?action=load&amp;table=mesure_commune&amp;source=CPII&amp;date=20190226&amp;georef=commune'>
  recopie mesure_commune dans mce cpii 20190226 commune en changeant la projection</a><br>

<a href='?action=load&amp;flux=direct&amp;source=igngp&amp;date=20190226&amp;georef=direct'>
  recopie flux direct dans mce igngp 20190226 direct en changeant la projection</a><br>
<a href='?action=load&amp;flux=commune&amp;source=igngp&amp;date=20190226&amp;georef=commune'>
  recopie flux commune dans mce igngp 20190226 commune en changeant la projection</a><br>

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

/*if ($_GET['action']=='truncate') {
  $dbconn = pg_connect(xxx)
      or die('Could not connect: ' . pg_last_error());
  $query = "truncate table mce";
  $result = pg_query($query)
    or die('Query failed: ' . pg_last_error());
  die("truncate ok\n");
}*/

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

if (($_GET['action']=='load') && isset($_GET['table'])) {
  header('Content-type: text/plain');
  $srce_table = $_GET['table'];
  $dest_table = "mce$_GET[source]$_GET[date]$_GET[georef]";
  // colonnes rajoutées dans mne
  //$addedColumns = ['source'=> $_GET['source'], 'date_export'=> $_GET['date'], 'georef'=> $_GET['georef']];
  
  $secret_connection_string = require __DIR__.'/secret.inc.php';
  $dbconn = pg_connect($secret_connection_string)
      or die('Could not connect: ' . pg_last_error());

  // schema de la table
  if (!($ischema = ischema($srce_table))) {
    die("Table $srce_table incorrecte");
  }
  
  $query = "drop table if exists $dest_table";
  if (!($result = pg_query($query)))
    die('line '.__LINE__.', Query failed: ' . pg_last_error());
  $query = "create table $dest_table (like mce)";
  //echo "$query\n";
  if (!($result = pg_query($query)))
    die('line '.__LINE__.', Query failed: ' . pg_last_error());

  // génération de la requête SQL
  $columns = []; // liste des nom de colonnes sauf celle correspondant à la géométrie
  $geomColumn = null; // nom de la colonne correspondant à la géométrie
  foreach ($ischema['byPos'] as $pos => $column) {
    if ($column['udt_name']<>'geometry')
      $columns[] = $column['column_name'];
    else
      $geomColumn = $column['column_name'];
  }
  if (!$geomColumn)
    die("geomColumn incorrect");
  $query = "SELECT ".implode(', ', $columns).", ST_AsGeoJSON($geomColumn) as geometry FROM public.$srce_table";

  //echo "query=$query\n";
  if (!($result = @pg_query($query))) {
    echo "query: $query\n\n";
    die('line '.__LINE__.', Query failed: ' . pg_last_error());
  }

  if (!in_array('num', $columns))
    $columns = array_merge(['num'], $columns);
  
  $featno = 0;
  while ($tuple = pg_fetch_array($result, null, PGSQL_ASSOC)) {
    $geom = $tuple['geometry'] ? Geometry::fromGeoJSON(json_decode($tuple['geometry'], true)) : null;
    //echo "geom="; print_r($geom); echo "\n";
    if ($geom)
      $geom = correctProjectError($geom);
    //echo "geom="; print_r($geom); echo "\n";
    $tuple['num'] = isset($tuple['mesure_id']) ? $tuple['mesure_id'] : $featno+1;
    $insert = "insert into public.$dest_table (".implode(', ', $columns).", geom) values(";
    foreach ($columns as $colname) {
      $val = $tuple[$colname];
      $insert .= ($val ? "'".str_replace("'","''",$val)."'" : 'null').', ';
    }
    $insert .= ($geom ? "ST_GeomFromGeoJSON('$geom')" : 'null').');';
    if (!pg_query($insert)) {
      echo "$insert\n";
      die('line '.__LINE__.', Query failed: ' . pg_last_error());
    }
    $featno++;
    //if ($featno >= 100) break;
  }
  die("-- Fin OK, $featno enregistrements insérés dans $dest_table\n\n\n");
}

if (($_GET['action']=='load') && isset($_GET['flux'])) {
  $typenames = [
    'direct'=> [
      'MESURES_COMPENSATOIRES:emprises_polygones',
      'MESURES_COMPENSATOIRES:emprises_lineaires',
      'MESURES_COMPENSATOIRES:emprises_ponctuelles',
    ],
    'commune'=> ['MESURES_COMPENSATOIRES:emprises_commune'],
  ];
  $urlfmt = 'http://localhost/geoapi/mce/wfs.php?SERVICE=WFS&VERSION=2.0.0&REQUEST=GetFeature'
  .'&TYPENAMES=%s&STARTINDEX=%d&COUNT=%d&outputFormat=application/json';
  
  $dest_table = "mce$_GET[source]$_GET[date]$_GET[georef]";
  $secret_connection_string = require __DIR__.'/secret.inc.php';
  $dbconn = pg_connect($secret_connection_string)
      or die('Could not connect: ' . pg_last_error());

  // schema de la table
  if (!($ischema = ischema('mce'))) {
    die("Table mce incorrecte");
  }
  
  $query = "drop table if exists $dest_table";
  $result = pg_query($query)
    or die('Query failed: ' . pg_last_error());
  $query = "create table $dest_table (like mce)";
  //echo "$query\n";
  $result = pg_query($query)
    or die('Query failed: ' . pg_last_error());
  $columns = []; // liste des noms de colonne sauf celle correspondant à la géométrie
  $geomColumn = null; // nom de la colonne correspondant à la géométrie
  foreach ($ischema['byPos'] as $pos => $column) {
    if ($column['udt_name'] == 'geography')
      $geomColumn = $column['column_name'];
    else
      $columns[] = $column['column_name'];
  }
  
  $featno = 0;
  foreach ($typenames[$_GET['flux']] as $typename) {
    $start = 0;
    $count = 1000;
    while(1) {
      $url = sprintf($urlfmt, $typename, $start, $count);
      $urlmd5 = md5($url);
      if (($contents = @file_get_contents(__DIR__."/gpexports/$urlmd5.json"))===false) {
        echo "<a href='$url'>$url</a><br>\n";
        if (($contents = file_get_contents($url))===false)
          die("Erreur de lecture de $url");
        file_put_contents(__DIR__."/gpexports/$urlmd5.json", $contents);
      }
      $contents = json_decode($contents, true);
      //echo "<pre>contents="; print_r($contents); echo "</pre>\n";
      foreach ($contents['features'] as $feature) {
        $properties = $feature['properties'];
        $properties['num'] = $featno+1;
        // Utilisation de la syntaxe des tableaux
        $properties['communes'] =
          $properties['communes'] ? '{"'.str_replace('+', '","', $properties['communes']).'"}' : '{NULL}';
        $insert = "insert into public.$dest_table (".implode(', ', $columns).", geom) values(";
        foreach ($columns as $colname) {
          $insert .= (isset($properties[$colname]) && $properties[$colname]) ?
             "'".str_replace("'","''",$properties[$colname])."', " : "null, ";
        }
        $geom = Geometry::fromGeoJSON($feature['geometry']);
        $geom = $geom->reproject(function ($pos) { return WebMercator::geo($pos); });
        $insert .= ($geom ? "ST_GeomFromGeoJSON('$geom')" : 'null').');';
        if (!pg_query($insert)) {
          echo "$insert<br>\n";
          die('Query failed: ' . pg_last_error());
        }
        $featno++;
      }
      if ($contents['totalFeatures'] < $start + $count)
        break;
      $start += $count;
    }
  }
  die("couches ".implode(',',$typenames[$_GET['flux']])." recopiées dans $dest_table\n");
}

die("Aucune action");