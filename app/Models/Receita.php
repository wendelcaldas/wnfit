<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Receita extends Model
{
    protected $fillable = [
        'organizacao_id',
        'aluno_id',
        'valor',
        'competencia',
        'descricao',
    ];

    protected function casts(): array
    {
        return [
            'valor' => 'decimal:2',
            'competencia' => 'date',
        ];
    }

    public function organizacao(): BelongsTo
    {
        return $this->belongsTo(Organizacao::class);
    }
}
