<?php
// export de la base GeoMCE en GeoJson

$dbconn = pg_connect("host=postgresql-bdavid.alwaysdata.net dbname=bdavid_geomce user=bdavid_geomce password=geomce")
    or die('Could not connect: ' . pg_last_error());

$query = "select table_schema, table_name, column_name, ordinal_position, data_type, udt_name
from INFORMATION_SCHEMA.COLUMNS where table_schema='public' and table_name = 'mesure_emprise'";
$result = pg_query($query) or die('Query failed: ' . pg_last_error());

$ischema = []; // [ {schema} => [ {table} => [ {ordinal_position} => [ 'column_name'=> , ... ]]]]
while ($tuple = pg_fetch_array($result, null, PGSQL_ASSOC)) {
  $ischema[$tuple['table_schema']][$tuple['table_name']]['byPos'][$tuple['ordinal_position']] = [
    'column_name'=> $tuple['column_name'],
    'data_type'=> $tuple['data_type'],
    'udt_name'=> $tuple['udt_name'],
  ];
  $ischema[$tuple['table_schema']][$tuple['table_name']]['byName'][$tuple['column_name']] = [
    'ordinal_position'=> $tuple['ordinal_position'],
    'data_type'=> $tuple['data_type'],
    'udt_name'=> $tuple['udt_name'],
  ];
}

header('Content-type: text/plain');
echo 'schema='; print_r($ischema); die();
  