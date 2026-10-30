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
        $phone = is_array($targets) ? ($targets[$organizationId] ?? null) : null;

        return is_string($phone) && preg_match('/^\+55[1-9]\d{9,10}$/', $phone) ? $phone : null;
    }

    public function enqueue(StaffNotification $notification): void
    {
        $phone = $this->recipient($notification->organizacao_id);
        if (! $phone || ! in_array($notification->type, ['workout_completed', 'workout_plan_expired'], true)) {
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
        if ($notification->type === 'workout_plan_expired' &&
            (empty($data['endsOn']) || Carbon::parse($data['endsOn'])->lt(Carbon::parse($since)->startOfDay()))) {
            return;
        }
        $eventId = $notification->type === 'workout_completed'
            ? $notification->student_workout_session_id : $notification->student_workout_plan_id;
        if (! $eventId) {
            return;
        }
        $student = $data['studentName'] ?? 'Aluno';
        $workout = $data['workoutName'] ?? 'Treino';
        $text = $notification->type === 'workout_completed'
            ? "Treino concluído: {$student} finalizou {$workout}."
            : "Ficha vencida: {$student} — {$workout}. Vigência até ".Carbon::parse($data['endsOn'])->format('d/m/Y').'.';
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
