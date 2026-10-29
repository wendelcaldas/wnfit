<?php

namespace App\Services;

use App\Models\Agendamento;
use App\Models\Organizacao;
use App\Models\OrganizationBookingLink;
use App\Models\OrganizationScheduleSetting;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;

class ScheduleAvailabilityService
{
    public function settings(int $org): OrganizationScheduleSetting
    {
        return OrganizationScheduleSetting::where('organizacao_id', $org)->first() ?? new OrganizationScheduleSetting([
            'organizacao_id' => $org, 'enforced' => false, 'weekly_hours' => array_fill(0, 7, []), 'public_enabled' => false, 'duration_minutes' => 60, 'title' => 'Atendimento individual',
        ]);
    }

    public function hoursPayload(OrganizationScheduleSetting $s): array
    {
        return ['enforced' => $s->enforced, 'weeklyHours' => $s->weekly_hours, 'timezone' => config('app.timezone')];
    }

    public function isOpen(OrganizationScheduleSetting $s, Carbon $start, Carbon $end): bool
    {
        if (! $s->enforced) {
            return true;
        }
        $start = $start->copy()->setTimezone(config('app.timezone'));
        $end = $end->copy()->setTimezone(config('app.timezone'));
        if ($end->lte($start) || ! $start->isSameDay($end)) {
            return false;
        }
        foreach (($s->weekly_hours[$start->dayOfWeek] ?? []) as $range) {
            $opens = $start->copy()->setTimeFromTimeString($range['start']);
            $closes = $start->copy()->setTimeFromTimeString($range['end']);
            if ($start->gte($opens) && $end->lte($closes)) {
                return true;
            }
        }

        return false;
    }

    public function assertOpen(int $org, Carbon $start, Carbon $end): void
    {
        if (! $this->isOpen($this->settings($org), $start, $end)) {
            throw ValidationException::withMessages(['starts_at' => 'Este horário está fora do funcionamento configurado. Escolha um horário aberto na agenda.']);
        }
    }

    public function instructorActive(OrganizationScheduleSetting $s): bool
    {
        return $s->instructor_id && Organizacao::find($s->organizacao_id)?->usuarios()->whereKey($s->instructor_id)->wherePivot('status', 'ativo')->wherePivotIn('papel', ['proprietario', 'administrador', 'professor'])->exists();
    }

    public function publicSettings(string $token): OrganizationScheduleSetting
    {
        abort_unless(preg_match('/^[a-zA-Z0-9]{64}$/', $token), 404);
        $link = OrganizationBookingLink::where('public_token', $token)->where('active', true)->firstOrFail();
        $s = $this->settings($link->organizacao_id);
        abort_unless($s->enforced, 404);
        $s->fill(['booking_link_id' => $link->id, 'title' => $link->title, 'duration_minutes' => $link->duration_minutes, 'instructor_id' => $link->instructor_id, 'location' => $link->location, 'public_token' => $link->public_token]);
        abort_unless(Organizacao::whereKey($s->organizacao_id)->where('ativa', true)->exists() && $this->instructorActive($s), 404);

        return $s;
    }

    public function conflict(OrganizationScheduleSetting $s, Carbon $start, Carbon $end): bool
    {
        return Agendamento::where('organizacao_id', $s->organizacao_id)->where('status', '!=', 'cancelado')
            ->where(fn ($q) => $q->where('instrutor_id', $s->instructor_id)->orWhereNull('instrutor_id'))
            ->where('inicio_em', '<', $end)->where('fim_em', '>', $start)->exists();
    }

    public function slots(OrganizationScheduleSetting $s, string $date): array
    {
        $day = Carbon::createFromFormat('!Y-m-d', $date, config('app.timezone'));
        if ($day->lt(today()) || $day->gt(today()->addDays(60))) {
            return [];
        }
        $slots = [];
        foreach (($s->weekly_hours[$day->dayOfWeek] ?? []) as $range) {
            $cursor = $day->copy()->setTimeFromTimeString($range['start']);
            $close = $day->copy()->setTimeFromTimeString($range['end']);
            while (($end = $cursor->copy()->addMinutes($s->duration_minutes))->lte($close)) {
                if ($cursor->gte(now()->addMinutes(30)) && ! $this->conflict($s, $cursor, $end)) {
                    $slots[] = ['startsAt' => $cursor->toIso8601String(), 'endsAt' => $end->toIso8601String(), 'label' => $cursor->format('H:i')];
                }
                $cursor = $end;
            }
        }

        return $slots;
    }
}
