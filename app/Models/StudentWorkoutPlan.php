<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class StudentWorkoutPlan extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['snapshot' => 'array', 'starts_on' => 'date', 'ends_on' => 'date'];
    }
}
