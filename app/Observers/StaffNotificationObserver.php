<?php

namespace App\Observers;

use App\Events\StaffNotificationChanged;
use App\Models\StaffNotification;
use Illuminate\Support\Facades\DB;
use Throwable;

class StaffNotificationObserver
{
    public function created(StaffNotification $notification): void
    {
        $this->publish($notification, 'created');
    }

    public function updated(StaffNotification $notification): void
    {
        if ($notification->wasChanged('read_at')) {
            $this->publish($notification, 'read');
        }
    }

    private function publish(StaffNotification $notification, string $action): void
    {
        $event = new StaffNotificationChanged($action, $notification->organizacao_id, $notification->user_id, $notification->payload());
        DB::afterCommit(function () use ($event) {
            try {
                event($event);
            } catch (Throwable $error) {
                report($error);
            }
        });
    }
}
