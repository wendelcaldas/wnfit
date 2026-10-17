<?php

namespace App\Services;

use App\Models\Aluno;
use App\Models\Event;
use App\Services\Messaging\MessagingService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class EventRegistrationService
{
    public function matchingStudents(int $organizationId, ?string $phone): array
    {
        if (! $phone) {
            return [];
        }
        $normalized = app(MessagingService::class)->normalizeBrazilianPhone($phone);
        $ids = [];
        foreach (Aluno::where('organizacao_id', $organizationId)->whereNotNull('telefone')->select(['id', 'telefone'])->cursor() as $student) {
            try {
                if (app(MessagingService::class)->normalizeBrazilianPhone($student->telefone) === $normalized) {
                    $ids[] = $student->id;
                }
            } catch (ValidationException) {
            }
        }

        return $ids;
    }

    public function register(Event $event, ?Aluno $student, array $data, string $source, ?int $staffId = null): array
    {
        return DB::transaction(function () use ($event, $student, $data, $source, $staffId) {
            DB::table('organizacoes')->where('id', $event->organizacao_id)->lockForUpdate()->first();
            $event = Event::whereKey($event->id)->lockForUpdate()->firstOrFail();
            abort_unless($event->status === 'published' && ! $event->closed_at, 422, 'As inscrições não estão abertas.');
            $key = $source === 'staff' ? ($data['request_id'] ?? null) : null;
            if ($key && ($existing = $event->registrations()->where('enrollment_key', $key)->first())) {
                abort_if($student ? $existing->aluno_id !== $student->id : ($existing->name !== $data['name'] || $existing->phone !== ($data['phone'] ?? null)), 409, 'Esta solicitação já foi usada para outro participante. Abra uma nova inclusão.');

                return [$existing, null, null];
            }
            if ($student) {
                abort_unless($student->organizacao_id === $event->organizacao_id, 404);
                abort_if($event->audience === 'students' && $student->perfil_tipo !== 'aluno', 422, 'Este evento é exclusivo para alunos.');
                $existing = $event->registrations()->where('aluno_id', $student->id)->first();
                if ($existing) {
                    if ($source === 'student' && ! $existing->privacy_accepted_at) {
                        $existing->update(['privacy_accepted_at' => now()]);
                    }

                    return [$existing, null, null];
                }
                if ($student->telefone) {
                    try {
                        $phone = app(MessagingService::class)->normalizeBrazilianPhone($student->telefone);
                    } catch (ValidationException) {
                        $phone = null;
                    }
                    abort_if($phone && $event->registrations()->whereNull('aluno_id')->where('phone', $phone)->exists(), 422, 'Uma inscrição existente precisa ser conferida pela equipe antes de continuar.');
                }
            } else {
                abort_unless($event->audience === 'open', 422, 'Este evento é exclusivo para alunos. Entre com sua conta ou procure a equipe.');
                abort_if(count($this->matchingStudents($event->organizacao_id, $data['phone'] ?? null)) > 0 && ! ($source === 'staff' && ($data['allow_shared_phone'] ?? false)), 422, 'Entre com sua conta ou procure a equipe para concluir sua inscrição.');
                abort_if(! empty($data['phone']) && $event->registrations()->where('phone', $data['phone'])->exists() && ! ($source === 'staff' && ($data['allow_shared_phone'] ?? false)), 422, 'Entre com sua conta ou recupere sua inscrição com a equipe.');
            }
            abort_if($event->capacity && $event->registrations()->count() >= $event->capacity, 422, 'As vagas deste evento foram preenchidas.');
            $gift = $event->moduleSettings()['gift'];
            abort_if($gift['enabled'] && $event->registrations()->count() >= ($gift['quantity'] ?? 0), 422, 'O limite de participantes com presente foi atingido. Procure a equipe.');
            $student ??= Aluno::create(['organizacao_id' => $event->organizacao_id, 'nome' => $data['name'], 'telefone' => $data['phone'] ?? null, 'perfil_tipo' => 'participante', 'status' => 'participante', 'plano' => 'Participante de evento']);
            $token = Str::random(64);
            $recovery = strtoupper(bin2hex(random_bytes(12)));
            $registration = $event->registrations()->create([
                'aluno_id' => $student->id, 'name' => $student->nome, 'phone' => $student->telefone,
                'enrollment_key' => $key, 'source' => $source, 'created_by' => $staffId,
                'access_hash' => hash('sha256', $token), 'recovery_hash' => hash('sha256', $recovery),
                'privacy_accepted_at' => $source === 'staff' ? null : now(),
                'marketing_opt_in' => $source === 'staff' ? false : ($data['marketing'] ?? false),
            ]);

            app(EventBillingService::class)->create($event, $registration);

            return [$registration, $token, $recovery];
        });
    }
}
