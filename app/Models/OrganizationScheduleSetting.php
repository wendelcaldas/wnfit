<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OrganizationScheduleSetting extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['weekly_hours' => 'array', 'enforced' => 'boolean', 'public_enabled' => 'boolean', 'duration_minutes' => 'integer'];
    }
}
