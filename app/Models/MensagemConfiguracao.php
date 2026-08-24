<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MensagemConfiguracao extends Model
{
    protected $table = 'mensagem_configuracoes';

    protected $fillable = [
        'organizacao_id',
        'provedor',
        'whatsapp_from',
        'charge_template_key',
        'charge_template_sid',
        'charge_message_body',
        'welcome_template_key',
        'welcome_template_sid',
        'welcome_message_body',
        'ativo',
    ];

    protected function casts(): array
    {
        return [
            'ativo' => 'boolean',
        ];
    }

    public function organizacao(): BelongsTo
    {
        return $this->belongsTo(Organizacao::class);
    }
}
