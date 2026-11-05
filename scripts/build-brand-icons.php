<?php

$directory = dirname(__DIR__).'/public/icons';
$source = imagecreatefrompng(__DIR__.'/wnfit-icon-source.png');

foreach ([32, 180, 192, 512] as $size) {
    $resized = imagecreatetruecolor($size, $size);
    imagecopyresampled($resized, $source, 0, 0, 0, 0, $size, $size, imagesx($source), imagesy($source));
    imagepng($resized, $directory.'/wnfit-'.$size.'.png');
    imagedestroy($resized);
}
imagedestroy($source);

// ICO wrapper containing a PNG also covers browsers requesting /favicon.ico directly.
$png = file_get_contents($directory.'/wnfit-32.png');
$header = pack('vvv', 0, 1, 1);
$entry = pack('CCCCvvVV', 32, 32, 0, 0, 1, 32, strlen($png), 22);
file_put_contents(dirname(__DIR__).'/public/favicon.ico', $header.$entry.$png);

echo "WNFit browser icons generated.\n";
