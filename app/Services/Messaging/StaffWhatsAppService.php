<?php

namespace App\Services\Messaging;

use App\Jobs\SendStaffWhatsApp;
use App\Models\Organizacao;
use App\Models\StaffNotification;
use App\Models\StaffWhatsAppDelivery;
use Illuminate\Support\Carbon;

class StaffWhatsAppService
{
    public function recipient(int $organizationId): ?string
    {
        $targets = config('services.messaging.staff_whatsapp.targets', []);
        if (! is_array($targets) || empty($targets[$organizationId])) {
            return null;
        }
        $organization = Organizacao::find($organizationId);
        $owner = $organization?->usuarios()->wherePivot('papel', 'proprietario')->wherePivot('status', 'ativo')->first();
        if (! $owner) {
            return null;
        }
        $phone = preg_replace('/\D/', '', (string) ($owner->pivot->telefone ?? $organization->telefone_contato));
        if (strlen($phone) === 10 || strlen($phone) === 11) {
            $phone = '55'.$phone;
        }
        $phone = '+'.$phone;

        return is_string($phone) && preg_match('/^\+55[1-9]\d{9,10}$/', $phone) ? $phone : null;
    }

    public function enqueue(StaffNotification $notification): void
    {
        $phone = $this->recipient($notification->organizacao_id);
        if (! $phone || $notification->type !== 'workout_completed') {
            return;
        }
        $organization = Organizacao::whereKey($notification->organizacao_id)->where('ativa', true)->first();
        // Only a notification addressed to an active manager can trigger this channel.
        if (! $organization || ! $organization->usuarios()->whereKey($notification->user_id)
            ->wherePivot('status', 'ativo')->wherePivotIn('papel', ['proprietario', 'administrador'])->exists()) {
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
        $delivery = StaffWhatsAppDelivery::firstOrCreate([
            'event_key' => 'staff:'.$organization->id.':'.$notification->type.':'.$eventId,
        ], [
            'organizacao_id' => $organization->id, 'staff_notification_id' => $notification->id,
            'recipient' => $phone, 'body' => "WNFit · {$organization->nome_fantasia}\n{$text}\nVer no sistema: {$url}",
        ]);
        if ($delivery->wasRecentlyCreated) {
            SendStaffWhatsApp::dispatch($delivery->id)->onConnection('database')->onQueue('whatsapp')->afterCommit();
        }
    }
}
