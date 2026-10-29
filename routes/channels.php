<?php

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Broadcast;
use Illuminate\Support\Facades\Route;

Broadcast::channel('staff.org.{organizationId}.user.{userId}', function (User $user, string $organizationId, string $userId) {
    return (string) $user->id === $userId && $user->organizacoes()->whereKey($organizationId)->where('organizacoes.ativa', true)->wherePivot('status', 'ativo')->wherePivotIn('papel', ['proprietario', 'administrador', 'professor'])->exists();
});

// Use the normal web CSRF protection for the session-authenticated POST handshake.
Route::post('/broadcasting/auth', function (Request $request) {
    return Broadcast::auth($request);
})->middleware(['web', 'auth', 'staff', 'throttle:60,1']);
