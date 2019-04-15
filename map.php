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
$mtable = isset($_GET['table']) ? $_GET['table'] : null;
$mid = isset($_GET['mid']) ? $_GET['mid'] : null;
$geomceUrl = ($_SERVER['SERVER_NAME']=='localhost') ? 'http://localhost/gexplor/geomce'
    : (($_SERVER['SERVER_NAME']=='bdavid.alwaysdata.net') ? 'https://bdavid.alwaysdata.net/gexplor/geomce'
    : 'https://gexplor.fr/geomce');
?>
<!DOCTYPE HTML><html><head><title>Carte GéoMCE</title><meta charset='UTF-8'>
<!-- meta nécessaire pour le mobile -->
  <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no" />
  <link rel='stylesheet' href='https://visu.gexplor.fr/viewer.css'>
  <link rel='stylesheet' href='https://unpkg.com/leaflet@1.3/dist/leaflet.css'>
  <script src='https://unpkg.com/leaflet@1.3/dist/leaflet.js'></script>
  <script src='https://visu.gexplor.fr/lib/leaflet.uGeoJSON.js'></script>
  <script src='https://visu.gexplor.fr/lib/leaflet.edgebuffer.js'></script>
</head>
<body>
  <div id='map' style='height: 100%; width: 100%'></div>
  <script>
    var map = L.map('map').setView(<?php echo "[$lat, $lon], $zoom";?>); // view pour la zone
L.control.scale({position:'bottomleft', metric:true, imperial:false}).addTo(map);
var bases = {
  "Cartes IGN" : new L.TileLayer(
    'https://igngp.geoapi.fr/tile.php/cartes/{z}/{x}/{y}.jpg',
    {"format":"image/jpeg","minZoom":0,"maxZoom":18,"attribution":"&copy; <a href='http://www.ign.fr'>IGN</a>"}
  ),
  "Cartes IGN N&B" : new L.TileLayer(
    'https://igngp.geoapi.fr/tile.php/scan-express-ng/{z}/{x}/{y}.png',
    {"format":"image/png","minZoom":0,"maxZoom":18,"attribution":"&copy; <a href='http://www.ign.fr'>IGN</a>"}
  ),
  "Ortho-images" : new L.TileLayer(
    'https://igngp.geoapi.fr/tile.php/orthos/{z}/{x}/{y}.jpg',
    {"format":"image/jpeg","minZoom":0,"maxZoom":18,"attribution":"&copy; <a href='http://www.ign.fr'>IGN</a>"}
  ),
// PYR Shom
  "GéoTiff Shom" : new L.TileLayer(
    'https://geoapi.fr/shomgt/tile.php/gtpyr/{z}/{x}/{y}.png',
    { "format":"png","minZoom":0,"maxZoom":18,"detectRetina":false,
      "attribution":"&copy; <a href='http://data.shom.fr' target='_blank'>Shom</a>"
    }
  ),
  "Fond blanc" : new L.TileLayer(
    'https://visu.gexplor.fr/utilityserver.php/whiteimg/{z}/{x}/{y}.png',
    {"format":"image/png","minZoom":0,"maxZoom":21}
  ),
};
map.addLayer(bases["Cartes IGN"]);

var geomceUrl = '<?php echo $geomceUrl;?>';
var overlays = {
<?php
  require __DIR__.'/deliveries.inc.php';
  foreach ($deliveries as $source => $dates) {
    foreach ($dates as $date) {
      foreach (['direct','commune'] as $georef) {
        $table = "mce$source$date$georef";
        echo <<<EOT
  "$table" : new L.UGeoJSONLayer({
    lyrid: 'maps/geomce/mesures_emprises',
    endpoint: geomceUrl+'/geojson.php/$table',
    onEachFeature: function (feature, layer) {
      layer.bindPopup(
        '<b><a href="'+geomceUrl+'/html.php/$table/'
        +feature.properties.num+'" target="_blank">$table</a></b><br>'
        +'<pre>'+JSON.stringify(feature.properties,null,' ')+'</pre>'
      );
    },
    pointToLayer: function(feature, latlng) {
      return L.marker(latlng, {
        icon: L.icon({
          iconUrl: geomceUrl+'/marker.php/'+feature.style["marker-symbol"],
          iconSize: [20,20], iconAnchor: [10,10], popupAnchor: [0,0]
        })
      });
    },
    minZoom: 0,
    maxZoom: 21,
    usebbox: true
  }),\n
EOT;
      }
    }
  }
?>
<?php
if ($mtable && $mid) echo <<<EOT
  "mesure" : new L.UGeoJSONLayer({
    lyrid: 'maps/geomce/$mtable',
    endpoint: geomceUrl+'/geojson.php/$mtable/$mid',
    minZoom: 0,
    maxZoom: 21,
    usebbox: true
  }),
};
map.addLayer(overlays["mesure"]);

EOT;
else echo <<<EOT
};
map.addLayer(overlays["mcecpii20190226direct"]);

EOT;
?>
L.control.layers(bases, overlays).addTo(map);
  </script>
</body></html>
