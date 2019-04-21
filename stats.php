<?php
require __DIR__.'/deliveries.inc.php';

//header('Content-type: text/plain');

$connection_string = require __DIR__.'/pgconn.inc.php';
$dbconn = pg_connect($connection_string)
    or die('Could not connect: ' . pg_last_error());

$stats = [];

foreach ($deliveries as $source => $dates) {
  foreach ($dates as $date) {
    foreach (['direct','commune'] as $georef) {
      $table = "mce$source$date$georef";
      $query = "select count(*) nbre, sum(ST_Area(geom))/10000 as area_ha, sum(ST_Length(geom))/1000 as length_km from $table";
      //echo "query=$query<br>\n";
      $result = pg_query($query)
        or die('Query failed: ' . pg_last_error());
      while ($tuple = pg_fetch_array($result, null, PGSQL_ASSOC)) {
        //print_r($tuple);
        $stats[$source][$date][$georef] = $tuple;
      }
      
      if (1) {
        $nbPoints = 0;
        $nbCollections = 0;
        $typesCollection = [];
        $query = "select ST_AsGeoJSON(geom) geometry from $table";
        //echo "query=$query\n";
        $result = pg_query($query)
          or die('Query failed: ' . pg_last_error());
        while ($tuple = pg_fetch_array($result, null, PGSQL_ASSOC)) {
          //print_r($tuple);
          if (!$tuple['geometry'])
            continue;
          $geom = json_decode($tuple['geometry'], true);
          if ($geom['type'] == 'Point')
            $nbPoints++;
          elseif ($geom['type'] == 'MultiPoint')
            $nbPoints += count($geom['coordinates']);
          elseif ($geom['type'] == 'GeometryCollection') {
            //print_r($geom);
            $nbCollections++;
            $typeCollection = [];
            foreach ($geom['geometries'] as $geometry) {
              $typeCollection[$geometry['type']] = 1;
              if ($geometry['type'] == 'Point')
                $nbPoints++;
              elseif ($geometry['type'] == 'MultiPoint')
                $nbPoints += count($geometry['coordinates']);
            }
            $typeCollection = array_keys($typeCollection);
            sort($typeCollection);
            $typeCollection = implode('', $typeCollection);
            if (!isset($typesCollection[$typeCollection]))
              $typesCollection[$typeCollection] = 1;
            else
              $typesCollection[$typeCollection]++;
          }
        }
        $stats[$source][$date][$georef]['nbPoints'] = $nbPoints;
        $stats[$source][$date][$georef]['nbCollections'] = $nbCollections;
        $stats[$source][$date][$georef]['typesCollection'] = $typesCollection;
      }
    }
  }
}

echo "<!DOCTYPE html><html><head><meta charset='UTF-8'><title>stats</title></head><body>\n";
echo "<h2>Dénombrement des mesures compensatoires environnementales</h2>\n";
//echo "<pre>stats = "; print_r($stats); echo "</pre>\n";
echo "<table border=1>\n",
  "<th>source</th><th>date</th><th>nbre direct</th><th>area_ha</th><th>length_km</th><th>nbpoints</th>\n",
  "<th>nbre com</th><th>nbcoms</th>\n";
foreach ($stats as $source => $statsBySource) {
  foreach ($statsBySource as $date => $statsByDate) {
    echo "<tr><td>$source</td><td>$date</td>\n";
    printf("<td align=right>%d</td><td align=right>%.0f</td><td align=right>%.0f</td align=right>"
      ."<td align=right>%d</td><td align=right>%d</td><td align=right>%d</td>\n",
      $statsByDate['direct']['nbre'], $statsByDate['direct']['area_ha'],
      $statsByDate['direct']['length_km'], $statsByDate['direct']['nbPoints'],
      $statsByDate['commune']['nbre'], $statsByDate['commune']['nbPoints']);
    echo "</tr>\n";
  }
}
echo "</table>\n";

echo "<h3>Description des collections</h3>\n";
echo "</p><table border=1><th>source</th><th>date</th><th>nbCollections</th>",
  "<th>PLS</th><th>LS</th><th>PS</th><th>PL</th>\n";
foreach ($stats as $source => $statsBySource) {
  foreach ($statsBySource as $date => $statsByDate) {
    $s = $statsByDate['direct'];
    if ($s['nbCollections']==0)
      continue;
    echo "<tr><td>$source</td><td>$date</td>\n";
    echo "<td>$s[nbCollections]</td>";
    $t = $s['typesCollection'];
    echo "<td>$t[LineStringPointPolygon]</td>";
    echo "<td>$t[LineStringPolygon]</td>";
    echo "<td>$t[PointPolygon]</td>";
    echo "<td>$t[LineStringPoint]</td>";
    echo "</tr>\n";
  }
}  
echo "</table>\n";
