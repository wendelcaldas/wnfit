<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class StaffNotification extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['data' => 'array', 'read_at' => 'datetime'];
    }

    public function payload(): array
    {
        return [
            'id' => $this->id, 'type' => $this->type, 'data' => $this->data,
            'readAt' => $this->read_at?->toISOString(), 'createdAt' => $this->created_at->toISOString(),
        ];
    }
}
