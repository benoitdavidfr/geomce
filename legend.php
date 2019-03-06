<!DOCTYPE html><html><head><meta charset='UTF-8'><title>legend</title></head><body>
<h2>Légende la carte GéoMCE</h2>
<table border=1>
<th></th><th>surfacique</th><th>linéaire</th><th>ponctuelle</th>
<tr><td>mesure géolocalisée précisément</td>
<td><center><img width=40 height=40 src='legendimg/polygon.jpg'></center></td>
<td><center><img width=40 height=40 src='legendimg/linestring.jpg'></center></td>
<td><center><img width=20 height=20 src='marker.php/circle/0000FF/10'></center></td>
</tr>
<tr><td>mesure géolocalisée à la commune</td>
<td colspan=3><center><img width=20 height=20 src='marker.php/diam/0000FF/10'></center></td>
</tr>
<tr><td>mesure généralisée</td>
<td colspan=2><center><img width=80 height=40 src='legendimg/bbox.jpg'></center></td>
<td><center><img width=20 height=20 src='marker.php/square/3BB9FF/10'></center></td>
</tr>
</table>

<br><br>
<h3>Description de l'algorithme de généralisation cartographique des mesures</h3>

Les mesures sont souvent petites et à petite échelle (ex 1/1M), elle sont alors représentées par un point.
Cependant certaines mesures ont une surface petite mais une extension importante, comme par exemple
<a href='map.php?table=mesure_emprise&mid=cd1371f703b18c04055326e7eefe9697&lon=-0.016531&lat=45.857592&zoom=7'>la mesure concernant la LGV Tours-Bordeaux</a>. Dans ce cas une représentation par un point n'a pas de sens.
Pour traiter ces cas, en fait assez fréquents, une telle mesure ayant une extension importante est décomposée en fragments,
regroupant plusieurs éléments géométriques proches qui sont agrégés ;
chaque fragment est alors représenté soit par un point si son extension est faible,
soit par une boite englobante si elle est plus grande.
Cette décomposition dépend du zoom d'affichage.
</p>

Les principes de l'algorithme de généralisation d'une mesure sont les suivants :<ul>
  <li>une mesure correspondant à un point n'est jamais généralisée,
  <li>pour les zooms supérieurs ou égaux à
    <a href='map.php?lon=0.011714&lat=47.094534&zoom=14'>14, correspondant à l'affichage de la carte IGN au 1/25 000</a>, 
    les mesures ne sont plus généralisées,
  <li>pour les zooms inférieurs ou égaux à
    <a href='map.php?lon=0.011714&lat=47.094534&zoom=10'>10 (jusqu'à la carte 1/1M)</a>:<ul>
    <li>les petites mesures sont généralisées par un point,
    <li>les grandes mesures correspondant à un seul élément géométrique (LineString ou Polygon) sont représentées par leur boite englobante,
    <li>les autres grandes mesures sont décomposées en fragments, les petits fragments sont représentés par un point
      et les grands par une boite englobante.
  </ul>
  <li>pour les zooms intermédiaires 11 à 13, les mêmes règles que pour le zoom inférieurs à 10 sont appliquées
    mais les géométries suffisament simples ne sont pas généralisées
    (<a href='map.php?lon=1.685571&lat=47.530891&zoom=11'>exemple</a>).
</ul>
</body>
</html>