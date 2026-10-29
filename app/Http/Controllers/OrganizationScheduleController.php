<?php

namespace App\Http\Controllers;

use App\Models\Organizacao;
use App\Models\OrganizationBookingLink;
use App\Models\OrganizationScheduleSetting;
use App\Services\ScheduleAvailabilityService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class OrganizationScheduleController extends Controller
{
    private function context(Request $r): array
    {
        $org = $r->user()->organizacoes()->where('organizacoes.ativa', true)->wherePivot('status', 'ativo')->firstOrFail();
        $role = $org->usuarios()->whereKey($r->user()->id)->firstOrFail()->pivot->papel;

        return [$org, in_array($role, ['proprietario', 'administrador'], true)];
    }

    private function payload($org, bool $manage, ScheduleAvailabilityService $availability): array
    {
        $s = $availability->settings($org->id);

        return ['canManage' => $manage, 'settings' => [...$availability->hoursPayload($s), 'publicEnabled' => $s->public_enabled, 'durationMinutes' => $s->duration_minutes,
            'instructorId' => $s->instructor_id, 'title' => $s->title, 'location' => $s->location, 'publicToken' => $s->public_token,
            'publicUrl' => $s->public_token ? '/agendar/'.$s->public_token : null,
            'bookingLinks' => OrganizationBookingLink::where('organizacao_id', $org->id)->orderBy('id')->get()->map(fn ($link) => ['id' => $link->id, 'title' => $link->title, 'durationMinutes' => $link->duration_minutes, 'instructorId' => $link->instructor_id, 'location' => $link->location, 'active' => $link->active, 'publicToken' => $link->public_token, 'publicUrl' => '/agendar/'.$link->public_token])],
            'instructors' => $org->usuarios()->wherePivot('status', 'ativo')->wherePivotIn('papel', ['proprietario', 'administrador', 'professor'])->orderBy('name')->get(['users.id', 'users.name'])->map(fn ($u) => ['id' => $u->id, 'name' => $u->name])];
    }

    public function show(Request $r, ScheduleAvailabilityService $availability)
    {
        [$org,$manage] = $this->context($r);

        return response()->json($this->payload($org, $manage, $availability));
    }

    public function save(Request $r, ScheduleAvailabilityService $availability)
    {
        [$org,$manage] = $this->context($r);
        abort_unless($manage, 403);
        $legacy = $r->has('publicEnabled');
        $defaults = $availability->settings($org->id);
        $r->mergeIfMissing(['publicEnabled' => $defaults->public_enabled, 'durationMinutes' => $defaults->duration_minutes, 'instructorId' => $defaults->instructor_id, 'title' => $defaults->title, 'location' => $defaults->location]);
        $data = $r->validate(['enforced' => 'required|boolean', 'weeklyHours' => 'required|array:0,1,2,3,4,5,6', 'weeklyHours.*' => 'present|array|max:3',
            'weeklyHours.*.*' => 'required|array:start,end', 'weeklyHours.*.*.start' => 'required|date_format:H:i', 'weeklyHours.*.*.end' => 'required|date_format:H:i',
            'publicEnabled' => 'required|boolean', 'durationMinutes' => ['required', 'integer', Rule::in([15, 30, 45, 60, 90, 120])],
            'instructorId' => ['nullable', 'integer'], 'title' => 'required|string|max:120', 'location' => 'nullable|string|max:120']);
        $hours = [];
        $hasHours = false;
        $slotFits = false;
        for ($day = 0; $day < 7; $day++) {
            $ranges = $data['weeklyHours'][$day] ?? [];
            usort($ranges, fn ($a, $b) => strcmp($a['start'], $b['start']));
            $lastEnd = null;
            foreach ($ranges as $range) {
                if ($range['start'] >= $range['end'] || ($lastEnd !== null && $range['start'] < $lastEnd)) {
                    throw ValidationException::withMessages(['weeklyHours' => 'Os intervalos precisam ter início antes do fim e não podem se sobrepor.']);
                }
                $lastEnd = $range['end'];
                $hasHours = true;
                [$sh,$sm] = array_map('intval', explode(':', $range['start']));
                [$eh,$em] = array_map('intval', explode(':', $range['end']));
                $slotFits = $slotFits || ($eh * 60 + $em - $sh * 60 - $sm >= $data['durationMinutes']);
            }
            $hours[$day] = $ranges;
        }
        if ($legacy && $data['publicEnabled'] && (! $data['enforced'] || ! $hasHours || ! $slotFits)) {
            throw ValidationException::withMessages(['publicEnabled' => 'Para liberar o link, configure o funcionamento e um intervalo que comporte a duração do atendimento.']);
        }
        if ($legacy && ! empty($data['instructorId']) && ! $org->usuarios()->whereKey($data['instructorId'])->wherePivot('status', 'ativo')->wherePivotIn('papel', ['proprietario', 'administrador', 'professor'])->exists()) {
            throw ValidationException::withMessages(['instructorId' => 'Selecione um professor ativo deste negócio.']);
        }
        if ($data['publicEnabled'] && empty($data['instructorId'])) {
            throw ValidationException::withMessages(['instructorId' => 'Selecione o responsável pelos agendamentos do link público.']);
        }
        DB::transaction(function () use ($org, $data, $hours, $legacy) {
            Organizacao::whereKey($org->id)->lockForUpdate()->firstOrFail();
            $s = OrganizationScheduleSetting::firstOrNew(['organizacao_id' => $org->id]);
            $s->fill(['enforced' => $data['enforced'], 'weekly_hours' => $hours, 'public_enabled' => $data['publicEnabled'], 'duration_minutes' => $data['durationMinutes'],
                'instructor_id' => $data['instructorId'] ?? null, 'title' => trim($data['title']), 'location' => $data['location'] ?? null]);
            if ($data['publicEnabled'] && ! $s->public_token) {
                $s->public_token = Str::random(64);
            }
            $s->save();
            if ($legacy && $s->public_token) {
                $link = OrganizationBookingLink::firstOrNew(['public_token' => $s->public_token]);
                if (! $link->exists && OrganizationBookingLink::where('organizacao_id', $org->id)->count() >= 3) {
                    throw ValidationException::withMessages(['bookingLinks' => 'É permitido cadastrar até três links.']);
                }
                $link->fill(['organizacao_id' => $org->id, 'title' => $s->title, 'duration_minutes' => $s->duration_minutes, 'instructor_id' => $s->instructor_id, 'location' => $s->location, 'active' => $s->public_enabled])->save();
            }
        });

        return response()->json($this->payload($org, $manage, $availability));
    }

    public function saveLink(Request $r, ScheduleAvailabilityService $availability, ?int $link = null)
    {
        [$org,$manage] = $this->context($r);
        abort_unless($manage, 403);
        $data = $r->validate(['title' => 'required|string|max:120', 'durationMinutes' => ['required', 'integer', Rule::in([15, 30, 45, 60, 90, 120])], 'instructorId' => 'required|integer', 'location' => 'nullable|string|max:120', 'active' => 'required|boolean']);
        DB::transaction(function () use ($org, $data, $availability, $link) {
            Organizacao::whereKey($org->id)->lockForUpdate()->firstOrFail();
            $model = $link ? OrganizationBookingLink::where('organizacao_id', $org->id)->findOrFail($link) : new OrganizationBookingLink(['organizacao_id' => $org->id, 'public_token' => Str::random(64)]);
            if (! $model->exists && OrganizationBookingLink::where('organizacao_id', $org->id)->count() >= 3) {
                throw ValidationException::withMessages(['bookingLinks' => 'É permitido cadastrar até três links.']);
            }
            $keepingInactiveInstructor = ! $data['active'] && $model->exists && (int) $model->instructor_id === (int) $data['instructorId'];
            if (! $keepingInactiveInstructor && ! $org->usuarios()->whereKey($data['instructorId'])->wherePivot('status', 'ativo')->wherePivotIn('papel', ['proprietario', 'administrador', 'professor'])->exists()) {
                throw ValidationException::withMessages(['instructorId' => 'Selecione um professor ativo deste negócio.']);
            }
            $settings = $availability->settings($org->id);
            $fits = collect($settings->weekly_hours)->flatten(1)->contains(function ($range) use ($data) {
                [$sh,$sm] = array_map('intval', explode(':', $range['start']));
                [$eh,$em] = array_map('intval', explode(':', $range['end']));

                return $eh * 60 + $em - $sh * 60 - $sm >= $data['durationMinutes'];
            });
            if ($data['active'] && (! $settings->enforced || ! $fits)) {
                throw ValidationException::withMessages(['active' => 'Configure horários de funcionamento que comportem este atendimento.']);
            }
            $model->fill(['title' => $data['title'], 'duration_minutes' => $data['durationMinutes'], 'instructor_id' => $data['instructorId'], 'location' => $data['location'] ?? null, 'active' => $data['active']])->save();
        });

        return response()->json($this->payload($org, $manage, $availability), $link ? 200 : 201);
    }

    public function deleteLink(Request $r, int $link, ScheduleAvailabilityService $availability)
    {
        [$org,$manage] = $this->context($r);
        abort_unless($manage, 403);
        DB::transaction(function () use ($org, $link) {
            Organizacao::whereKey($org->id)->lockForUpdate()->firstOrFail();
            $model = OrganizationBookingLink::where('organizacao_id', $org->id)->findOrFail($link);
            OrganizationScheduleSetting::where('organizacao_id', $org->id)->where('public_token', $model->public_token)->update(['public_token' => null, 'public_enabled' => false]);
            $model->delete();
        });

        return response()->json($this->payload($org, $manage, $availability));
    }
}
