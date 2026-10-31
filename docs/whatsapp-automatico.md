# Boas-vindas automáticas — piloto Baileys

## Piloto de avisos ao gestor

O canal de avisos do gestor é independente das boas-vindas. Ao criar uma notificação
`workout_completed` para um proprietário ativo,
o sistema registra um envio em `staff_whatsapp_deliveries` e o enfileira em `whatsapp`.
O piloto é habilitado por organização. Todos os proprietários ativos com telefone
individual válido recebem a mensagem, nos números editáveis em Configurações →
Usuários → Equipe cadastrada. Administradores e professores não recebem por este canal.
Telefones iguais, mesmo com pontuação ou +55 diferentes, recebem uma única mensagem.
Não há fallback para o contato compartilhado se o telefone individual estiver vazio.
O cadastro inicial preenche esse telefone e a migration preserva o contato existente.
Os telefones da equipe são salvos por vínculo com o ambiente. Editar o telefone do
proprietário também atualiza o contato do ambiente. Telefone vazio desativa os avisos para esse proprietário. Alterações cancelam envios pendentes para
o número anterior; os próximos eventos usam o novo contato.

```env
WHATSAPP_STAFF_TARGETS='{"ID_DO_STUDIO":true}'
WHATSAPP_STAFF_SINCE=2026-10-30T18:00:00-03:00
WHATSAPP_STAFF_MULTIPLE_OWNERS=true
```

Configurar os mesmos valores na aplicação e no worker, além das variáveis da ponte.
Implantar primeiro a ponte com o endpoint privado `POST /staff-notifications`, executar
as migrations e depois ativar o canal. Não há importação do histórico de notificações
nem alteração no canal de boas-vindas. A verificação de vencimentos continua às
`00:05` somente para os avisos internos do sistema. Não há envio de ficha vencida
por WhatsApp nem agendamento às 06h. A migration cancela envios antigos pendentes;
o worker e a ponte também bloqueiam esse tipo. Treinos concluídos continuam imediatos.

Cada evento tem uma chave única por organização, sessão e telefone normalizado,
com hash do destinatário no sufixo `:to:`. A ponte persiste essa chave antes do
envio. Desconexão/ocupação permite nova tentativa; resultado ambíguo fica `uncertain`
para conferência, sem reenvio cego. `sent` indica aceitação com ID, não entrega.
Os recibos continuam consultáveis em `GET /messages/{id}`. Para desativar, definir
`WHATSAPP_STAFF_TARGETS={}` na aplicação e no worker e reiniciar ambos. Jobs pendentes
revalidam o destinatário e a organização ativa antes de enviar.

Ao atualizar versões antigas, manter `WHATSAPP_STAFF_MULTIPLE_OWNERS=false` na
aplicação durante o deploy da ponte e do worker. Só ativar após os três serviços
estarem atualizados: workers e ponte antigos não conhecem o novo sufixo. O modo
temporário usa a chave antiga e um único destinatário. Reservas antigas são
preservadas para o mesmo telefone em qualquer estado, evitando duplicação durante
a transição. A ativação não reprocessa o histórico nem envia mensagens de teste.

O novo aluno cadastrado em `POST /api/students` recebe o texto de boas-vindas configurado em Comunicacoes. O envio usa o numero WNFit vinculado ao servico Node.js. Nao ha navegador, Twilio, disparo retroativo nem cobranca automatica. Participantes criados por outros fluxos nao disparam esta mensagem.

Baileys e uma integracao nao oficial, com risco de desconexao, quebra de compatibilidade e restricao do numero. A dependencia esta fixada em 7.0.0-rc14; testar antes de atualizar.

## 1. Servico separado

No Railway, criar um servico com Root Directory `services/whatsapp` e o Dockerfile desse diretorio. Configurar Dockerfile `Dockerfile`, sem healthcheck HTTP e sem pre-deploy. Definir `RAILWAY_RUN_UID=0` para acesso ao volume montado pelo Railway. Usar apenas uma replica e volume persistente montado em `/data`. Manter `sends.sqlite` e `auth/` no mesmo volume, com backup protegido. Nao compartilhar esse volume entre processos/replicas.

Variaveis do servico:

```env
BRIDGE_TOKEN=SEGREDO_ALEATORIO_COM_PELO_MENOS_32_CARACTERES
WHATSAPP_DATA_DIR=/data
BRIDGE_SHOW_QR=true
```

Gerar o segredo com `openssl rand -hex 32`. A aplicacao deve acessar esse servico exclusivamente por rede privada; nao criar dominio publico. Se usar IPv6 privado do Railway, definir `HOST=::`; caso contrario o padrao e `0.0.0.0`. `PORT` e fornecida pelo ambiente (padrao 3000).

Na primeira inicializacao, ler o QR nos logs restritos e vincular pelo WhatsApp Business da WNFit em **Dispositivos conectados > Conectar dispositivo**. Depois, definir `BRIDGE_SHOW_QR=false` e reiniciar. O QR e uma credencial temporaria: restringir acesso e limpar os logs de pareamento conforme os recursos do provedor. O QR tambem pode ser consultado em `GET /pairing`, exclusivamente na rede privada e com o mesmo Bearer token; nunca criar dominio publico para a ponte.

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

Executar as migrations habituais. O projeto ja possui tabelas de jobs. Criar um worker separado, usando a imagem Laravel, sem healthcheck HTTP nem pre-deploy, com o comando abaixo configurado diretamente no servico. Nao herdar o healthcheck `/up` do servico web:

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

### Diagnostico de entrega

O bridge resolve o JID canonico por `onWhatsApp` antes do envio. `GET /messages/{id}` (privado, Bearer token) informa `accepted`, `server_ack`, `delivered`, `read` ou `error`, a partir dos recibos persistidos. Um ID local nao comprova entrega. A interface Laravel continua mostrando o historico de envio; os recibos deste endpoint sao usados para diagnostico nesta etapa. Testes pontuais usam chaves `welcome-test:<organizacao>:<identificador>` para nao colidir com alunos reais.

Se o WhatsApp revogar explicitamente a sessao, o bridge descarta somente a autenticacao revogada e disponibiliza novo QR. O banco de deduplicacao permanece intacto. Objetos de sessao da dependencia libsignal sao removidos das chamadas diretas ao console para impedir registro de chaves.
