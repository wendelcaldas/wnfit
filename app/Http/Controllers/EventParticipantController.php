<?php

namespace App\Http\Controllers;

use App\Models\Aluno;
use App\Models\Event;
use App\Models\EventRegistration;
use App\Models\Organizacao;
use App\Models\StudentAccount;
use App\Services\Messaging\MessagingService;
use App\Services\StudentAccessService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class EventParticipantController extends Controller
{
    private function authorizeEvent(Request $request, Event $event, EventRegistration $registration): Organizacao
    {
        $organization = $request->user()->organizacoes()->wherePivot('status', 'ativo')->firstOrFail();
        abort_unless(in_array($organization->pivot->papel, ['proprietario', 'administrador']), 403);
        abort_unless($event->organizacao_id === $organization->id && $registration->event_id === $event->id, 404);

        return $organization;
    }

    private function phoneMatches(int $organizationId, ?string $phone): array
    {
        if (! $phone) {
            return [];
        }
        $messaging = app(MessagingService::class);
        $ids = [];
        foreach (Aluno::where('organizacao_id', $organizationId)->whereNotNull('telefone')->select(['id', 'telefone'])->cursor() as $student) {
            try {
                if ($messaging->normalizeBrazilianPhone($student->telefone) === $phone) {
                    $ids[] = $student->id;
                }
            } catch (ValidationException) {
                // Older records may contain incomplete numbers; they remain searchable by name.
            }
        }

        return $ids;
    }

    public function candidates(Request $request, Event $event, EventRegistration $registration)
    {
        $organization = $this->authorizeEvent($request, $event, $registration);
        $data = $request->validate(['q' => 'nullable|string|max:120']);
        $matches = $this->phoneMatches($organization->id, $registration->phone);
        $query = Aluno::where('organizacao_id', $organization->id);
        if (trim($data['q'] ?? '') !== '') {
            $term = trim($data['q']);
            $phoneIds = [];
            try {
                $phoneIds = $this->phoneMatches($organization->id, app(MessagingService::class)->normalizeBrazilianPhone($term));
            } catch (ValidationException) {
            }
            $query->where(fn ($q) => $q->where('nome', 'like', '%'.$term.'%')->orWhere('telefone', 'like', '%'.$term.'%')->orWhereIn('id', $phoneIds));
        }
        $students = $query->orderByRaw('CASE WHEN id IN ('.(implode(',', array_map('intval', $matches)) ?: '0').') THEN 0 ELSE 1 END')->orderBy('nome')->paginate(15);
        $accountNames = StudentAccount::whereIn('aluno_id', $students->pluck('id'))->pluck('username', 'aluno_id');
        $linked = $event->registrations()->whereNotNull('aluno_id')->pluck('id', 'aluno_id');
        $students->through(fn ($student) => [
            'id' => $student->id, 'name' => $student->nome, 'phone' => $student->telefone,
            'username' => $accountNames[$student->id] ?? null,
            'phoneMatch' => in_array($student->id, $matches, true),
            'linkedToAnotherParticipant' => isset($linked[$student->id]) && $linked[$student->id] !== $registration->id,
        ]);

        return response()->json($students)->header('Cache-Control', 'no-store, private');
    }

    public function access(Request $request, Event $event, EventRegistration $registration, StudentAccessService $access)
    {
        $organization = $this->authorizeEvent($request, $event, $registration);
        $data = $request->validate([
            'mode' => 'required|in:link,create',
            'student_id' => 'required_if:mode,link|prohibited_if:mode,create|integer',
        ]);

        return DB::transaction(function () use ($organization, $event, $registration, $data, $access) {
            // Serialize creation across events too, so a retry cannot create a second student.
            Organizacao::whereKey($organization->id)->lockForUpdate()->firstOrFail();
            $event = Event::whereKey($event->id)->lockForUpdate()->firstOrFail();
            $registration = EventRegistration::whereKey($registration->id)->lockForUpdate()->firstOrFail();
            abort_if($event->status === 'cancelled', 422, 'Este evento está cancelado.');
            if ($registration->aluno_id) {
                abort_if($data['mode'] === 'link' && $registration->aluno_id !== (int) $data['student_id'], 422, 'Este participante já está vinculado a outro aluno.');
                $student = Aluno::where('organizacao_id', $organization->id)->findOrFail($registration->aluno_id);
            } elseif ($data['mode'] === 'link') {
                $student = Aluno::where('organizacao_id', $organization->id)->lockForUpdate()->findOrFail($data['student_id']);
            } else {
                abort_if(count($this->phoneMatches($organization->id, $registration->phone)) > 0, 422, 'Já existe aluno com este telefone. Confira os cadastros e vincule o aluno existente.');
                $student = Aluno::create([
                    'organizacao_id' => $organization->id, 'nome' => $registration->name,
                    'telefone' => $registration->phone, 'plano' => 'Participante de evento', 'status' => 'participante', 'perfil_tipo' => 'participante',
                ]);
            }
            abort_if($event->registrations()->where('aluno_id', $student->id)->where('id', '!=', $registration->id)->exists(), 422, 'Este aluno já está vinculado a outro participante deste evento.');
            $registration->update(['aluno_id' => $student->id]);
            $account = StudentAccount::where('aluno_id', $student->id)->first();
            $credentials = $account ? null : $access->issue($student);
            $account ??= StudentAccount::where('aluno_id', $student->id)->firstOrFail();

            return response()->json([
                'student' => ['id' => $student->id, 'name' => $student->nome, 'username' => $account->username],
                'credentials' => $credentials,
                'message' => $credentials ? 'Acesso criado. Guarde a senha provisória para enviar individualmente.' : 'Aluno vinculado. O acesso e a senha atuais foram preservados.',
            ])->header('Cache-Control', 'no-store, private');
        });
    }
}
