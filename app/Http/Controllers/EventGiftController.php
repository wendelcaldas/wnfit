<?php

namespace App\Http\Controllers;

use App\Models\Event;
use App\Models\EventRegistration;
use App\Services\EventAccess;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class EventGiftController extends Controller
{
    public function show(Event $event)
    {
        $r = app(StudentEventController::class)->participant($event);
        abort_unless($event->moduleSettings()['gift']['enabled'], 404);
        EventAccess::ensureModulesAvailable(Auth::guard('student')->user(), $event);

        return response()->json(['gift' => $r->gift_snapshot, 'deliveredAt' => $r->gift_delivered_at]);
    }

    public function reveal(Event $event)
    {
        $r = app(StudentEventController::class)->participant($event);
        EventAccess::ensureModulesAvailable(Auth::guard('student')->user(), $event);

        return DB::transaction(function () use ($event, $r) {
            $event = Event::whereKey($event->id)->lockForUpdate()->firstOrFail();
            $r = EventRegistration::whereKey($r->id)->lockForUpdate()->firstOrFail();
            $settings = $event->moduleSettings()['gift'];
            abort_unless($settings['enabled'], 404);
            $this->assign($event, $r);

            return response()->json(['gift' => $r->gift_snapshot, 'deliveredAt' => $r->gift_delivered_at]);
        });
    }

    private function assign(Event $event, EventRegistration $r): void
    {
        if ($r->gift_snapshot) {
            return;
        }
        $settings = $event->moduleSettings()['gift'];
        abort_unless($event->status === 'published' && $settings['enabled'], 422, 'O presente não está disponível neste evento.');
        abort_if($event->registrations()->whereNotNull('gift_snapshot')->count() >= ($settings['quantity'] ?? 0), 422, 'Os presentes disponíveis já foram reservados. Procure a equipe.');
        $r->update(['gift_snapshot' => ['name' => $settings['name'], 'message' => $settings['message'] ?? '', 'instructions' => $settings['instructions'] ?? ''], 'gift_revealed_at' => now()]);
    }

    public function deliver(Request $request, Event $event, EventRegistration $registration)
    {
        EventAccess::staff($request, $event);
        abort_unless($registration->event_id === $event->id, 404);

        return DB::transaction(function () use ($request, $event, $registration) {
            $event = Event::whereKey($event->id)->lockForUpdate()->firstOrFail();
            $r = EventRegistration::whereKey($registration->id)->lockForUpdate()->firstOrFail();
            $this->assign($event, $r);
            if (! $r->gift_delivered_at) {
                $r->update(['gift_delivered_at' => now(), 'gift_delivered_by' => $request->user()->id]);
            }

            return response()->json(['deliveredAt' => $r->gift_delivered_at]);
        });
    }
}
