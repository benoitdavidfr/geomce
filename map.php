<?php
/*PhpDoc:
name: map.php
title: map.php - Carte des mesures ou d'une seule en fonction des paramètres - Benoit DAVID - 2/3/2019
functions:
classes:
doc: |
  XX
journal: |
  4/3/2019:
  - version finalisée
*/

$lat = isset($_GET['lat']) ? $_GET['lat'] : 47;
$lon = isset($_GET['lon']) ? $_GET['lon'] : 1;
$zoom = isset($_GET['zoom']) ? $_GET['zoom'] : 8;
$table = isset($_GET['table']) ? $_GET['table'] : null;
$mid = isset($_GET['mid']) ? $_GET['mid'] : null;
$geomceUrl = ($_SERVER['SERVER_NAME']=='localhost') ? "'http://localhost/gexplor/geomce'"
    : (($_SERVER['SERVER_NAME']=='bdavid.alwaysdata.net') ? "'http://bdavid.alwaysdata.net/gexplor/geomce'"
    : "'https://gexplor.fr/geomce'");
?>
<!DOCTYPE HTML><html><head><title>Carte GéoMCE</title><meta charset='UTF-8'>
<!-- meta nécessaire pour le mobile -->
  <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no" />
  <link rel='stylesheet' href='http://visu.gexplor.fr/viewer.css'>
  <link rel='stylesheet' href='https://unpkg.com/leaflet@1.3/dist/leaflet.css'>
  <script src='https://unpkg.com/leaflet@1.3/dist/leaflet.js'></script>
  <script src='http://visu.gexplor.fr/lib/leaflet.uGeoJSON.js'></script>
  <script src='http://visu.gexplor.fr/lib/leaflet.edgebuffer.js'></script>
</head>
<body>
  <div id='map' style='height: 100%; width: 100%'></div>
  <script>
    var map = L.map('map').setView(<?php echo "[$lat, $lon], $zoom";?>); // view pour la zone
L.control.scale({position:'bottomleft', metric:true, imperial:false}).addTo(map);
var bases = {
  "Cartes IGN" : new L.TileLayer(
    'http://igngp.geoapi.fr/tile.php/cartes/{z}/{x}/{y}.jpg',
    {"format":"image/jpeg","minZoom":0,"maxZoom":18,"attribution":"&copy; <a href='http://www.ign.fr'>IGN</a>"}
  ),
  "Cartes IGN N&B" : new L.TileLayer(
    'http://igngp.geoapi.fr/tile.php/scan-express-ng/{z}/{x}/{y}.png',
    {"format":"image/png","minZoom":0,"maxZoom":18,"attribution":"&copy; <a href='http://www.ign.fr'>IGN</a>"}
  ),
  "Ortho-images" : new L.TileLayer(
    'http://igngp.geoapi.fr/tile.php/orthos/{z}/{x}/{y}.jpg',
    {"format":"image/jpeg","minZoom":0,"maxZoom":18,"attribution":"&copy; <a href='http://www.ign.fr'>IGN</a>"}
  ),
  "Fond blanc" : new L.TileLayer(
    'http://visu.gexplor.fr/utilityserver.php/whiteimg/{z}/{x}/{y}.png',
    {"format":"image/png","minZoom":0,"maxZoom":21}
  ),
};
map.addLayer(bases["Cartes IGN"]);

var overlays = {
  "mesure_emprise" : new L.UGeoJSONLayer({
    lyrid: 'maps/geomce/mesure_emprise',
    endpoint: <?php echo $geomceUrl;?>+'/geojson.php/mesure_emprise',
    onEachFeature: function (feature, layer) {
      layer.bindPopup(
        '<b><a href="'+<?php echo $geomceUrl;?>+'/html.php/mesure_emprise/'
        +feature.properties.id+'" target="_blank">mesure_emprise</a></b><br>'
        +'<pre>'+JSON.stringify(feature.properties,null,' ')+'</pre>'
      );
    },
    pointToLayer: function(feature, latlng) {
      return L.marker(latlng, {
        icon: L.icon({
          iconUrl: <?php echo $geomceUrl;?>+'/marker.php/'+feature.style["marker-symbol"],
          iconSize: [20,20], iconAnchor: [10,10], popupAnchor: [0,0]
        })
      });
    },
    minZoom: 0,
    maxZoom: 21,
    usebbox: true
  }),
  "mesure_commune" : new L.UGeoJSONLayer({
    lyrid: 'maps/geomce/mesure_commune',
    endpoint: <?php echo $geomceUrl;?>+'/geojson.php/mesure_commune',
    onEachFeature: function (feature, layer) {
      layer.bindPopup(
        '<b><a href="'+<?php echo $geomceUrl;?>+'/html.php/mesure_commune/'
        +feature.properties.id+'" target="_blank">mesure_commune</a></b><br>'
        +'<pre>'+JSON.stringify(feature.properties,null,' ')+'</pre>'
      );
    },
    pointToLayer: function(feature, latlng) {
      return L.marker(latlng, {
        icon: L.icon({
          iconUrl: <?php echo $geomceUrl;?>+'/marker.php/'+feature.style["marker-symbol"],
          iconSize: [20,20], iconAnchor: [10,10], popupAnchor: [10,10]
        })
      });
    },
    minZoom: 0,
    maxZoom: 21,
    usebbox: true
  }),
<?php
if (!$table || !$mid) echo <<<EOT
};
map.addLayer(overlays["mesure_emprise"]);

EOT;
else echo <<<EOT
  "mesure" : new L.UGeoJSONLayer({
    lyrid: 'maps/geomce/$table',
    endpoint: $geomceUrl+'/geojson.php/$table/$mid',
    minZoom: 0,
    maxZoom: 21,
    usebbox: true
  }),
};
map.addLayer(overlays["mesure"]);

EOT;
?>
L.control.layers(bases, overlays).addTo(map);
  </script>
</body></html>
