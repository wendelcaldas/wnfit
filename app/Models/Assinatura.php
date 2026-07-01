<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Assinatura extends Model
{
    protected $fillable = [
        'organizacao_id',
        'aluno_id',
        'plano_id',
        'status',
        'inicio_em',
        'proximo_vencimento',
        'auto_renovacao',
        'metodo_pagamento',
    ];

    protected function casts(): array
    {
        return [
            'inicio_em' => 'date',
            'proximo_vencimento' => 'date',
            'auto_renovacao' => 'boolean',
        ];
    }

    public function aluno(): BelongsTo
    {
        return $this->belongsTo(Aluno::class);
    }

    public function plano(): BelongsTo
    {
        return $this->belongsTo(Plano::class);
    }

    public function cobrancas(): HasMany
    {
        return $this->hasMany(Cobranca::class);
    }
}
