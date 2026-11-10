<?php

namespace App\Services;

use App\Models\Agendamento;
use App\Models\Aluno;
use App\Models\Organizacao;
use App\Models\PublicAppointmentReservation;
use App\Models\PublicScheduleBooking;
use App\Models\StaffNotification;

class PublicBookingNotificationService
{
    public function created(Agendamento $event, PublicScheduleBooking|PublicAppointmentReservation $booking, string $appointmentTitle): void
    {
        $this->notifyStaff($event, $appointmentTitle, ['guestName' => $booking->name, 'phone' => $booking->phone]);
    }

    public function studentBooked(Agendamento $event, Aluno $student): void
    {
        $this->notifyStaff($event, $event->titulo, [
            'guestName' => $student->nome,
            'phone' => $student->telefone,
            'source' => 'student_portal',
        ]);
    }

    private function notifyStaff(Agendamento $event, string $appointmentTitle, array $contact): void
    {
        $organization = Organizacao::findOrFail($event->organizacao_id);
        $members = $organization->usuarios()->wherePivot('status', 'ativo')->get();
        $recipients = $members->filter(fn ($user) => in_array($user->pivot->papel, ['proprietario', 'administrador'], true) || ($user->pivot->papel === 'professor' && $user->id === $event->instrutor_id));
        $data = [
            ...$contact,
            'appointmentTitle' => $appointmentTitle,
            'startsAt' => $event->inicio_em->toIso8601String(),
            'endsAt' => $event->fim_em->toIso8601String(),
            'instructorName' => $members->firstWhere('id', $event->instrutor_id)?->name,
            'location' => $event->local,
            'scheduleId' => $event->id,
            'href' => '/agenda?date='.$event->inicio_em->toDateString().'&event='.$event->id,
        ];
        foreach ($recipients->unique('id') as $recipient) {
            StaffNotification::create(['organizacao_id' => $organization->id, 'user_id' => $recipient->id, 'type' => 'public_booking_created', 'data' => $data]);
        }
    }
}
