<?php

use App\Models\Exercicio;
use Illuminate\Contracts\Console\Kernel;

require dirname(__DIR__).'/vendor/autoload.php';

$app = require dirname(__DIR__).'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

$catalog = require database_path('data/exercises.php');
$expectedNames = array_column($catalog, 0);
$entries = Exercicio::query()->whereNull('organizacao_id')->whereIn('nome', $expectedNames)->get();
$missing = array_diff($expectedNames, $entries->pluck('nome')->all());
$incomplete = $entries->filter(fn (Exercicio $entry) => blank($entry->instrucoes) || blank($entry->imagem_url));

echo json_encode([
    'expectedCatalogEntries' => count($expectedNames),
    'availableCatalogEntries' => $entries->count(),
    'missingEntries' => count($missing),
    'incompleteEntries' => $incomplete->count(),
], JSON_THROW_ON_ERROR).PHP_EOL;

exit($missing || $incomplete->isNotEmpty() ? 1 : 0);
