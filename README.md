# Vérification du processus de publication de GéoMCE

L'objectif de ce projet est d'effectuer une vérification du processus de publication de GéoMCE CPII -> Cerema -> Géoportail  
La démarche est de stocker les versions successives des livraisons:

  - export GéoMCE produit par le CPII (export PotsgreSQL) (id cpii)
  - export Céréma vers le Géoportail (SHP, WFS, GeoJSON ?) (id cerema)
  - flux WFS du Géoportail (id igngp)
  
et de réaliser sur ces livraisons:
  - une visualisation sous la forme de cartes Leaflet
  - des croisements entre eux afin notamment de détecter des différences
  - des statistiques simples sur chacune

Résultats [disponibles ici](http://gexplor.fr/geomce).

Les exports sont disponibles:
  - [export GéoMCE géoréf. direct du 26/2/2019](https://benoitdavidfr.github.io/geomce/mcecpii20190226direct.geojson)
  - [export GéoMCE géoréf. à la commune du 26/2/2019](https://benoitdavidfr.github.io/geomce/mcecpii20190226commune.geojson)
  - [WFS Géoportail géoréf. direct du 26/2/2019](https://benoitdavidfr.github.io/geomce/mceigngp20190226direct.geojson)
  - [WFS Géoportail géoréf. à la commune du 26/2/2019](https://benoitdavidfr.github.io/geomce/mceigngp20190226commune.geojson)
