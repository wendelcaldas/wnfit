<?php

namespace App\Services\Messaging;

use App\Models\Aluno;
use App\Models\Cobranca;
use App\Models\Mensagem;
use App\Models\MensagemConfiguracao;
use App\Models\Organizacao;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class MessagingService
{
    public function __construct(private readonly WhatsAppMessageDriver $driver) {}

    public function sendChargeReminder(Cobranca $charge, string $type = 'cobranca_manual'): Mensagem
    {
        $charge->loadMissing(['aluno', 'assinatura.plano', 'organizacao']);

        if ($this->isManualMode()) {
            return $this->prepareManualChargeReminder($charge, 'Envio manual configurado para esta organizacao.');
        }

        $student = $charge->aluno;
        $settings = $this->settingsForOrganization($charge->organizacao);
        $templateVariables = $this->chargeTemplateVariables($charge);

        return $this->sendWhatsAppMessage(
            organization: $charge->organizacao,
            student: $student,
            type: $type,
            templateKey: $settings->charge_template_key,
            templateSid: $settings->charge_template_sid ?: config('services.twilio.whatsapp.templates.charge_reminder'),
            body: $this->renderTemplate($settings->charge_message_body, $templateVariables),
            variables: $templateVariables,
            charge: $charge,
        );
    }

    public function prepareManualChargeReminder(Cobranca $charge, string $reason): Mensagem
    {
        $charge->loadMissing(['aluno', 'assinatura.plano', 'organizacao']);

        $settings = $this->settingsForOrganization($charge->organizacao);
        $variables = $this->chargeTemplateVariables($charge);
        $to = $this->normalizeBrazilianPhone($charge->aluno->telefone);
        $body = $this->renderTemplate($settings->charge_message_body, $variables);

        return Mensagem::query()->create([
            'organizacao_id' => $charge->organizacao_id,
            'aluno_id' => $charge->aluno_id,
            'cobranca_id' => $charge->id,
            'canal' => 'whatsapp',
            'provedor' => 'whatsapp_link',
            'tipo' => 'cobranca_manual_assistida',
            'status' => 'manual_preparado',
            'destinatario' => $to,
            'remetente' => $settings->whatsapp_from ?: config('services.twilio.whatsapp.from'),
            'template_key' => $settings->charge_template_key,
            'conteudo' => $body,
            'payload' => [
                'manual_url' => $this->manualWhatsAppUrl($to, $body),
                'fallback_reason' => $reason,
            ],
            'erro' => $reason,
        ]);
    }

    public function manualWhatsAppUrlForMessage(Mensagem $message): string
    {
        return $message->payload['manual_url']
            ?? $this->manualWhatsAppUrl($message->destinatario, $message->conteudo);
    }

    public function sendWelcomeMessage(Aluno $student): Mensagem
    {
        $student->loadMissing(['organizacao', 'assinatura.plano']);

        if ($this->isManualMode()) {
            return $this->prepareManualWelcomeMessage($student, 'Envio manual configurado para esta organizacao.');
        }

        $settings = $this->settingsForOrganization($student->organizacao);
        $templateVariables = $this->welcomeTemplateVariables($student);

        return $this->sendWhatsAppMessage(
            organization: $student->organizacao,
            student: $student,
            type: 'boas_vindas',
            templateKey: $settings->welcome_template_key,
            templateSid: $settings->welcome_template_sid ?: config('services.twilio.whatsapp.templates.student_welcome'),
            body: $this->renderTemplate($settings->welcome_message_body, $templateVariables),
            variables: $templateVariables,
        );
    }

    public function prepareManualWelcomeMessage(Aluno $student, string $reason): Mensagem
    {
        $student->loadMissing(['organizacao', 'assinatura.plano']);

        $settings = $this->settingsForOrganization($student->organizacao);
        $variables = $this->welcomeTemplateVariables($student);
        $to = $this->normalizeBrazilianPhone($student->telefone);
        $body = $this->renderTemplate($settings->welcome_message_body, $variables);

        return Mensagem::query()->create([
            'organizacao_id' => $student->organizacao_id,
            'aluno_id' => $student->id,
            'canal' => 'whatsapp',
            'provedor' => 'whatsapp_link',
            'tipo' => 'boas_vindas',
            'status' => 'manual_preparado',
            'destinatario' => $to,
            'remetente' => $settings->whatsapp_from ?: config('services.twilio.whatsapp.from'),
            'template_key' => $settings->welcome_template_key,
            'conteudo' => $body,
            'payload' => [
                'manual_url' => $this->manualWhatsAppUrl($to, $body),
                'fallback_reason' => $reason,
            ],
            'erro' => $reason,
        ]);
    }

    public function markManualMessageOpened(Mensagem $message): Mensagem
    {
        if (! in_array($message->status, ['manual_preparado', 'manual_aberto'], true)) {
            return $message;
        }

        $payload = $message->payload ?? [];
        $payload['manual_url'] = $payload['manual_url'] ?? $this->manualWhatsAppUrlForMessage($message);
        $payload['opened_at'] = $payload['opened_at'] ?? now()->toISOString();

        $message->update([
            'status' => 'manual_aberto',
            'payload' => $payload,
        ]);

        return $message->refresh();
    }

    public function markManualMessageSent(Mensagem $message): Mensagem
    {
        return DB::transaction(function () use ($message) {
            $message = Mensagem::query()->lockForUpdate()->findOrFail($message->id);
            if (! in_array($message->status, ['manual_preparado', 'manual_aberto'], true)) {
                return $message;
            }

            $sentAt = now();
            $payload = $message->payload ?? [];
            $payload['manual_url'] = $payload['manual_url'] ?? $this->manualWhatsAppUrlForMessage($message);
            $payload['sent_manually_at'] = $sentAt->toISOString();

            $message->update([
                'status' => 'manual_enviado',
                'payload' => $payload,
                'erro' => null,
                'enviado_em' => $sentAt,
            ]);

            $charges = $message->cobrancas()->get();
            if ($message->cobranca && ! $charges->contains('id', $message->cobranca_id)) {
                $charges->push($message->cobranca);
            }
            foreach ($charges as $charge) {
                $charge->update(['enviado_em' => $sentAt]);
                $charge->eventos()->create([
                    'tipo' => 'mensagem_manual_enviada',
                    'descricao' => 'Mensagem marcada como enviada manualmente pelo WhatsApp.',
                    'ocorrido_em' => $sentAt,
                ]);
            }

            return $message->refresh();
        });
    }

    public function prepareGroupedReminder(Aluno $student, Collection $charges): Mensagem
    {
        $lines = $charges->sortBy('vencimento')->map(fn ($c) => $c->competencia.' — R$ '.number_format((float) $c->valor, 2, ',', '.').' (vencimento '.$c->vencimento->format('d/m/Y').')')->implode("\n");
        $body = 'Ola, '.$student->nome.'! Seguem as mensalidades em aberto no '.$student->organizacao->nome_fantasia.":\n\n".$lines."\n\nTotal: R$ ".number_format((float) $charges->sum('valor'), 2, ',', '.').".\nPodemos combinar o pagamento? Se ja pagou, envie o comprovante para conferirmos. Obrigado!";
        $message = Mensagem::create([
            'organizacao_id' => $student->organizacao_id, 'aluno_id' => $student->id,
            'canal' => 'whatsapp', 'provedor' => 'whatsapp_link', 'tipo' => 'cobranca_manual_assistida',
            'status' => 'manual_preparado', 'destinatario' => $this->normalizeBrazilianPhone($student->telefone),
            'conteudo' => $body,
        ]);
        $message->cobrancas()->attach($charges->pluck('id'));
        foreach ($charges as $charge) {
            $charge->eventos()->create(['tipo' => 'mensagem_manual_preparada', 'descricao' => 'Mensalidade incluida em mensagem conjunta preparada para envio manual.', 'ocorrido_em' => now()]);
        }

        return $message;
    }

    public function settingsForOrganization(Organizacao $organization): MensagemConfiguracao
    {
        $settings = MensagemConfiguracao::query()->firstOrCreate(
            ['organizacao_id' => $organization->id],
            [
                'provedor' => $this->isManualMode() ? 'whatsapp_link' : 'twilio',
                'whatsapp_from' => $organization->telefone_contato ?: config('services.twilio.whatsapp.from'),
                'charge_template_key' => 'charge_reminder_default',
                'charge_template_sid' => config('services.twilio.whatsapp.templates.charge_reminder'),
                'charge_message_body' => self::defaultChargeMessageBody(),
                'welcome_template_key' => 'student_welcome_default',
                'welcome_template_sid' => config('services.twilio.whatsapp.templates.student_welcome'),
                'welcome_message_body' => self::defaultWelcomeMessageBody(),
                'ativo' => true,
            ],
        );

        if (! $settings->welcome_message_body) {
            $settings->forceFill([
                'welcome_template_key' => $settings->welcome_template_key ?: 'student_welcome_default',
                'welcome_template_sid' => $settings->welcome_template_sid ?: config('services.twilio.whatsapp.templates.student_welcome'),
                'welcome_message_body' => self::defaultWelcomeMessageBody(),
            ])->save();
        }

        return $settings;
    }

    public function isManualMode(): bool
    {
        return config('services.messaging.mode', 'manual') === 'manual';
    }

    public static function defaultChargeMessageBody(): string
    {
        return 'Ola, {aluno}! Sua mensalidade do {plano} no valor de {valor} vence em {vencimento}. Podemos combinar o pagamento? Se ja pagou, por favor envie o comprovante. Obrigado!';
    }

    public static function defaultWelcomeMessageBody(): string
    {
        return 'Ola, {aluno}! Seja bem-vindo(a) ao {studio}. Seu plano {plano} ja esta ativo e o primeiro vencimento fica para {vencimento}.';
    }

    private function sendWhatsAppMessage(
        Organizacao $organization,
        Aluno $student,
        string $type,
        ?string $templateKey,
        ?string $templateSid,
        string $body,
        array $variables,
        ?Cobranca $charge = null,
    ): Mensagem {
        $settings = $this->settingsForOrganization($organization);
        $to = $this->normalizeBrazilianPhone($student->telefone);
        $from = $settings->whatsapp_from ?: config('services.twilio.whatsapp.from');

        $message = Mensagem::query()->create([
            'organizacao_id' => $organization->id,
            'aluno_id' => $student->id,
            'cobranca_id' => $charge?->id,
            'canal' => 'whatsapp',
            'provedor' => config('services.twilio.whatsapp.driver', 'twilio'),
            'tipo' => $type,
            'status' => 'pendente',
            'destinatario' => $to,
            'remetente' => $from,
            'template_key' => $templateKey,
            'conteudo' => $body,
        ]);

        $requiresSender = config('services.twilio.whatsapp.driver') !== 'fake';

        if (! $settings->ativo || ($requiresSender && ! $from)) {
            $message->update([
                'status' => 'falhou',
                'erro' => ! $settings->ativo
                    ? 'Envio de mensagens desativado para esta organizacao.'
                    : 'Numero WhatsApp remetente nao configurado.',
            ]);

            return $message->refresh();
        }

        if ($requiresSender && config('app.env') === 'production' && ! $templateSid) {
            $message->update([
                'status' => 'falhou',
                'erro' => 'Template aprovado do Twilio WhatsApp nao configurado para mensagens iniciadas pelo sistema.',
            ]);

            return $message->refresh();
        }

        $result = $templateSid
            ? $this->driver->sendTemplate($to, (string) $templateSid, $variables, $from)
            : $this->driver->sendText($to, $body, $from);

        $message->update([
            'status' => $result->sent ? ($result->status ?: 'enviado') : 'falhou',
            'provider_message_id' => $result->providerMessageId,
            'payload' => $result->payload,
            'erro' => $result->error,
            'enviado_em' => $result->sent ? now() : null,
        ]);

        return $message->refresh();
    }

    private function chargeTemplateVariables(Cobranca $charge): array
    {
        $student = $charge->aluno;
        $planName = $charge->assinatura?->plano?->nome ?? $student->plano ?? 'seu plano';
        $amount = 'R$ '.number_format((float) $charge->valor, 2, ',', '.');
        $dueDate = $charge->vencimento->format('d/m/Y');
        $paymentLink = $charge->link_pagamento && parse_url($charge->link_pagamento, PHP_URL_HOST) !== 'pay.wnfit.test'
            ? $charge->link_pagamento : '(consulte o studio para combinar o pagamento)';

        return [
            '1' => $student->nome,
            '2' => $planName,
            '3' => $amount,
            '4' => $dueDate,
            '5' => $paymentLink,
            'aluno' => $student->nome,
            'plano' => $planName,
            'valor' => $amount,
            'vencimento' => $dueDate,
            'link_pagamento' => $paymentLink,
            'studio' => $charge->organizacao?->nome_fantasia ?? 'studio',
        ];
    }

    private function welcomeTemplateVariables(Aluno $student): array
    {
        $planName = $student->assinatura?->plano?->nome ?? $student->plano ?? 'seu plano';
        $dueDate = $student->assinatura?->proximo_vencimento ?? $student->vencimento;

        return [
            '1' => $student->nome,
            '2' => $student->organizacao?->nome_fantasia ?? 'studio',
            '3' => $planName,
            '4' => $dueDate ? Carbon::parse($dueDate)->format('d/m/Y') : '-',
            'aluno' => $student->nome,
            'studio' => $student->organizacao?->nome_fantasia ?? 'studio',
            'plano' => $planName,
            'vencimento' => $dueDate ? Carbon::parse($dueDate)->format('d/m/Y') : '-',
            'valor' => '',
            'link_pagamento' => '',
        ];
    }

    private function renderTemplate(string $template, array $variables): string
    {
        return Str::of($template)
            ->replace('{aluno}', $variables['aluno'])
            ->replace('{plano}', $variables['plano'])
            ->replace('{valor}', $variables['valor'])
            ->replace('{vencimento}', $variables['vencimento'])
            ->replace('{link_pagamento}', $variables['link_pagamento'])
            ->replace('{studio}', $variables['studio'])
            ->toString();
    }

    private function manualWhatsAppUrl(string $to, string $body): string
    {
        $digits = preg_replace('/\D+/', '', $to) ?: '';

        return 'https://wa.me/'.$digits.'?text='.rawurlencode($body);
    }

    public function normalizeBrazilianPhone(?string $phone): string
    {
        $digits = preg_replace('/\D+/', '', $phone ?? '') ?: '';

        if (in_array(strlen($digits), [12, 13], true) && str_starts_with($digits, '55')) {
            $digits = substr($digits, 2);
        }
        if (! preg_match('/^[1-9][0-9](?:[2-5][0-9]{7}|9[0-9]{8})$/', $digits)) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'telefone' => 'Cadastre um telefone válido do aluno com DDD para enviar pelo WhatsApp.',
            ]);
        }
        return '+55'.$digits;
    }
}
