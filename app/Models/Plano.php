<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Plano extends Model
{
    protected $fillable = [
        'organizacao_id',
        'nome',
        'valor_mensal',
        'ciclo',
        'ativo',
    ];

    protected function casts(): array
    {
        return [
            'valor_mensal' => 'decimal:2',
            'ativo' => 'boolean',
        ];
    }

    public function organizacao(): BelongsTo
    {
        return $this->belongsTo(Organizacao::class);
    }
}
