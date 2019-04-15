<?php
/*PhpDoc:
name:  index.php
title: index.php - Page d'accueil
doc: |
journal: |
  14/4/2019:
  - nlle version
  4/3/2019:
  - version finalisée
*/
echo "<!DOCTYPE html><html><head><meta charset='UTF-8'><title>GéoMCE</title></head><body>\n";
echo "<h2>Contrôle qualité des exports GéoMCE transmis</h2>\n";

if ($_SERVER['SERVER_NAME']=='localhost')
  echo "<a href='load.php' target='_blank'>chargement des tables</a><br>\n";
echo "<a href='stats.php' target='_blank'>stats</a>,
  <a href='diff.php' target='_blank'>différences</a><br>\n";
echo "<a href='geojson.php' target='_blank'>Flux GéoJSON</a>,
  <a href='html.php' target='_blank'>Affichage tables</a><br>\n";
echo "<a href='map.php' target='_blank'>carte</a> et sa <a href='legend.php' target='_blank'>légende</a><br>\n";

if ($_SERVER['SERVER_NAME']=='localhost') {
  echo "<h2>Dev</h2>\n";
  echo "<a href='http://localhost/synchro.php?remote=http://bdavid.alwaysdata.net/&dir=gexplor/geomce' target ='_blank'>synchro</a><br>\n";
  echo "<a href='http://bdavid.alwaysdata.net/gexplor/geomce/' target ='_blank'>accueil</a><br>\n";
  
  echo "<h2>Prod</h2>\n";
  echo "<a href='http://localhost/synchro.php?remote=http://prod.geoapi.fr&dir=gexplor/geomce' target ='_blank'>synchro</a><br>\n";
  echo "<a href='http://gexplor.fr/geomce/' target ='_blank'>accueil</a><br>\n";
  
  echo "<h2>Docs</h2><ul>
    <li><a href='http://postgis.net/docs/manual-2.4/' target='_blank'>Manuel PostGis v2.4</a></li>
    <li><a href='https://docs.postgresql.fr/10/' target='_blank'>Manuel PostgreSQL v10</a></li>
  </ul>\n";
}
