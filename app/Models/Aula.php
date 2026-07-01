<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Aula extends Model
{
    protected $fillable = [
        'organizacao_id',
        'nome',
        'sala',
        'instrutor',
        'data',
        'hora',
        'capacidade',
        'reservas',
    ];

    protected function casts(): array
    {
        return [
            'data' => 'date',
        ];
    }

    public function organizacao(): BelongsTo
    {
        return $this->belongsTo(Organizacao::class);
    }
}
