<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

class StudentWorkoutPlan extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['snapshot' => 'array', 'starts_on' => 'date', 'ends_on' => 'date'];
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Aluno::class, 'aluno_id');
    }

    public function effectiveEndsOn(): ?Carbon
    {
        if ($this->ends_on) {
            return $this->ends_on->copy();
        }

        // Legacy assignments have no ends_on; use their frozen duration, never today's template.
        $weeks = filter_var($this->snapshot['durationWeeks'] ?? null, FILTER_VALIDATE_INT, [
            'options' => ['min_range' => 1, 'max_range' => 52],
        ]);

        return $weeks !== false && $this->starts_on ? $this->starts_on->copy()->addWeeks($weeks) : null;
    }

    public function accessStatus(): string
    {
        $end = $this->effectiveEndsOn();
        if (! $end || ! $this->starts_on) {
            return 'unavailable';
        }
        if ($end->lt(today())) {
            return 'expired';
        }
        if ($this->status !== 'ativo') {
            return 'closed';
        }

        return $this->starts_on->gt(today()) ? 'scheduled' : 'available';
    }

    public function expiresAt(): ?string
    {
        return $this->effectiveEndsOn()?->addDay()->startOfDay()->toIso8601String();
    }
}
