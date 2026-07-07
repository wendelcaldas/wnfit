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

## Observacoes

- O banco SQLite nao deve ser usado em producao, pois o filesystem do container e efemero.
- Arquivos enviados para o disco `local` tambem nao sao permanentes. Antes de implementar uploads de producao, configure um storage externo como S3.
- Quando houver jobs em fila, crie um segundo servico usando a mesma imagem e o comando `php artisan queue:work --sleep=3 --tries=3`.
