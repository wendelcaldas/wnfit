<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Agendamento extends Model
{
    protected $fillable = [
        'organizacao_id', 'titulo', 'tipo', 'status', 'inicio_em', 'fim_em',
        'instrutor_id', 'modalidade', 'local', 'endereco', 'capacidade', 'observacoes',
    ];

    protected function casts(): array
    {
        return [
            'inicio_em' => 'datetime',
            'fim_em' => 'datetime',
            'capacidade' => 'integer',
        ];
    }

    public function organizacao(): BelongsTo
    {
        return $this->belongsTo(Organizacao::class);
    }

    public function instrutor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'instrutor_id');
    }

    public function alunos(): BelongsToMany
    {
        return $this->belongsToMany(Aluno::class, 'agendamento_aluno')
            ->withPivot('status')
            ->withTimestamps();
    }
}
