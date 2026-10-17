<?php

namespace App\Http\Controllers;

use App\Models\Event;
use App\Models\EventRegistration;
use App\Services\EventFeedbackService;
use App\Services\EventRegistrationService;
use App\Services\Messaging\MessagingService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
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
        $account = Auth::guard('student')->user();
        if ($account && $request->session()->get('student_credential_version') === $account->credential_version && ! $account->must_change_password && $account->aluno?->organizacao?->ativa && $account->aluno->organizacao_id === $event->organizacao_id) {
            $registration = $event->registrations()->where('aluno_id', $account->aluno_id)->first();
            if ($registration) {
                return $registration;
            }
        }
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

        $account = Auth::guard('student')->user();
        $student = $account && $request->session()->get('student_credential_version') === $account->credential_version && ! $account->must_change_password && $account->aluno?->organizacao?->ativa && $account->aluno->organizacao_id === $event->organizacao_id ? $account->aluno : null;

        return response()->json(['student' => $student ? ['name' => $student->nome, 'phone' => $student->telefone, 'eligible' => $event->audience === 'open' || $student->perfil_tipo === 'aluno'] : null, 'event' => $event->publicPayload(), 'participant' => $this->participant($request, $event)?->participantPayload()])
            ->header('Cache-Control', 'no-store');
    }

    public function register(Request $request, Event $event, MessagingService $messaging)
    {
        $this->visible($request, $event);
        $data = $request->validate(['name' => 'required|string|min:2|max:120', 'phone' => 'required|string|max:30', 'privacy' => 'accepted', 'marketing' => 'sometimes|boolean']);
        $phone = $messaging->normalizeBrazilianPhone($data['phone']);
        [$participant, $token, $recovery] = app(EventRegistrationService::class)->register($event, null, [...$data, 'phone' => $phone], 'public');

        return response()->json(['participant' => $participant->participantPayload(), 'recoveryCode' => implode('-', str_split($recovery, 4))], 201)
            ->cookie($this->cookie($request, $event, $token))->header('Cache-Control', 'no-store');
    }

    public function studentRegister(Request $request, Event $event)
    {
        $request->validate(['privacy' => 'accepted']);
        $student = Auth::guard('student')->user()->aluno;
        [$registration] = app(EventRegistrationService::class)->register($event, $student, $request->all(), 'student');

        return response()->json(['participant' => $registration->participantPayload()]);
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
        $data = $request->validate(['rating' => 'required|integer|min:1|max:5', 'feedback' => 'nullable|string|max:1000']);
        $participant = app(EventFeedbackService::class)->save($event, $participant, $data);

        return response()->json(['participant' => $participant->participantPayload()]);
    }
}
