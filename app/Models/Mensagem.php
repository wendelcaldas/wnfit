<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Mensagem extends Model
{
    public function cobrancas(): BelongsToMany
    {
        return $this->belongsToMany(Cobranca::class, 'cobranca_mensagem');
    }

    protected $table = 'mensagens';

    protected $fillable = [
        'organizacao_id',
        'aluno_id',
        'cobranca_id',
        'canal',
        'provedor',
        'tipo',
        'status',
        'destinatario',
        'remetente',
        'template_key',
        'provider_message_id',
        'conteudo',
        'payload',
        'erro',
        'enviado_em',
        'entregue_em',
    ];

    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'enviado_em' => 'datetime',
            'entregue_em' => 'datetime',
        ];
    }

    public function aluno(): BelongsTo
    {
        return $this->belongsTo(Aluno::class);
    }

    public function cobranca(): BelongsTo
    {
        return $this->belongsTo(Cobranca::class);
    }

    public function organizacao(): BelongsTo
    {
        return $this->belongsTo(Organizacao::class);
    }
}
