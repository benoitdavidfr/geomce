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
<h2>Consultation de l'export Postgresql GéoMCE transmis le 26/2/2019</h2>
Dans la carte, la localisation des mesures sur La Réunion a été corrigée des erreurs de projection.</p>
<a href='html.php/mesure_emprise'>table mesure_emprise</a><br>
<a href='html.php/mesure_commune'>table mesure_commune</a><br>
<a href='map.php'>carte</a> et sa <a href='legend.php'>légende</a><br>

<a href='geojson.php/mesure_emprise'>GéoJSON mesure_emprise</a>,
<a href='geojson.php/mesure_emprise?zoom=0'>géométrie simplifiée</a><br>

<a href='geojson.php/mesure_commune'>GéoJSON mesure_commune</a>,
<a href='geojson.php/mesure_commune?zoom=0'>géométrie simplifiée</a><br>

<a href='http://georef.eu/yamldoc/?doc=geomce-remarques'>Quelques remarques</a><br>

<?php
if ($_SERVER['SERVER_NAME']=='localhost') {
  echo "<h2>Dev</h2>\n";
  echo "<a href='http://localhost/synchro.php?remote=http://bdavid.alwaysdata.net/&dir=gexplor/geomce' target ='_blank'>synchro</a><br>\n";
  echo "<a href='http://bdavid.alwaysdata.net/gexplor/geomce/' target ='_blank'>accueil</a><br>\n";
  
  echo "<h2>Prod</h2>\n";
  echo "<a href='http://localhost/synchro.php?remote=http://prod.geoapi.fr&dir=gexplor/geomce' target ='_blank'>synchro</a><br>\n";
  echo "<a href='http://gexplor.fr/geomce/' target ='_blank'>accueil</a><br>\n";
}
