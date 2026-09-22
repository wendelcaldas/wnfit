<?php

use App\Models\Organizacao;
use App\Services\RecurringBillingService;
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

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');
