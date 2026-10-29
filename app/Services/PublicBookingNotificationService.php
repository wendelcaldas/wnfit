<?php

namespace App\Services;

use App\Models\Agendamento;
use App\Models\Organizacao;
use App\Models\PublicScheduleBooking;
use App\Models\StaffNotification;

class PublicBookingNotificationService
{
    public function created(Agendamento $event, PublicScheduleBooking $booking, string $appointmentTitle): void
    {
        $organization = Organizacao::findOrFail($event->organizacao_id);
        $members = $organization->usuarios()->wherePivot('status', 'ativo')->get();
        $recipients = $members->filter(fn ($user) => in_array($user->pivot->papel, ['proprietario', 'administrador'], true) || ($user->pivot->papel === 'professor' && $user->id === $event->instrutor_id));
        $data = ['guestName' => $booking->name, 'appointmentTitle' => $appointmentTitle, 'startsAt' => $event->inicio_em->toIso8601String(), 'endsAt' => $event->fim_em->toIso8601String(), 'phone' => $booking->phone, 'instructorName' => $members->firstWhere('id', $event->instrutor_id)?->name, 'location' => $event->local, 'scheduleId' => $event->id, 'href' => '/agenda?date='.$event->inicio_em->toDateString().'&event='.$event->id];
        foreach ($recipients->unique('id') as $recipient) {
            StaffNotification::create(['organizacao_id' => $organization->id, 'user_id' => $recipient->id, 'type' => 'public_booking_created', 'data' => $data]);
        }
    }
}
