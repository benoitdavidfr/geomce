<?php
/*PhpDoc:
name:  index.php
title: index.php - Page d'accueil
doc: |
journal: |
  4/3/2019:
  - version finalisée
*/
?>
<!DOCTYPE html><html><head><meta charset='UTF-8'><title>GéoMCE</title></head><body>
<h2>Contrôle qualité des exports GéoMCE transmis</h2>

<a href='load.php'>chargement des tables</a><br>
<a href='geojson.php'>Flux GéoJSON</a>,
<a href='html.php'>Affichage tables</a><br>
<a href='map.php'>carte</a> et sa <a href='legend.php'>légende</a><br>

<a href='http://georef.eu/yamldoc/?doc=geomce-remarques'>Quelques remarques</a><br>

<?php
if ($_SERVER['SERVER_NAME']=='localhost') {
  echo "<h2>Dev</h2>\n";
  echo "<a href='http://localhost/synchro.php?remote=http://bdavid.alwaysdata.net/&dir=gexplor/geomce' target ='_blank'>synchro</a><br>\n";
  echo "<a href='http://bdavid.alwaysdata.net/gexplor/geomce/' target ='_blank'>accueil</a><br>\n";
  
  echo "<h2>Prod</h2>\n";
  echo "<a href='http://localhost/synchro.php?remote=http://prod.geoapi.fr&dir=gexplor/geomce' target ='_blank'>synchro</a><br>\n";
  echo "<a href='http://gexplor.fr/geomce/' target ='_blank'>accueil</a><br>\n";
  
  echo "<h2>Docs</h2><ul>
    <li><a href='http://postgis.net/docs/manual-2.4/'>Manuel PostGis v2.4</a></li>
    <li><a href='https://docs.postgresql.fr/10//'>Manuel PostgreSQL v10</a></li>
  
  </ul>\n";
}
