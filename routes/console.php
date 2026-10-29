<?php

use App\Models\Organizacao;
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
