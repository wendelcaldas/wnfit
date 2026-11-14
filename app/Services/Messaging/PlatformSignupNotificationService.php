<?php

namespace App\Services\Messaging;

use App\Jobs\SendPlatformSignupWhatsApp;
use App\Models\Organizacao;
use App\Models\PlatformSignupNotification;
use App\Models\User;
use Throwable;

class PlatformSignupNotificationService
{
    public function record(Organizacao $organization, User $owner): void
    {
        if (! config('services.messaging.signup_alerts.enabled')) {
            return;
        }
        try {
            $recipient = app(MessagingService::class)->normalizeBrazilianPhone(config('services.messaging.signup_alerts.recipient'));
        } catch (\Illuminate\Validation\ValidationException $exception) {
            report($exception);

            return;
        }
        PlatformSignupNotification::firstOrCreate(['organizacao_id' => $organization->id], [
            'recipient' => $recipient, 'body' => $this->body($organization, $owner),
        ]);
    }

    public function body(Organizacao $organization, User $owner): string
    {
        $profile = $organization->onboarding_profile ?? [];
        $labels = [
            'starting' => 'Começando agora', '1_20' => '1 a 20 alunos', '21_50' => '21 a 50 alunos',
            '51_100' => '51 a 100 alunos', '100_plus' => 'Mais de 100 alunos',
            'in_person' => 'Presencial', 'online' => 'Online', 'hybrid' => 'Híbrido',
            'solo' => 'Trabalha sozinho', 'team' => 'Possui equipe',
            'workouts' => 'Treinos', 'schedule' => 'Agenda', 'finance' => 'Financeiro',
            'progress' => 'Evolução dos alunos', 'events' => 'Eventos',
            'academia' => 'Academia', 'studio' => 'Studio', 'personal' => 'Personal trainer',
        ];
        $text = static fn ($value) => trim(preg_replace('/[\x00-\x1F\x7F]/u', ' ', (string) $value) ?? '') ?: 'Não informado';
        $answer = static fn ($key) => $labels[$profile[$key] ?? ''] ?? 'Não informado';
        $priorities = array_map(static fn ($key) => $labels[$key] ?? 'Não informado', $profile['priorities'] ?? []);

        return implode("\n", [
            'WNFit · Novo cadastro de empresa/lead',
            'Empresa: '.$text($organization->nome_fantasia),
            'Responsável: '.$text($owner->name),
            'WhatsApp: '.$text($organization->telefone_contato),
            'E-mail: '.$text($owner->email),
            'Tipo: '.($labels[$organization->tipo ?? ''] ?? 'Não informado'),
            'Alunos hoje: '.$answer('student_range'),
            'Atendimento: '.$answer('service_mode'),
            'Equipe: '.$answer('team'),
            'Prioridades: '.($priorities ? implode(', ', $priorities) : 'Não informado'),
            'Cadastro: '.$organization->created_at->timezone('America/Bahia')->format('d/m/Y H:i').' (Bahia)',
            'Origem: cadastro público WNFit',
            'Ambiente: #'.$organization->id.' · '.$text($organization->slug),
            'Gerenciar módulos: '.rtrim(config('app.url'), '/').'/modulos',
        ]);
    }

    public function dispatchPending(): int
    {
        if (! config('services.messaging.signup_alerts.enabled')) {
            return 0;
        }
        $count = 0;
        $ids = PlatformSignupNotification::where('status', 'pending')
            ->where(fn ($query) => $query->whereNull('queued_at')->orWhere('queued_at', '<=', now()->subMinutes(30)))
            ->orderBy('id')->limit(100)->pluck('id');
        foreach ($ids as $id) {
            $reserved = PlatformSignupNotification::whereKey($id)->where('status', 'pending')
                ->where(fn ($query) => $query->whereNull('queued_at')->orWhere('queued_at', '<=', now()->subMinutes(30)))
                ->update(['queued_at' => now()]);
            if (! $reserved) {
                continue;
            }
            try {
                SendPlatformSignupWhatsApp::dispatch($id)->onConnection('database')->onQueue('whatsapp');
                $count++;
            } catch (Throwable $exception) {
                PlatformSignupNotification::whereKey($id)->where('status', 'pending')->update(['queued_at' => null]);
                report($exception);
            }
        }

        return $count;
    }
}
