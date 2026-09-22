<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Event extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['closed_at' => 'datetime', 'starts_at' => 'datetime', 'ends_at' => 'datetime', 'checkin_opens_at' => 'datetime', 'checkin_closes_at' => 'datetime'];
    }

    public function registrations(): HasMany
    {
        return $this->hasMany(EventRegistration::class);
    }

    public function publicPayload(): array
    {
        return [
            'id' => $this->id, 'slug' => $this->slug, 'name' => $this->name, 'organizer' => $this->organizer,
            'description' => $this->description, 'location' => $this->location, 'instructions' => $this->instructions,
            'status' => $this->status, 'startsAt' => $this->starts_at?->toIso8601String(), 'endsAt' => $this->ends_at?->toIso8601String(),
            'checkinOpensAt' => $this->checkin_opens_at?->toIso8601String(), 'checkinClosesAt' => $this->checkin_closes_at?->toIso8601String(),
            'timezone' => config('app.timezone'),
            'registrationOpen' => $this->status === 'published' && ! $this->closed_at
                && (! $this->capacity || $this->registrations()->count() < $this->capacity),
            'checkinOpen' => $this->status === 'published' && ! $this->closed_at && $this->checkin_opens_at && now()->gte($this->checkin_opens_at),
            'finished' => (bool) $this->closed_at,
            'afterContent' => $this->closed_at ? $this->after_content : null,
        ];
    }
}
