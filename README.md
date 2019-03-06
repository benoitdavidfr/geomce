# Carte GéoMCE

Ce projet produit une [carte disponible ici](http://gexplor.fr/geomce) correspondant à un export de GéoMCE PgSQL.

Liste des fichiers:
  - geojson.php lit la base PgSQL et génère un flux GéoJSON généralisé et stylé en fonction du zoom et du BBox
  - html.php lit la base PgSQL et génère une page HTML des mesures
  - geojson.inc.php implémente des fonctions communes aux 2 scripts précédents
  - coordsys.inc.php implémente les changements de CRS
  - localtest.inc.php simule les fonctions PgSQL pour permettre des tests locaux
  - map.php est la carte Leaflet d'affichage du flux GéoJSON, legend.php est sa légende
  - marker.php génère un symbole ponctuel en fonction du nom du symbole, de la couleur, ...
  - index.php est la page d'accueil
  - phpdoc.yaml est un fichier de documentation
  - legendimg contient des images utilisées dans la légende
  - 20190226 contient la livraison de l'export du 26/02/2019.
