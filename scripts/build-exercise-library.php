<?php

require dirname(__DIR__).'/vendor/autoload.php';

use Illuminate\Support\Str;

$root = dirname(__DIR__);
$catalog = require $root.'/database/data/exercises.php';
$images = [];
$missing = [];
foreach ($catalog as $exercise) {
    $slug = Str::slug($exercise[0]);
    $file = $root.'/public/media/exercises/v1/'.$slug.'.webp';
    if (! is_file($file)) {
        $missing[] = $exercise[0];

        continue;
    }
    [$width, $height] = getimagesize($file);
    $images[Str::lower(Str::ascii($exercise[0]))] = [
        'name' => $exercise[0], 'url' => '/media/exercises/v1/'.$slug.'.webp',
        'width' => $width, 'height' => $height, 'muscle' => $exercise[1],
        'alt' => 'Demonstração de '.$exercise[0].', com posição inicial, execução e destaque dos principais músculos.',
    ];
}
if (in_array('--strict', $argv, true) && $missing) {
    fwrite(STDERR, json_encode(['missing' => $missing], JSON_UNESCAPED_UNICODE).PHP_EOL);
    exit(1);
}
$directory = $root.'/resources/js/data';
if (! is_dir($directory)) {
    mkdir($directory, 0755, true);
}
$json = json_encode($images, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR).PHP_EOL;
file_put_contents($directory.'/exerciseIllustrations.json', $json);
file_put_contents($root.'/public/media/exercises/library.json', $json);
echo json_encode(['ready' => count($images), 'missing' => count($missing)], JSON_THROW_ON_ERROR).PHP_EOL;
