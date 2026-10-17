<?php

namespace App\Services;

use App\Models\Event;
use App\Models\EventRegistration;
use Illuminate\Support\Facades\DB;

class EventFeedbackService
{
    public function save(Event $event, EventRegistration $registration, array $data): EventRegistration
    {
        return DB::transaction(function () use ($event, $registration, $data) {
            $event = Event::whereKey($event->id)->lockForUpdate()->firstOrFail();
            $registration = EventRegistration::whereKey($registration->id)->lockForUpdate()->firstOrFail();
            abort_unless($registration->event_id === $event->id, 404);
            abort_unless($event->moduleSettings()['feedback']['enabled'] && $event->status === 'published' && $event->closed_at && $registration->checked_in_at, 422, 'A avaliação estará disponível após o evento para quem confirmou presença.');
            $registration->update($data);

            return $registration;
        });
    }
}
