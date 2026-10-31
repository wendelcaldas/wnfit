<?php

namespace App\Services\Messaging;

use App\Jobs\SendStaffWhatsApp;
use App\Models\Organizacao;
use App\Models\StaffNotification;
use App\Models\StaffWhatsAppDelivery;
use App\Rules\BrazilianPhone;
use Illuminate\Support\Carbon;

class StaffWhatsAppService
{
    public function recipients(int $organizationId): array
    {
        $targets = config('services.messaging.staff_whatsapp.targets', []);
        if (! is_array($targets) || empty($targets[$organizationId])) {
            return [];
        }
        $organization = Organizacao::whereKey($organizationId)->where('ativa', true)->first();
        if (! $organization) {
            return [];
        }
        $phones = [];
        foreach ($organization->usuarios()->wherePivot('papel', 'proprietario')->wherePivot('status', 'ativo')->orderBy('users.id')->get() as $owner) {
            // A missing individual number must never fall back to another owner's contact.
            $phone = $owner->pivot->telefone;
            if (BrazilianPhone::valid($phone)) {
                $phones[] = '+55'.BrazilianPhone::digits($phone);
            }
        }

        return array_values(array_unique($phones));
    }

    public function enqueue(StaffNotification $notification): void
    {
        $phones = $this->recipients($notification->organizacao_id);
        if (! $phones || $notification->type !== 'workout_completed') {
            return;
        }
        $organization = Organizacao::whereKey($notification->organizacao_id)->where('ativa', true)->first();
        // The WhatsApp channel initially includes only active owners.
        if (! $organization || ! $organization->usuarios()->whereKey($notification->user_id)
            ->wherePivot('status', 'ativo')->wherePivot('papel', 'proprietario')->exists()) {
            return;
        }
        $data = $notification->data;
        $since = config('services.messaging.staff_whatsapp.since');
        if (! $since || $notification->created_at->lt(Carbon::parse($since))) {
            return;
        }
        $eventId = $notification->student_workout_session_id;
        if (! $eventId) {
            return;
        }
        $student = $data['studentName'] ?? 'Aluno';
        $workout = $data['workoutName'] ?? 'Treino';
        $text = "Treino concluído: {$student} finalizou {$workout}.";
        // Build our own path; notification data cannot inject an external link.
        $url = rtrim(config('app.url'), '/').'/alunos/'.(int) ($data['studentId'] ?? 0).'?tab=workouts';
        $baseKey = 'staff:'.$organization->id.':'.$notification->type.':'.$eventId;
        $multiple = config('services.messaging.staff_whatsapp.multiple_owners', true);
        $legacy = StaffWhatsAppDelivery::where('event_key', $baseKey)->first();
        foreach ($multiple ? $phones : array_slice($phones, 0, 1) as $phone) {
            // Preserve the old idempotency reservation during the rollout, in any state.
            if ($multiple && $legacy?->recipient === $phone) {
                continue;
            }
            $delivery = StaffWhatsAppDelivery::firstOrCreate([
                'event_key' => $multiple ? $baseKey.':to:'.substr(hash('sha256', $phone), 0, 16) : $baseKey,
            ], [
                'organizacao_id' => $organization->id, 'staff_notification_id' => $notification->id,
                'recipient' => $phone, 'body' => "WNFit · {$organization->nome_fantasia}\n{$text}\nVer no sistema: {$url}",
            ]);
            if ($delivery->wasRecentlyCreated) {
                SendStaffWhatsApp::dispatch($delivery->id)->onConnection('database')->onQueue('whatsapp')->afterCommit();
            }
        }
    }
}
