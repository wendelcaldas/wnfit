<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EventRegistration extends Model
{
    protected $guarded = ['id'];

    protected $hidden = ['access_hash', 'recovery_hash'];

    protected function casts(): array
    {
        return ['marketing_opt_in' => 'boolean', 'checked_in_at' => 'datetime', 'privacy_accepted_at' => 'datetime'];
    }

    public function participantPayload(): array
    {
        return ['name' => $this->name, 'registeredAt' => $this->created_at->toIso8601String(), 'checkedInAt' => $this->checked_in_at?->toIso8601String(),
            'attendanceSource' => $this->attendance_source, 'rating' => $this->rating, 'feedback' => $this->feedback,
            'marketingOptIn' => $this->marketing_opt_in];
    }
}
