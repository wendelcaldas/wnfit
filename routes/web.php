<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\OrganizationUserController;
use App\Http\Controllers\StudentController;
use Illuminate\Support\Facades\Route;

Route::prefix('api')->group(function () {
    Route::get('/me', [AuthController::class, 'me']);
    Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:10,1');
    Route::post('/register', [AuthController::class, 'register'])->middleware('throttle:5,1');
    Route::post('/logout', [AuthController::class, 'logout'])->middleware('auth');

    Route::middleware('auth')->group(function () {
        Route::get('/dashboard', DashboardController::class);
        Route::get('/organization/users', [OrganizationUserController::class, 'index']);
        Route::post('/organization/users', [OrganizationUserController::class, 'store']);
        Route::patch('/organization/users/{user}', [OrganizationUserController::class, 'update']);
        Route::get('/students/options', [StudentController::class, 'options']);
        Route::get('/students', [StudentController::class, 'index']);
        Route::post('/students', [StudentController::class, 'store']);
        Route::get('/students/{student}', [StudentController::class, 'show']);
        Route::patch('/students/{student}', [StudentController::class, 'update']);
        Route::post('/students/{student}/charges', [StudentController::class, 'generateCharge']);
        Route::post('/charges/{charge}/send', [StudentController::class, 'sendCharge']);
        Route::post('/charges/{charge}/pay', [StudentController::class, 'payCharge']);
    });
});

Route::view('/{any?}', 'app')->where('any', '.*');
