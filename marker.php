<?php
/*PhpDoc:
name:  marker.php
title: marker.php - Génère différents symboles pour la carte
functions:
classes:
doc: |
journal: |
  5/3/2019:
  - différents symboles
  - passage de paramètres
  4/3/2019:
  - version finalisée pour un symbole
*/

function doc() {
  echo "<!DOCTYPE html><html><head><meta charset='UTF-8'><title>marker</title></head><body>
<h2>Génération de symboles</h2>
URL de la forme http://$_SERVER[SERVER_NAME]$_SERVER[SCRIPT_NAME]/{symbol}/{colors}/{width}/{height}.{fmt}
Les symboles suivants sont définis:
<table border=1>\n";
  foreach (['circle/0000FF','square/3BB9FF','diam/0000FF','undefined/FF0000'] as $name)
    echo "<tr><td>$name</td><td><img src='http://$_SERVER[SERVER_NAME]$_SERVER[SCRIPT_NAME]/$name/70'></td></tr>\n";
  die("</table>\n");
}

{ // interprétation des paramètres 
  $params = substr($_SERVER['PHP_SELF'], strlen($_SERVER['SCRIPT_NAME'])+1);
  $fmt = null;
  if (in_array($fmt = substr($params, -4), ['.png','.jpg']))
    $params = substr($params, 0, strlen($params)-4);
  $params = explode('/', $params);
  if (count($params)==1) {
    $symbol = $params[0];
    $color = '0000FF';
    $width = 10;
    $height = 10;
  }
  elseif (count($params)==2) {
    list($symbol, $color) = $params;
    $width = 10;
    $height = 10;
  }
  elseif (count($params)==3) {
    list($symbol, $color, $width) = $params;
    $height = $width;
  }
  elseif (count($params)==4) {
    list($symbol, $color, $width, $height) = $params;
  }
  else
    doc();
  if (!$symbol)
    doc();
  if (strlen($color)<>6)
    $color = 'FF0000';
}

function error(string $message) { die($message); }

// Création d'une image
$im = imagecreatetruecolor (2*$width, 2*$height)
  or error("Erreur imagecreatetruecolor");
// passage en blending FALSE pour copier un fond transparent
imagealphablending($im, FALSE)
  or error("Erreur sur imagealphablending(FALSE)");
// création de la couleur transparente
if (($transparent = imagecolorallocatealpha($im, 0xFF, 0xFF, 0xFF, 0x7F)) == false)
  error("Erreur sur imagecolorallocatealpha");
// remplissage de l'image par la couleur transparente
imagefilledrectangle ($im, 0, 0, 2*$width-1, 2*$height-1, $transparent)
  or error("Erreur sur imagefilledrectangle");
// passage en blending TRUE pour copier normalement
imagealphablending($im, TRUE)
  or error("Erreur sur imagealphablending(TRUE)");

if (($grey = imagecolorallocatealpha($im, 0x40, 0x40, 0x40, 0x40)) === false)
  error("Erreur sur imagecolorallocatealpha");

$color = imagecolorallocatealpha($im, hexdec(substr($color,0,2)), hexdec(substr($color,2,2)), hexdec(substr($color,4,2)), 0);
if ($color === false)
  error("Erreur sur imagecolorallocatealpha");

if ($symbol=='circle') {
  // ombre grise transparente décalée
  imagefilledellipse($im, $width*1.2, $height*1.3, $width, $height, $grey)
    or error("Erreur imagefilledellipse");
  imagefilledellipse($im, $width, $height, $width, $height, $color)
    or error("Erreur imagefilledellipse");
}
elseif ($symbol=='square') {
  // ombre grise transparente décalée
  imagefilledrectangle($im, $width/2+$width*0.2, $height/2+$height*0.3, 3*$width/2+$width*0.3, 3*$height/2+$height*0.3, $grey)
    or error("Erreur imagefilledrectangle");
  imagefilledrectangle($im, $width/2, $height/2, 3*$width/2, 3*$height/2, $color)
    or error("Erreur imagefilledrectangle");
}
elseif ($symbol=='diam') {
  $points = [
    $width/2, $height,
    $width, $height/2,
    $width*1.5, $height,
    $width, $height*1.5,
  ];
  $offset = []; // Pts de décalés de 3 pixels en X et Y
  for ($i=0; $i<count($points)/2; $i++) {
    $offset[2*$i] = $points[2*$i] + $width*0.15;
    $offset[2*$i+1] = $points[2*$i+1] + $height*0.3;
  }
  // ombre grise transparente décalée
  imagefilledpolygon($im, $offset, count($offset)/2, $grey)
    or error("Erreur imagefilledpolygon");
  imagefilledpolygon($im, $points, count($points)/2, $color)
    or error("Erreur imagefilledpolygon");
}
else { // symbole inconnu => rond coloré avec rectangle blanc
  // ombre grise transparente décalée
  imagefilledellipse($im, $width*1.3, $height*1.3, $width, $height, $grey)
    or error("Erreur imagefilledellipse");
  // grand rond opaque
  imagefilledellipse($im, $width, $height, $width, $height, $color)
    or error("Erreur imagefilledellipse");
  // petit rectangle blanc
  $white = imagecolorallocatealpha($im, 0xFF, 0xFF, 0xFF, 0)
    or error("Erreur sur imagecolorallocatealpha");
  imagefilledrectangle($im, 0.7*$width, 0.9*$height, 1.3*$width, 1.1*$height, $white)
    or error("Erreur imagefilledrectangle");
}
// Affichage de l'image
imagealphablending($im, FALSE)
  or error("erreur sur imagealphablending(FALSE)");
imagesavealpha($im, TRUE)
  or error("erreur sur imagesavealpha(TRUE)");
if ($fmt=='.jpg') {
  header('Content-type: image/jpeg');
  imagejpeg($im);
}
else {
  header('Content-type: image/png');
  imagepng($im);
}
imagedestroy($im);
die();
