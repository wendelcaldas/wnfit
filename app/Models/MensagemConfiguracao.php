<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MensagemConfiguracao extends Model
{
    public const EVENT_DEFAULTS = [
        'boas_vindas' => false,
        'treino_atualizado' => false,
        'vencimento_proximo' => false,
        'vencimento_hoje' => false,
        'cobranca_atrasada' => false,
        'agendamento_confirmado' => false,
    ];

    protected $table = 'mensagem_configuracoes';

    protected $attributes = [
        'overdue_delay_days' => 1,
    ];

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
        'event_preferences',
        'overdue_delay_days',
    ];

    protected function casts(): array
    {
        return [
            'ativo' => 'boolean',
            'event_preferences' => 'array',
            'overdue_delay_days' => 'integer',
        ];
    }

    public function eventPreferences(): array
    {
        return array_replace(self::EVENT_DEFAULTS, ['boas_vindas' => $this->ativo], $this->event_preferences ?? []);
    }

    public function organizacao(): BelongsTo
    {
        return $this->belongsTo(Organizacao::class);
    }
}
