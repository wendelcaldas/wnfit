# Boas-vindas automáticas — piloto Baileys

O novo aluno cadastrado em `POST /api/students` recebe o texto de boas-vindas configurado em Comunicacoes. O envio usa o numero WNFit vinculado ao servico Node.js. Nao ha navegador, Twilio, disparo retroativo nem cobranca automatica. Participantes criados por outros fluxos nao disparam esta mensagem.

Baileys e uma integracao nao oficial, com risco de desconexao, quebra de compatibilidade e restricao do numero. A dependencia esta fixada em 7.0.0-rc14; testar antes de atualizar.

## 1. Servico separado

No Railway, criar um servico com Root Directory `services/whatsapp` e o Dockerfile desse diretorio. Usar apenas uma replica e volume persistente montado em `/data`. Manter `sends.sqlite` e `auth/` no mesmo volume, com backup protegido. Nao compartilhar esse volume entre processos/replicas.

Variaveis do servico:

```env
BRIDGE_TOKEN=SEGREDO_ALEATORIO_COM_PELO_MENOS_32_CARACTERES
WHATSAPP_DATA_DIR=/data
BRIDGE_SHOW_QR=true
```

Gerar o segredo com `openssl rand -hex 32`. A aplicacao deve acessar esse servico exclusivamente por rede privada; nao criar dominio publico. Se usar IPv6 privado do Railway, definir `HOST=::`; caso contrario o padrao e `0.0.0.0`. `PORT` e fornecida pelo ambiente (padrao 3000).

Na primeira inicializacao, ler o QR nos logs restritos e vincular pelo WhatsApp Business da WNFit em **Dispositivos conectados > Conectar dispositivo**. Depois, definir `BRIDGE_SHOW_QR=false` e reiniciar. O QR e uma credencial temporaria: restringir acesso e limpar os logs de pareamento conforme os recursos do provedor. O QR nao e exposto por HTTP.

A sessao sobrevive a deploys pelo volume; o computador pessoal pode ficar desligado. Logout exige novo pareamento. Parar o servico, remover somente `/data/auth` se necessario e repetir o pareamento; **nunca apagar `sends.sqlite` para reconectar**, pois ele impede envios duplicados.

## 2. Aplicacao Laravel e worker

Configurar ambos com os mesmos valores:

```env
MESSAGING_MODE=manual
WHATSAPP_WELCOME_DRIVER=baileys
WHATSAPP_WELCOME_ORGANIZATION_IDS=ID_DO_STUDIO_PILOTO
WHATSAPP_BRIDGE_URL=http://ENDERECO_PRIVADO_DO_SERVICO:3000
WHATSAPP_BRIDGE_TOKEN=MESMO_SEGREDO_DO_BRIDGE_TOKEN
QUEUE_CONNECTION=database
```

Aceita IDs separados por virgula; lista vazia nao habilita nenhum studio. Todos os studios habilitados compartilham o numero WNFit. Os demais continuam com o fluxo manual. `ativo` em Comunicacoes deve estar habilitado. O texto existente e reutilizado; esta etapa nao altera o mecanismo de login nem envia senha ou link de redefinicao. O gestor continua entregando as credenciais pelo fluxo atual.

Executar as migrations habituais. O projeto ja possui tabelas de jobs. Criar um worker separado, usando a imagem Laravel e o comando:

```sh
php artisan queue:work database --queue=whatsapp --sleep=3 --tries=5 --timeout=45
```

O dispatch usa explicitamente a conexao `database` e so ocorre apos commit. O worker precisa ser um processo permanente. Limpar/recriar o cache de configuracao ao alterar variaveis e reiniciar workers. No Railway, conferir o comportamento do entrypoint ao definir comando personalizado.

## 3. Verificacao

1. Consultar `GET /health` na rede privada com `Authorization: Bearer SEGREDO`: HTTP 200 indica conectado, 503 desconectado. Usar monitor interno para alertar em caso de 503 ou indisponibilidade.
2. Cadastrar um aluno de teste com seu telefone em um studio habilitado.
3. Conferir uma unica mensagem e transicao `pendente` para `enviado` em Comunicacoes do aluno.
4. Reiniciar o servico e verificar que mantem a sessao.
5. Testar cadastro com ponte desconectada: aluno e criado normalmente; job tenta novamente com backoff.
6. Confirmar que uma cobranca continua manual e que outro studio nao usa o remetente WNFit.

`enviado` significa que o servico obteve um ID de mensagem do WhatsApp, nao comprovacao de entrega/leitura. Nao ha webhook de recibos nesta etapa. `incerto` aparece como **Conferir envio**; consultar a conversa antes de qualquer acao manual. O servico grava a chave antes de enviar; em queda durante envio, bloqueia repeticao automatica. Isso prefere uma mensagem eventualmente nao enviada a uma duplicacao silenciosa. HTTP 503/429 pode ser tentado novamente; resultados ambiguos recebem HTTP 409. O bridge armazena chave/fingerprint/ID, sem texto ou telefone em seu banco de deduplicacao. Laravel conserva o historico de conteudo existente.

## 4. Desativar

Definir `WHATSAPP_WELCOME_DRIVER=default` na aplicacao e no worker, manter `MESSAGING_MODE=manual`, atualizar cache e reiniciar processos. Jobs pendentes verificam essa configuracao e nao enviam; um envio ja em andamento pode terminar. Desativar `ativo` na organizacao tambem bloqueia jobs ainda nao iniciados. Nao ha endpoint generico de disparo ou reenvio em massa.

## Testes

```sh
php artisan test --filter=BillingMessagingTest
cd services/whatsapp
npm ci
npm test
```

Os testes usam HTTP falso e adaptador falso, sem contato com alunos reais. Um teste com o numero vinculado e necessario antes da ativacao em producao.
