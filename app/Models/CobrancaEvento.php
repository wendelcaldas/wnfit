<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CobrancaEvento extends Model
{
    protected $table = 'cobranca_eventos';

    protected $fillable = [
        'cobranca_id',
        'tipo',
        'descricao',
        'ocorrido_em',
    ];

    protected function casts(): array
    {
        return [
            'ocorrido_em' => 'datetime',
        ];
    }

    public function cobranca(): BelongsTo
    {
        return $this->belongsTo(Cobranca::class);
    }
}
