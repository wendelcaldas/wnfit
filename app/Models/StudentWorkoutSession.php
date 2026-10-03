<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class StudentWorkoutSession extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['snapshot' => 'array', 'progress' => 'array', 'started_at' => 'datetime', 'finished_at' => 'datetime'];
    }
}
