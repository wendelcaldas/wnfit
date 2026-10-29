<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Unidade extends Model
{
    protected $fillable = ['organizacao_id', 'nome', 'ativa', 'padrao'];
    protected function casts(): array { return ['ativa' => 'boolean', 'padrao' => 'boolean']; }
    public function organizacao(): BelongsTo { return $this->belongsTo(Organizacao::class); }
    public function alunos(): HasMany { return $this->hasMany(Aluno::class); }
}
