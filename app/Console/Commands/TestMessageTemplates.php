<?php

namespace App\Console\Commands;

use App\Models\MensagemConfiguracao;
use App\Services\Messaging\EventMessageTemplates;
use App\Services\Messaging\MessagingService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;

class TestMessageTemplates extends Command
{
    protected $signature = 'messages:test-templates {--run= : Identificador estável desta rodada} {--organization= : Usar boas-vindas configuradas nesta organização} {--send : Enviar; sem esta opção apenas exibe os modelos} {--json : Exibir prévia em JSON}';

    protected $description = 'Confere os seis modelos com dados fictícios e envia somente ao destinatário de teste configurado';

    public function handle(MessagingService $messaging): int
    {
        $welcome = null;
        if ($id = $this->option('organization')) {
            $welcome = MensagemConfiguracao::where('organizacao_id', $id)->value('welcome_message_body');
            if (! $welcome) {
                $this->error('Configuração de boas-vindas não encontrada.');

                return self::FAILURE;
            }
        }
        $samples = EventMessageTemplates::samples($welcome);
        if (! $this->option('send')) {
            if ($this->option('json')) {
                $this->line(json_encode($samples, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
            } else {
                foreach ($samples as $type => $body) {
                    $this->line($type.': '.$body);
                }
                $this->info('Prévia: nenhuma mensagem enviada.');
            }

            return self::SUCCESS;
        }
        $run = $this->option('run');
        $target = config('services.messaging.test_recipient');
        $url = config('services.messaging.baileys.url');
        $token = config('services.messaging.baileys.token');
        if (! is_string($run) || ! preg_match('/^[A-Za-z0-9_-]{1,32}$/', $run) || ! $target || ! $url || ! $token) {
            $this->error('Configure WHATSAPP_TEST_RECIPIENT e a ponte; informe --run com até 32 letras, números, _ ou -.');

            return self::FAILURE;
        }
        $to = $messaging->normalizeBrazilianPhone($target);
        $health = Http::withToken($token)->connectTimeout(5)->timeout(10)->get(rtrim($url, '/').'/health');
        if (! $health->successful() || $health->json('connected') !== true) {
            $this->error('Ponte desconectada. Nenhum teste enviado.');

            return self::FAILURE;
        }
        foreach ($samples as $type => $body) {
            // Diagnostic namespace supported by the bridge; no real events or students are created.
            $key = 'welcome-test:0:'.$run.'_'.$type;
            $response = Http::withToken($token)->connectTimeout(5)->timeout(35)->post(rtrim($url, '/').'/welcome', [
                'key' => $key, 'to' => $to, 'body' => "[TESTE WNFit · {$type}]\n".$body,
            ]);
            if (! $response->successful() || ! is_string($response->json('id')) || $response->json('id') === '') {
                $this->error($type.': HTTP '.$response->status().'. Rodada interrompida; confira os recibos antes de repetir.');

                return self::FAILURE;
            }
            $this->info($type.': aceito pela ponte; ID '.$response->json('id'));
        }

        return self::SUCCESS;
    }
}
