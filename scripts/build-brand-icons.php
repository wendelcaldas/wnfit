<?php

// Render the same geometric W used in public/icons/wnfit.svg, without font dependencies.
$directory = dirname(__DIR__).'/public/icons';
$points = [116, 144, 168, 144, 196, 315, 231, 176, 281, 176, 316, 315, 344, 144, 396, 144, 349, 368, 294, 368, 256, 228, 218, 368, 163, 368];

foreach ([32, 180, 192, 512] as $size) {
    $image = imagecreatetruecolor(512, 512);
    $green = imagecolorallocate($image, 163, 230, 53);
    $ink = imagecolorallocate($image, 21, 23, 21);
    imagefill($image, 0, 0, $green);
    imagefilledpolygon($image, $points, $ink);
    $resized = imagecreatetruecolor($size, $size);
    imagecopyresampled($resized, $image, 0, 0, 0, 0, $size, $size, 512, 512);
    imagepng($resized, $directory.'/wnfit-'.$size.'.png');
    imagedestroy($resized);
    imagedestroy($image);
}

// ICO wrapper containing a PNG also covers browsers requesting /favicon.ico directly.
$png = file_get_contents($directory.'/wnfit-32.png');
$header = pack('vvv', 0, 1, 1);
$entry = pack('CCCCvvVV', 32, 32, 0, 0, 1, 32, strlen($png), 22);
file_put_contents(dirname(__DIR__).'/public/favicon.ico', $header.$entry.$png);

echo "WNFit browser icons generated.\n";
