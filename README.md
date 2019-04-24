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

[Proposition de schéma JSON pour la fourniture des données à l'IGN](https://benoitdavidfr.github.io/geomce/geomce.schema.json).

Résultats [disponibles ici](http://gexplor.fr/geomce).

Les exports sont disponibles:
  - [export GéoMCE géoréf. direct du 27/2/2019](https://benoitdavidfr.github.io/geomce/mcecpii20190227direct.geojson)
  - [export GéoMCE géoréf. à la commune du 27/2/2019](https://benoitdavidfr.github.io/geomce/mcecpii20190227commune.geojson)
  - [WFS Géoportail géoréf. direct à partir de l'export GéoMCE du 27/2/2019](https://benoitdavidfr.github.io/geomce/mceigngp20190227direct.geojson)
  - [WFS Géoportail géoréf. à la commune à partir de l'export GéoMCE du 27/2/2019](https://benoitdavidfr.github.io/geomce/mceigngp20190227commune.geojson)
