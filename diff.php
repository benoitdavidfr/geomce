<?php
// mesure des différences entre 2 livraisons

$deliveries = [
  'mcecpii20190227direct',
  'mceigngp20190227direct',
];

$connection_string = require __DIR__.'/pgconn.inc.php';
$dbconn = pg_connect($connection_string)
    or die('Could not connect: ' . pg_last_error());

function display_results(string $query): void {
  if (!($result = @pg_query($query)))
    throw new Exception('Query failed: ' . pg_last_error());
  echo "<table border=1>\n";
  $first = true;
  while ($tuple = pg_fetch_array($result, null, PGSQL_ASSOC)) {
    //print_r($tuple);
    if ($first) {
      foreach ($tuple as $key => $value)
        echo "<th>$key</th>";
      echo "\n";
      $first = false;
    }
    echo "<tr>";
    foreach ($tuple as $key => $value)
      echo "<td>$value</td>\n";
    echo "</tr>\n";
  }
  echo "</table>\n";
}

try {
  echo "<h2>Comparaison des 2 livraisons $deliveries[0] (a) et $deliveries[1] (b)</h2>\n";
  
  echo "<h3>projets surfaciques ayant des surfaces différentes</h3>\n";
  $query = "select dela.projet, dela.area_ha a_area_ha, delb.area_ha b_area_ha
    from dela, delb
    where dela.projet=delb.projet
      and dela.area_ha + delb.area_ha <> 0 
      and abs((dela.area_ha-delb.area_ha)/(dela.area_ha+delb.area_ha)/2) > 0.01";
  display_results($query);

  echo "<h3>projets linéaires ayant des longueurs différentes</h3>\n";
  $query = "select dela.projet, dela.length_km a_length_km, delb.length_km b_length_km
    from dela, delb
    where dela.projet=delb.projet
      and dela.length_km + delb.length_km <> 0 
      and abs((dela.length_km-delb.length_km)/(dela.length_km+delb.length_km)/2) > 0.01";
  display_results($query);

  echo "<h3>mesures de a absentes de b</h3>\n";
  $query = "select dela.projet, dela.area_ha a_area_ha, delb.area_ha b_area_ha, dela.length_km a_length_km, delb.length_km b_length_km
  from dela left join delb on dela.projet=delb.projet
  where delb.area_ha is null or delb.length_km is null";
  display_results($query);

  echo "<h3>mesures de b absentes de a</h3>\n";
  $query = "select delb.projet, delb.area_ha b_area_ha, dela.area_ha a_area_ha, delb.length_km b_length_km, dela.length_km a_length_km
  from delb left join dela on dela.projet=delb.projet
  where dela.area_ha is null or dela.length_km is null";
  display_results($query);
} catch(Exception $e) {
  echo '<pre>',$e->getMessage(),"</pre>\n";
}


echo "<h3>Pour mémoire tables à créer dans PostgreSQL</h3><pre>
  -- création de tables simplifiées et agrégées par projet
  drop table if exists dela;
  create table dela as
  select projet, sum(ST_Area(geom))/10000 area_ha, sum(ST_Length(geom))/1000 as length_km
  from $deliveries[0]
  group by projet;

  drop table if exists delb;
  create table delb as
  select projet, sum(ST_Area(geom))/10000 area_ha, sum(ST_Length(geom))/1000 as length_km
  from $deliveries[1]
  group by projet;
  </pre>\n";
