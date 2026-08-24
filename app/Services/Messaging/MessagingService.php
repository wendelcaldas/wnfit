<?php

namespace App\Services\Messaging;

use App\Models\Aluno;
use App\Models\Cobranca;
use App\Models\Mensagem;
use App\Models\MensagemConfiguracao;
use App\Models\Organizacao;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

class MessagingService
{
    public function __construct(private readonly WhatsAppMessageDriver $driver)
    {
    }

    public function sendChargeReminder(Cobranca $charge, string $type = 'cobranca_manual'): Mensagem
    {
        $charge->loadMissing(['aluno', 'assinatura.plano', 'organizacao']);

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

    public function sendWelcomeMessage(Aluno $student): Mensagem
    {
        $student->loadMissing(['organizacao', 'assinatura.plano']);

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

    public function settingsForOrganization(Organizacao $organization): MensagemConfiguracao
    {
        $settings = MensagemConfiguracao::query()->firstOrCreate(
            ['organizacao_id' => $organization->id],
            [
                'provedor' => 'twilio',
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

    public static function defaultChargeMessageBody(): string
    {
        return 'Ola, {aluno}! Sua mensalidade do {plano} no valor de {valor} vence em {vencimento}. Para pagar, acesse: {link_pagamento}';
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
        $paymentLink = $charge->link_pagamento ?: 'link de pagamento indisponivel';

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

    private function normalizeBrazilianPhone(string $phone): string
    {
        $digits = preg_replace('/\D+/', '', $phone) ?: '';

        if (Str::startsWith($digits, '55')) {
            return '+'.$digits;
        }

        return '+55'.$digits;
    }
}
