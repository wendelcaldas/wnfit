<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Cobranca extends Model
{
    protected $fillable = [
        'organizacao_id',
        'aluno_id',
        'assinatura_id',
        'competencia',
        'vencimento',
        'valor',
        'status',
        'forma_pagamento',
        'pago_em',
        'link_pagamento',
        'enviado_em',
    ];

    protected function casts(): array
    {
        return [
            'vencimento' => 'date',
            'valor' => 'decimal:2',
            'pago_em' => 'datetime',
            'enviado_em' => 'datetime',
        ];
    }

    public function aluno(): BelongsTo
    {
        return $this->belongsTo(Aluno::class);
    }

    public function assinatura(): BelongsTo
    {
        return $this->belongsTo(Assinatura::class);
    }

    public function pagamento(): HasOne
    {
        return $this->hasOne(Pagamento::class);
    }

    public function eventos(): HasMany
    {
        return $this->hasMany(CobrancaEvento::class);
    }
}
