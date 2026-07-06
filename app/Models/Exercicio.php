<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Exercicio extends Model
{
    protected $table = 'exercicios';

    protected $fillable = [
        'organizacao_id', 'nome', 'grupo_muscular', 'grupo_secundario', 'equipamento',
        'categoria', 'nivel', 'instrucoes', 'cuidados', 'imagem_url', 'video_url', 'origem', 'ativo',
    ];

    protected function casts(): array { return ['ativo' => 'boolean']; }
    public function organizacao(): BelongsTo { return $this->belongsTo(Organizacao::class); }
}
