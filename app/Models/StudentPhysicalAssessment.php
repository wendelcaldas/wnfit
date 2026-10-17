<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StudentPhysicalAssessment extends Model
{
    public const MEASURES = ['weight', 'height', 'body_fat', 'arm_right', 'arm_left', 'chest', 'waist', 'hip', 'thigh_right', 'thigh_left', 'calf_right', 'calf_left'];

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['assessed_on' => 'date', ...array_fill_keys(self::MEASURES, 'decimal:2')];
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function editor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }
}
