<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TreinoDia extends Model
{
    protected $table = 'treino_dias';
    protected $fillable = ['treino_id', 'nome', 'foco', 'ordem'];

    public function treino(): BelongsTo { return $this->belongsTo(Treino::class); }
    public function exercicios(): HasMany { return $this->hasMany(TreinoExercicio::class)->orderBy('ordem'); }
}
