# Notificações em tempo real

O sino usa Reverb/WebSocket privado por organização e usuário. Não há polling de notificações, fallback periódico nem consulta ao banco por heartbeat. Uma consulta inicial carrega o histórico; eventos de criação/leitura e reconexões reconciliam o histórico e o contador via API (eventos próximos são agrupados). Abrir o sino ou atualizar manualmente também consulta a API. A indisponibilidade do transporte não desfaz a reserva ou a conclusão de um treino; o aviso permanece no banco.

A autorização do canal exige sessão da equipe, usuário destinatário e vínculo ativo com a organização. Ao sair ou mudar de contexto, o cliente desconecta. Eventos são publicados somente após o commit da transação. A chave pública vai no HTML, nunca o segredo. Não dependemos de um provedor WebSocket pago, mas o processo Reverb consome memória e conexões.

O GET de notificações não gera vencimentos. O comando já existente `workouts:notify-deadlines`, agendado às 00:05, gera esses avisos independentemente dos acessos. O Supervisor executa `php artisan schedule:work` no serviço web quando `SCHEDULER_ENABLED=true`. Ative em uma única réplica/serviço.

## Local

Configure no `.env` (chaves locais aleatórias, não versionadas):

```dotenv
BROADCAST_CONNECTION=reverb
REVERB_APP_ID=wnfit-local
REVERB_APP_KEY=CHAVE_PUBLICA_ALEATORIA
REVERB_APP_SECRET=SEGREDO_ALEATORIO
REVERB_HOST=127.0.0.1
REVERB_PORT=8081
REVERB_SCHEME=http
REVERB_PUBLIC_HOST=127.0.0.1
REVERB_PUBLIC_PORT=8081
REVERB_PUBLIC_SCHEME=http
```

Execute `php artisan config:clear` e `php artisan reverb:start --host=127.0.0.1 --port=8081` junto ao servidor e Vite.

## Deploy Docker / Railway

O Supervisor mantém Reverb no mesmo contêiner, porta interna 8081. Nginx encaminha `/app/` com upgrade de WebSocket, usando o domínio HTTPS da aplicação. Os broadcasts PHP usam localhost; o navegador usa o domínio público. Não é necessário publicar a porta 8081 externamente.

```dotenv
REVERB_ENABLED=true
SCHEDULER_ENABLED=true
BROADCAST_CONNECTION=reverb
REVERB_APP_ID=wnfit
REVERB_APP_KEY=CHAVE_PUBLICA_ALEATORIA
REVERB_APP_SECRET=SEGREDO_ALEATORIO
REVERB_HOST=127.0.0.1
REVERB_PORT=8081
REVERB_SCHEME=http
REVERB_PUBLIC_HOST=wnfit.com.br
REVERB_PUBLIC_PORT=443
REVERB_PUBLIC_SCHEME=https
```

Configure as variáveis no serviço antes de publicar. Elas são lidas em runtime, sem rebuild de JavaScript. Esta configuração suporta uma réplica da aplicação; múltiplas réplicas exigem o mecanismo de escala/Redis do Reverb para distribuir eventos. Reinicie os processos após alterar as chaves/configuração.

Referências: [Laravel Reverb](https://laravel.com/docs/12.x/reverb) e [Broadcasting](https://laravel.com/docs/12.x/broadcasting).
