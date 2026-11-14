<?php

namespace App\Services\Messaging;

use App\Jobs\SendEventWhatsApp;
use App\Models\Agendamento;
use App\Models\Aluno;
use App\Models\Cobranca;
use App\Models\Mensagem;
use App\Models\Organizacao;
use App\Models\StudentWorkoutPlan;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class AutomaticMessagingService
{
    public function __construct(private MessagingService $messaging) {}

    public function enabled(Organizacao $organization, string $type): bool
    {
        return config('services.messaging.events_enabled', false)
            && $organization->ativa && $organization->hasMessagingModule()
            && in_array((string) $organization->id, array_map('trim', config('services.messaging.baileys.organization_ids', [])), true)
            && ($this->messaging->settingsForOrganization($organization)->eventPreferences()[$type] ?? false);
    }

    public function workout(StudentWorkoutPlan $plan): ?Mensagem
    {
        $student = $plan->student;
        if (! $student || $plan->accessStatus() !== 'available') {
            return null;
        }

        return $this->queue($student->organizacao, 'treino_atualizado', 'workout:'.$plan->id, $student->telefone,
            EventMessageTemplates::workout($student->nome, $plan->snapshot['name'] ?? 'personalizado'),
            ['plan_id' => $plan->id], $student);
    }

    public function appointment(Agendamento $event, string $name, ?string $phone, string $recipientKey, array $source, ?Aluno $student = null): ?Mensagem
    {
        if ($event->status !== 'agendado' || $event->inicio_em->isPast()) {
            return null;
        }

        return $this->queue($event->organizacao, 'agendamento_confirmado', 'schedule:'.$event->id.':'.$recipientKey, $phone,
            EventMessageTemplates::appointment($name, $event->titulo, $event->inicio_em->format('d/m/Y'), $event->inicio_em->format('H:i')),
            ['schedule_id' => $event->id, 'starts_at' => $event->inicio_em->toISOString(), 'title' => $event->titulo, ...$source], $student);
    }

    public function studentAppointment(Agendamento $event, Aluno $student): ?Mensagem
    {
        if ($event->organizacao_id !== $student->organizacao_id) {
            return null;
        }

        return $this->appointment($event, $student->nome, $student->telefone, 'student:'.$student->id, ['student_id' => $student->id], $student);
    }

    public function syncBilling(): int
    {
        if (! config('services.messaging.events_enabled', false)) {
            return 0;
        }
        $count = 0;
        Cobranca::query()->whereIn('status', ['pendente', 'atrasado'])->where('origem', '!=', 'evento')
            ->whereBetween('vencimento', [today()->subDays(30), today()->addDays(2)])
            ->with(['organizacao', 'aluno'])->chunkById(100, function ($charges) use (&$count) {
                foreach ($charges as $charge) {
                    if (! $charge->aluno || $charge->pago_em || $charge->pagamento()->exists()) {
                        continue;
                    }
                    $delay = $this->messaging->settingsForOrganization($charge->organizacao)->overdue_delay_days;
                    $type = match ($charge->vencimento->toDateString()) {
                        today()->addDays(2)->toDateString() => 'vencimento_proximo',
                        today()->toDateString() => 'vencimento_hoje',
                        today()->subDays($delay)->toDateString() => 'cobranca_atrasada',
                        default => null,
                    };
                    if (! $type) {
                        continue;
                    }
                    $amount = 'R$ '.number_format((float) $charge->valor, 2, ',', '.');
                    $message = $this->queue($charge->organizacao, $type, 'charge:'.$charge->id.':'.$type.':'.$charge->vencimento->toDateString(),
                        $charge->aluno->telefone, EventMessageTemplates::billing($type, $charge->aluno->nome, $amount, $charge->vencimento->format('d/m/Y')),
                        ['due_date' => $charge->vencimento->toDateString(), 'amount' => (string) $charge->valor], $charge->aluno, $charge);
                    $count += (int) ($message?->wasRecentlyCreated ?? false);
                }
            });

        return $count;
    }

    private function queue(Organizacao $organization, string $type, string $key, ?string $phone, string $body, array $source, ?Aluno $student = null, ?Cobranca $charge = null): ?Mensagem
    {
        if (! $this->enabled($organization, $type) || ($student && $student->status !== 'ativo')) {
            return null;
        }
        try {
            $to = $this->messaging->normalizeBrazilianPhone($phone);
        } catch (ValidationException) {
            return null;
        }

        return DB::transaction(function () use ($organization, $type, $key, $to, $body, $source, $student, $charge) {
            $message = Mensagem::firstOrCreate(['event_key' => 'event:'.$organization->id.':'.$key], [
                'organizacao_id' => $organization->id, 'aluno_id' => $student?->id, 'cobranca_id' => $charge?->id,
                'canal' => 'whatsapp', 'provedor' => 'baileys', 'tipo' => $type, 'status' => 'pendente',
                'destinatario' => $to, 'conteudo' => $body, 'payload' => $source,
            ]);
            if ($message->wasRecentlyCreated) {
                SendEventWhatsApp::dispatch($message->id)->onConnection('database')->onQueue('whatsapp')->afterCommit();
            }

            return $message;
        });
    }

    public function valid(Mensagem $message): bool
    {
        if (! $message->organizacao || ! $this->enabled($message->organizacao, $message->tipo)) {
            return false;
        }
        $source = $message->payload ?? [];
        $student = $message->aluno;
        if ($message->aluno_id && (! $student || $student->status !== 'ativo' || $student->organizacao_id !== $message->organizacao_id)) {
            return false;
        }
        if ($student && $this->messaging->normalizeBrazilianPhone($student->telefone) !== $message->destinatario) {
            return false;
        }
        if ($message->cobranca_id) {
            $charge = $message->cobranca;
            $delay = $this->messaging->settingsForOrganization($message->organizacao)->overdue_delay_days;
            $date = match ($message->tipo) {
                'vencimento_proximo' => today()->addDays(2),
                'vencimento_hoje' => today(),
                default => today()->subDays($delay),
            };

            return $charge && $charge->organizacao_id === $message->organizacao_id && $charge->aluno_id === $student?->id
                && $charge->origem !== 'evento' && in_array($charge->status, ['pendente', 'atrasado'], true)
                && ! $charge->pago_em && ! $charge->pagamento()->exists()
                && $charge->vencimento->isSameDay($date) && $charge->vencimento->toDateString() === $source['due_date']
                && (string) $charge->valor === $source['amount'];
        }
        if (isset($source['plan_id'])) {
            $plan = StudentWorkoutPlan::find($source['plan_id']);

            return $plan && $plan->aluno_id === $student?->id && $plan->accessStatus() === 'available'
                && ! StudentWorkoutPlan::where('aluno_id', $student->id)->where('id', '>', $plan->id)->exists();
        }
        $event = Agendamento::find($source['schedule_id'] ?? null);
        if (! $event || $event->organizacao_id !== $message->organizacao_id || $event->status !== 'agendado'
            || $event->inicio_em->isPast() || $event->titulo !== ($source['title'] ?? null)
            || $event->inicio_em->toISOString() !== ($source['starts_at'] ?? null)) {
            return false;
        }
        if ($student) {
            return $event->alunos()->whereKey($student->id)->wherePivot('status', 'confirmado')->exists();
        }
        $booking = isset($source['booking_id']) ? $event->publicBooking()->whereKey($source['booking_id'])->first()
            : $event->publicReservations()->whereKey($source['reservation_id'] ?? null)->first();

        return $booking && $this->messaging->normalizeBrazilianPhone($booking->phone) === $message->destinatario;
    }
}
