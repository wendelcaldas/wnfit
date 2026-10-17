<?php

namespace App\Http\Controllers;

use App\Models\Event;
use App\Models\EventRegistration;
use App\Services\EventFeedbackService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class StudentEventController extends Controller
{
    public function participant(Event $event): EventRegistration
    {
        $student = Auth::guard('student')->user()->aluno;
        abort_unless($event->organizacao_id === $student->organizacao_id && $event->status === 'published', 404);

        return $event->registrations()->where('aluno_id', $student->id)->firstOrFail();
    }

    public function index()
    {
        $student = Auth::guard('student')->user()->aluno;

        return Event::where('organizacao_id', $student->organizacao_id)->where('status', 'published')->whereHas('registrations', fn ($q) => $q->where('aluno_id', $student->id))->orderByDesc('starts_at')->get()->map(fn ($e) => $e->publicPayload());
    }

    public function show(Event $event)
    {
        $participant = $this->participant($event);

        return response()->json(['event' => $event->publicPayload(), 'participant' => $participant->participantPayload(), 'welcomed' => (bool) $participant->welcomed_at]);
    }

    public function welcome(Event $event)
    {
        $participant = $this->participant($event);
        $participant->whereKey($participant->id)->whereNull('welcomed_at')->update(['welcomed_at' => now()]);

        return response()->json(['ok' => true]);
    }

    public function feedback(Request $request, Event $event)
    {
        $registration = $this->participant($event);
        $data = $request->validate(['rating' => 'required|integer|min:1|max:5', 'feedback' => 'nullable|string|max:1000']);
        $registration = app(EventFeedbackService::class)->save($event, $registration, $data);

        return response()->json(['participant' => $registration->participantPayload()]);
    }

    public function checkin(Request $request, Event $event)
    {
        $participant = $this->participant($event);

        return DB::transaction(function () use ($participant, $event) {
            $event = Event::whereKey($event->id)->lockForUpdate()->firstOrFail();
            $participant = EventRegistration::whereKey($participant->id)->lockForUpdate()->firstOrFail();
            abort_unless($event->publicPayload()['checkinOpen'], 422, 'A presença ainda não está disponível.');
            abort_if($participant->attendance_source === 'staff_absent', 422, 'Procure a equipe para revisar sua presença.');
            if (! $participant->checked_in_at) {
                $participant->update(['checked_in_at' => now(), 'attendance_source' => 'self']);
            }

            return response()->json(['participant' => $participant->participantPayload()]);
        });
    }
}
