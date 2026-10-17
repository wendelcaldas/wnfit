<?php

namespace App\Http\Controllers;

use App\Models\Event;
use App\Models\EventRegistration;
use App\Services\EventAccess;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class EventGiftController extends Controller
{
    public function show(Event $event)
    {
        $r = app(StudentEventController::class)->participant($event);
        abort_unless($event->moduleSettings()['gift']['enabled'], 404);

        return response()->json(['gift' => $r->gift_snapshot, 'deliveredAt' => $r->gift_delivered_at]);
    }

    public function reveal(Event $event)
    {
        $r = app(StudentEventController::class)->participant($event);

        return DB::transaction(function () use ($event, $r) {
            $event = Event::whereKey($event->id)->lockForUpdate()->firstOrFail();
            $r = EventRegistration::whereKey($r->id)->lockForUpdate()->firstOrFail();
            $settings = $event->moduleSettings()['gift'];
            abort_unless($settings['enabled'], 404);
            if (! $r->gift_snapshot) {
                abort_if($event->registrations()->whereNotNull('gift_snapshot')->count() >= ($settings['quantity'] ?? 0), 422, 'Os presentes disponíveis já foram reservados. Procure a equipe.');
                $r->update(['gift_snapshot' => ['name' => $settings['name'], 'message' => $settings['message'] ?? '', 'instructions' => $settings['instructions'] ?? ''], 'gift_revealed_at' => now()]);
            }

            return response()->json(['gift' => $r->gift_snapshot, 'deliveredAt' => $r->gift_delivered_at]);
        });
    }

    public function deliver(Request $request, Event $event, EventRegistration $registration)
    {
        EventAccess::staff($request, $event);
        abort_unless($registration->event_id === $event->id, 404);

        return DB::transaction(function () use ($request, $registration) {
            $r = EventRegistration::whereKey($registration->id)->lockForUpdate()->firstOrFail();
            abort_unless($r->gift_snapshot, 422, 'O participante ainda não revelou seu presente.');
            if (! $r->gift_delivered_at) {
                $r->update(['gift_delivered_at' => now(), 'gift_delivered_by' => $request->user()->id]);
            }

            return response()->json(['deliveredAt' => $r->gift_delivered_at]);
        });
    }
}
