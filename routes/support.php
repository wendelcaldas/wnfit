<?php

use App\Http\Controllers\HelpCenterController;
use Illuminate\Support\Facades\Route;

$helpRoutes = function () {
    Route::get('/', [HelpCenterController::class, 'index']);
    Route::get('/modulos/{slug}', [HelpCenterController::class, 'collection']);
    Route::get('/artigos/{slug}', [HelpCenterController::class, 'article']);
};

Route::domain(config('support.host'))->group(function () use ($helpRoutes) {
    $helpRoutes();
    Route::get('/{missing}', fn () => abort(404))->where('missing', '.*');
});
Route::prefix('ajuda')->group($helpRoutes);
