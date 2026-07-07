<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Treino extends Model
{
    use HasFactory;

    protected $fillable = [
        'organizacao_id', 'criado_por', 'nome', 'objetivo', 'nivel',
        'sessoes_semana', 'duracao_semanas', 'status', 'descricao',
    ];

    public function organizacao(): BelongsTo
    {
        return $this->belongsTo(Organizacao::class);
    }

    public function criador(): BelongsTo
    {
        return $this->belongsTo(User::class, 'criado_por');
    }

    public function dias(): HasMany
    {
        return $this->hasMany(TreinoDia::class)->orderBy('ordem');
    }

    public function alunos(): BelongsToMany
    {
        return $this->belongsToMany(Aluno::class, 'aluno_treino')->withPivot('ativo')->withTimestamps();
    }
}
