<?php

namespace App\Http\Controllers;

use App\Models\Event;
use App\Models\EventRegistration;
use App\Services\Messaging\MessagingService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class PublicEventController extends Controller
{
    private function visible(Request $request, Event $event): void
    {
        abort_unless($event->status !== 'draft' || $request->user()?->organizacoes()->where('organizacoes.id', $event->organizacao_id)->exists(), 404);
    }

    private function participant(Request $request, Event $event): ?EventRegistration
    {
        $token = $request->cookie('wnfit_event_'.$event->id);

        return $token ? $event->registrations()->where('access_hash', hash('sha256', $token))->first() : null;
    }

    private function cookie(Request $request, Event $event, string $token)
    {
        return cookie('wnfit_event_'.$event->id, $token, 60 * 24 * 90, '/api/public/events/'.$event->slug, null, $request->isSecure(), true, false, 'lax');
    }

    public function show(Request $request, Event $event)
    {
        $this->visible($request, $event);

        return response()->json(['event' => $event->publicPayload(), 'participant' => $this->participant($request, $event)?->participantPayload()])
            ->header('Cache-Control', 'no-store');
    }

    public function register(Request $request, Event $event, MessagingService $messaging)
    {
        $this->visible($request, $event);
        $data = $request->validate(['name' => 'required|string|min:2|max:120', 'phone' => 'required|string|max:30', 'privacy' => 'accepted', 'marketing' => 'sometimes|boolean']);
        $phone = $messaging->normalizeBrazilianPhone($data['phone']);
        $token = Str::random(64);
        $recovery = strtoupper(bin2hex(random_bytes(12)));
        $participant = DB::transaction(function () use ($event, $data, $phone, $token, $recovery) {
            $event = Event::query()->lockForUpdate()->findOrFail($event->id);
            abort_unless($event->status === 'published' && ! $event->closed_at, 422, 'As inscrições não estão abertas.');
            abort_if($event->registrations()->where('phone', $phone)->exists(), 409, 'Já existe uma inscrição com esse telefone. Use seu código para recuperar o acesso.');
            abort_if($event->capacity && $event->registrations()->count() >= $event->capacity, 422, 'As vagas deste evento foram preenchidas.');

            return $event->registrations()->create(['name' => trim($data['name']), 'phone' => $phone, 'access_hash' => hash('sha256', $token), 'recovery_hash' => hash('sha256', $recovery),
                'privacy_accepted_at' => now(), 'marketing_opt_in' => $data['marketing'] ?? false]);
        });

        return response()->json(['participant' => $participant->participantPayload(), 'recoveryCode' => implode('-', str_split($recovery, 4))], 201)
            ->cookie($this->cookie($request, $event, $token))->header('Cache-Control', 'no-store');
    }

    public function recover(Request $request, Event $event)
    {
        $this->visible($request, $event);
        $data = $request->validate(['code' => 'required|string|max:40']);
        $code = strtoupper(preg_replace('/[\s-]/', '', $data['code']));
        $token = Str::random(64);
        $participant = $event->registrations()->where('recovery_hash', hash('sha256', $code))->first();
        abort_unless($participant, 422, 'Código não encontrado para este evento. Confira os caracteres.');
        $participant->update(['access_hash' => hash('sha256', $token)]);

        return response()->json(['participant' => $participant->participantPayload()])->cookie($this->cookie($request, $event, $token))->header('Cache-Control', 'no-store');
    }

    public function checkin(Request $request, Event $event)
    {
        $this->visible($request, $event);
        $participant = $this->participant($request, $event);
        abort_unless($participant, 401, 'Recupere seu acesso para confirmar presença.');

        return DB::transaction(function () use ($event, $participant) {
            $event = Event::query()->lockForUpdate()->findOrFail($event->id);
            $participant = EventRegistration::query()->lockForUpdate()->findOrFail($participant->id);
            abort_unless($event->publicPayload()['checkinOpen'], 422, 'A confirmação de presença não está disponível neste horário.');
            abort_if($participant->attendance_source === 'staff_absent', 422, 'Procure a equipe para revisar sua presença.');
            if (! $participant->checked_in_at) {
                $participant->update(['checked_in_at' => now(), 'attendance_source' => 'self']);
            }

            return response()->json(['participant' => $participant->participantPayload()]);
        });
    }

    public function feedback(Request $request, Event $event)
    {
        $this->visible($request, $event);
        $participant = $this->participant($request, $event);
        abort_unless($participant, 401);
        abort_unless($event->status === 'published' && $event->closed_at && $participant->checked_in_at, 422, 'A avaliação estará disponível após o evento para quem confirmou presença.');
        $data = $request->validate(['rating' => 'required|integer|min:1|max:5', 'feedback' => 'nullable|string|max:1000']);
        $participant->update($data);

        return response()->json(['participant' => $participant->participantPayload()]);
    }
}
