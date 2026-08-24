# Deploy no Railway

O projeto usa uma unica imagem Docker com Nginx e PHP-FPM. O Railway constroi o `Dockerfile`, executa as migrations antes do deploy e verifica a aplicacao em `/up`.

## 1. Banco de dados

Adicione um servico MySQL ao projeto no Railway. No servico da aplicacao, configure `DB_URL` como referencia para `MYSQL_URL` do MySQL.

## 2. Variaveis obrigatorias

Use `.env.production.example` como referencia e configure, no minimo:

```env
APP_NAME=WNFIT
APP_ENV=production
APP_DEBUG=false
APP_KEY=base64:CHAVE_GERADA
APP_URL=https://SEU_DOMINIO
DB_CONNECTION=mysql
DB_URL=${{MySQL.MYSQL_URL}}
LOG_CHANNEL=stderr
SESSION_DRIVER=database
CACHE_STORE=database
QUEUE_CONNECTION=database
SESSION_SECURE_COOKIE=true
TWILIO_ACCOUNT_SID=AC...
TWILIO_AUTH_TOKEN=...
TWILIO_VALIDATE_WEBHOOK_SIGNATURE=true
TWILIO_WHATSAPP_DRIVER=twilio
TWILIO_WHATSAPP_FROM=+55...
TWILIO_WHATSAPP_WELCOME_TEMPLATE_SID=HX...
TWILIO_WHATSAPP_CHARGE_TEMPLATE_SID=HX...
TWILIO_WHATSAPP_STATUS_CALLBACK_URL=https://SEU_DOMINIO/api/webhooks/twilio/whatsapp/status
```

Gere `APP_KEY` localmente com:

```bash
php artisan key:generate --show
```

Copie o valor exibido para a variavel `APP_KEY` no Railway. Nao salve essa chave no Git.

## 3. Configuracao do servico

O arquivo `railway.json` ja define:

- build pelo `Dockerfile`;
- migration antes do deploy com `php artisan migrate --force`;
- healthcheck em `/up`;
- reinicio automatico em caso de falha.

Nao e necessario definir um Start Command no painel. O Railway usara o `CMD` do Dockerfile.

## 4. Dominio

Gere um dominio no painel do Railway e atualize `APP_URL` com a URL HTTPS completa.

## 5. WhatsApp / Twilio

As credenciais globais do Twilio ficam nas variaveis do Railway. O numero remetente, os templates e os textos ficam configurados por organizacao dentro do WNFit, em `Comunicacoes`.

Configure no Railway:

```env
TWILIO_ACCOUNT_SID=AC...
TWILIO_AUTH_TOKEN=...
TWILIO_VALIDATE_WEBHOOK_SIGNATURE=true
TWILIO_WHATSAPP_DRIVER=twilio
TWILIO_WHATSAPP_FROM=+55...
TWILIO_WHATSAPP_WELCOME_TEMPLATE_SID=HX...
TWILIO_WHATSAPP_CHARGE_TEMPLATE_SID=HX...
TWILIO_WHATSAPP_STATUS_CALLBACK_URL=https://SEU_DOMINIO/api/webhooks/twilio/whatsapp/status
```

No Twilio, cada numero de studio precisa estar registrado e aprovado como WhatsApp Sender. Para mensagens iniciadas pela empresa fora da janela de atendimento, cadastre templates aprovados no Content Template Builder e informe os `Content Template SID` na tela de configuracao de mensagens do WNFit:

- boas-vindas ao cadastrar aluno;
- cobranca manual ou automatica.

Em producao, o WNFit nao tenta enviar mensagens iniciadas pelo sistema sem template aprovado. O aluno continua sendo cadastrado, mas a mensagem fica registrada como `falhou` com erro de configuracao.

O endpoint publico de status e:

```text
POST /api/webhooks/twilio/whatsapp/status
```

Em producao, mantenha `TWILIO_VALIDATE_WEBHOOK_SIGNATURE=true` para validar a assinatura `X-Twilio-Signature` enviada pela Twilio.

## Observacoes

- O banco SQLite nao deve ser usado em producao, pois o filesystem do container e efemero.
- Arquivos enviados para o disco `local` tambem nao sao permanentes. Antes de implementar uploads de producao, configure um storage externo como S3.
- Quando houver jobs em fila, crie um segundo servico usando a mesma imagem e o comando `php artisan queue:work --sleep=3 --tries=3`.
