<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Aluno extends Model
{
    use HasFactory;

    protected $fillable = [
        'organizacao_id',
        'nome',
        'email',
        'telefone',
        'data_nascimento',
        'genero',
        'cpf',
        'rg',
        'profissao',
        'endereco',
        'numero',
        'complemento',
        'bairro',
        'cidade',
        'estado',
        'cep',
        'objetivo',
        'treinador',
        'plano',
        'vencimento',
        'status',
        'peso',
        'altura',
        'como_conheceu',
        'unidade',
        'observacoes',
    ];

    protected function casts(): array
    {
        return [
            'vencimento' => 'date',
            'data_nascimento' => 'date',
            'peso' => 'decimal:2',
        ];
    }

    public function organizacao(): BelongsTo
    {
        return $this->belongsTo(Organizacao::class);
    }

    public function assinatura(): HasOne
    {
        return $this->hasOne(Assinatura::class)->latestOfMany();
    }

    public function cobrancas(): HasMany
    {
        return $this->hasMany(Cobranca::class);
    }
}
