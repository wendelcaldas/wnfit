<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Organizacao extends Model
{
    use HasFactory;

    protected $table = 'organizacoes';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'nome_fantasia',
        'razao_social',
        'slug',
        'tipo',
        'email_contato',
        'telefone_contato',
        'ativa',
    ];

    /**
     * @return BelongsToMany<User, $this>
     */
    public function usuarios(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'organizacao_usuario')
            ->using(OrganizacaoUsuario::class)
            ->withPivot(['papel', 'status'])
            ->withTimestamps();
    }

    public function alunos(): HasMany
    {
        return $this->hasMany(Aluno::class);
    }

    public function planos(): HasMany
    {
        return $this->hasMany(Plano::class);
    }
}
