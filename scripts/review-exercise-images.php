<?php

// Contact sheets for visual review only; source illustrations remain unchanged.
require dirname(__DIR__).'/vendor/autoload.php';

use Illuminate\Support\Str;

$root = dirname(__DIR__);
$catalog = require $root.'/database/data/exercises.php';
$offset = isset($argv[1]) ? (int) $argv[1] : 80;
$directory = $root.'/storage/app/exercise-review';
if (! is_dir($directory)) {
    mkdir($directory, 0755, true);
}
foreach (array_chunk(array_slice($catalog, $offset), 12) as $page => $rows) {
    $sheet = imagecreatetruecolor(1260, 1200);
    imagefill($sheet, 0, 0, imagecolorallocate($sheet, 255, 255, 255));
    $ink = imagecolorallocate($sheet, 25, 25, 25);
    foreach ($rows as $index => $row) {
        $file = $root.'/public/media/exercises/v1/'.Str::slug($row[0]).'.webp';
        if (! is_file($file)) {
            throw new RuntimeException('Missing illustration: '.$row[0]);
        }
        $image = imagecreatefromwebp($file);
        $x = ($index % 3) * 420;
        $y = intdiv($index, 3) * 300;
        $height = (int) round(410 * imagesy($image) / imagesx($image));
        imagecopyresampled($sheet, $image, $x + 5, $y + 5, 0, 0, 410, min(270, $height), imagesx($image), imagesy($image));
        imagestring($sheet, 3, $x + 8, $y + 278, Str::ascii(($offset + $page * 12 + $index + 1).'. '.$row[0]), $ink);
        imagedestroy($image);
    }
    imagepng($sheet, $directory.'/page-'.($page + 1).'.png');
    imagedestroy($sheet);
}
echo json_encode(['directory' => $directory, 'sheets' => (int) ceil((count($catalog) - $offset) / 12)], JSON_THROW_ON_ERROR).PHP_EOL;
