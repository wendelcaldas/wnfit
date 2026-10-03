<?php

// Lossy format/size optimization only; never redraws or alters the exercise demonstration.
if (PHP_SAPI !== 'cli' || count($argv) !== 3) {
    fwrite(STDERR, "Usage: php scripts/prepare-exercise-image.php source.png exercise-slug\n");
    exit(1);
}
[$script, $source, $slug] = $argv;
if (! preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/', $slug) || ! is_file($source)) {
    throw new RuntimeException('Invalid source or exercise slug.');
}
$image = imagecreatefrompng($source);
if (! $image) {
    throw new RuntimeException('Could not open generated image.');
}
$width = min(1200, imagesx($image));
$height = (int) round(imagesy($image) * $width / imagesx($image));
$optimized = imagescale($image, $width, $height, IMG_BICUBIC_FIXED);
$directory = dirname(__DIR__).'/public/media/exercises/v1';
if (! is_dir($directory)) {
    mkdir($directory, 0755, true);
}
$target = $directory.'/'.$slug.'.webp';
if (! imagewebp($optimized, $target.'.tmp', 85)) {
    throw new RuntimeException('Could not write optimized image.');
}
rename($target.'.tmp', $target);
imagedestroy($image);
imagedestroy($optimized);
echo json_encode(['slug' => $slug, 'width' => $width, 'height' => $height, 'bytes' => filesize($target)], JSON_THROW_ON_ERROR).PHP_EOL;
