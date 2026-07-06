<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TreinoExercicio extends Model
{
    protected $table = 'treino_exercicios';
    protected $fillable = ['treino_dia_id', 'exercicio_id', 'nome', 'grupo_muscular', 'series', 'repeticoes', 'carga', 'descanso_segundos', 'observacoes', 'ordem'];

    public function dia(): BelongsTo { return $this->belongsTo(TreinoDia::class, 'treino_dia_id'); }
    public function exercicio(): BelongsTo { return $this->belongsTo(Exercicio::class); }
}
