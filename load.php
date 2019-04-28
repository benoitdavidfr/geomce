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

  Ecarts entre les versions CPII et IGNGP:
    - des erreurs ont été détectées
    - chgt d'un nom de champ type -> type_mesure
    - modification des tableaux PostgreSQL
      - la syntaxe des tableaux a été modifiée
      - les tableaux sont simplifiés en cas de répétitions
      - la sémantique si_metier/numero_dossier a été modifiée
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
  
<a href='?action=load&amp;table=mesure_emprise&amp;source=cpii&amp;date=20190227&amp;georef=direct'>
  recopie mesure_emprise dans mce cpii 20190227 direct en changeant la projection</a><br>
<a href='?action=load&amp;table=mesure_commune&amp;source=CPII&amp;date=20190227&amp;georef=commune'>
  recopie mesure_commune dans mce cpii 20190227 commune en changeant la projection</a><br>

<a href='?action=load&amp;flux=direct&amp;source=igngp&amp;date=20190227&amp;georef=direct'>
  recopie flux direct dans mce igngp 20190227 direct en changeant la projection</a><br>
<a href='?action=load&amp;flux=commune&amp;source=igngp&amp;date=20190227&amp;georef=commune'>
  recopie flux commune dans mce igngp 20190227 commune en changeant la projection</a><br>

<a href='?action=proj'>afficher public.spatial_ref_sys</a><br>

");
}

if ($_GET['action']=='proj') {
  $connection_string = require __DIR__.'/pgconn.inc.php';
  $dbconn = pg_connect($connection_string)
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

// calcule le MD5 à partir du tuple
function calculateMd5(array $tuple): string {
  $colnames = [
    'projet', 'categorie', 'mo', 'communes', 'procedure', 'date_decision', 'classe', 'type', 'cat', 'sscat', 'duree',
    'si_metier', 'numero_dossier',
  ];
  //echo "-- ",json_encode($tuple),"<br>\n";
  $concat = '';
  foreach ($colnames as $colname) {
    $val = isset($tuple[$colname]) ? $tuple[$colname] : '';
    if (in_array($colname, ['communes','si_metier','numero_dossier']))
      $val = str_replace('"', '', $val);
    $concat .= $val . '/';
  }
  $md5 = md5($concat);
  //echo "-- md5=$md5, concat=$concat<br>\n";
  return $md5;
}

function simplifySiMetier(array $tuple): array {
  //echo "si_metier=$tuple[si_metier], numero_dossier=$tuple[numero_dossier]<br>\n";
  $si_metiers = substr($tuple['si_metier'], 1, strlen($tuple['si_metier'])-2);
  $si_metiers = explode(',', $si_metiers);
  $numero_dossiers = substr($tuple['numero_dossier'], 1, strlen($tuple['numero_dossier'])-2);
  $numero_dossiers = explode(',', $numero_dossiers);
  $tab = [];
  $si_metier2 = [];
  $numero_dossier2 = [];
  foreach($si_metiers as $i => $si_metier) {
    $v = $si_metier.':'.$numero_dossiers[$i];
    if (!in_array($v, $tab)) {
      $tab[] = $v;
      $si_metier2[] = $si_metier;
      $numero_dossier2[] = $numero_dossiers[$i];
    }
  }
  $tuple['si_metier'] = '{'.implode(',', $si_metier2).'}';
  $tuple['numero_dossier'] = '{'.implode(',', $numero_dossier2).'}';
  return $tuple;
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
    $featno++;
    //if ($featno <> 2697) continue;
    $geom = $tuple['geometry'] ? Geometry::fromGeoJSON(json_decode($tuple['geometry'], true)) : null;
    //echo "geom="; print_r($geom); echo "\n";
    if ($geom)
      $geom = correctProjectError($geom);
    //echo "geom="; print_r($geom); echo "\n";
    $tuple['num'] = isset($tuple['mesure_id']) ? $tuple['mesure_id'] : $featno;
    $tuple = simplifySiMetier($tuple);
    $insert = "insert into public.$dest_table (".implode(', ', $columns).", md5, geom) values(";
    foreach ($columns as $colname) {
      $val = $tuple[$colname];
      $insert .= ($val ? "'".str_replace("'","''",$val)."'" : 'null').', ';
    }
    $md5 = calculateMd5($tuple);
    $insert .= "'$md5', ";
    $insert .= ($geom ? "ST_GeomFromGeoJSON('$geom')" : 'null').');';
    //echo "$insert\n";
    if (!pg_query($insert)) {
      echo "$insert\n";
      die('line '.__LINE__.', Query failed: ' . pg_last_error());
    }
    //if ($featno >= 10) break;
  }
  die("-- Fin OK, $featno enregistrements insérés dans $dest_table\n\n\n");
}

// corrige les erreurs du flux IGNGP
function convertProperties(array $properties): array {
  // Utilisation de la syntaxe des tableaux
  foreach (['communes','si_metier','numero_dossier'] as $colname)
  $properties[$colname] =
    $properties[$colname] ? '{"'.str_replace('+', '","', $properties[$colname]).'"}' : '{NULL}';
  // utilisation du nom utilisé à l'origine
  $properties['type'] = $properties['type_mesure'];
  return $properties;
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
  $urlfmt = ($_SERVER['HTTP_HOST']=='localhost' ? 'http://localhost/geoapi' : 'http://geoapi.fr')
    .'/mce/wfs.php?SERVICE=WFS&VERSION=2.0.0&REQUEST=GetFeature'
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
        $properties = convertProperties($feature['properties']);
        $properties['num'] = $featno+1;
        $insert = "insert into public.$dest_table (".implode(', ', $columns).", geom) values(";
        foreach ($columns as $colname) {
          if ($colname == 'md5') continue;
          $val = isset($properties[$colname]) ? $properties[$colname] : null;
          $insert .= ($val ? "'".str_replace("'","''",$val)."'" : 'null').', ';
        }
        $md5 = calculateMd5($properties);
        $insert .= "'$md5', ";
        $geom = Geometry::fromGeoJSON($feature['geometry']);
        $geom = $geom->proj(function ($pos) { return WebMercator::geo($pos); });
        $insert .= ($geom ? "ST_GeomFromGeoJSON('$geom')" : 'null').');';
        if (!pg_query($insert)) {
          echo "$insert<br>\n";
          die('Query failed: ' . pg_last_error());
        }
        $featno++;
        //if ($featno >= 10) break 2;
      }
      if ($contents['totalFeatures'] < $start + $count)
        break;
      $start += $count;
    }
  }
  die("couches ".implode(',',$typenames[$_GET['flux']])." recopiées dans $dest_table\n");
}

die("Aucune action");