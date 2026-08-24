<?php

namespace App\Providers;

use App\Services\Messaging\FakeWhatsAppDriver;
use App\Services\Messaging\TwilioWhatsAppDriver;
use App\Services\Messaging\WhatsAppMessageDriver;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(WhatsAppMessageDriver::class, function () {
            if (config('services.twilio.whatsapp.driver') === 'fake') {
                return new FakeWhatsAppDriver();
            }

            return new TwilioWhatsAppDriver(
                (string) config('services.twilio.account_sid'),
                (string) config('services.twilio.auth_token'),
                (string) config('services.twilio.whatsapp.from'),
                (string) config('services.twilio.whatsapp.status_callback_url'),
            );
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
