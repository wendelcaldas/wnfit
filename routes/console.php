<?php

use App\Models\Organizacao;
use App\Services\Messaging\AutomaticMessagingService;
use App\Services\Messaging\PlatformSignupNotificationService;
use App\Services\RecurringBillingService;
use App\Services\WorkoutNotificationService;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('billing:sync', function () {
    $count = 0;
    foreach (Organizacao::query()->pluck('id') as $id) {
        $count += app(RecurringBillingService::class)->sync($id);
    }
    $this->info("{$count} cobrancas geradas. Nenhuma mensagem enviada.");
});
Schedule::command('billing:sync')->daily()->withoutOverlapping();

Artisan::command('messages:sync-billing', function () {
    $count = app(AutomaticMessagingService::class)->syncBilling();
    $this->info("{$count} mensagens financeiras enfileiradas.");
});
Schedule::command('messages:sync-billing')->dailyAt('08:00')->withoutOverlapping();

Artisan::command('platform-signups:dispatch', function () {
    $count = app(PlatformSignupNotificationService::class)->dispatchPending();
    $this->info("{$count} avisos de cadastro enfileirados.");
});
Schedule::command('platform-signups:dispatch')->everyMinute()->withoutOverlapping();

Artisan::command('workouts:notify-deadlines', function () {
    $count = 0;
    foreach (Organizacao::query()->where('ativa', true)->pluck('id') as $id) {
        $count += app(WorkoutNotificationService::class)->syncDeadlines($id);
    }
    $this->info("{$count} avisos de vigência de fichas criados.");
});
Schedule::command('workouts:notify-deadlines')->dailyAt('00:05')->withoutOverlapping();

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');
