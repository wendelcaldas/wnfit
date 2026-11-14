# Mensagens WhatsApp — operação em produção

## Avisos internos de novos cadastros

Ao concluir `POST /api/register`, a transação registra um aviso interno por empresa
em `platform_signup_notifications`. Depois do commit, o aviso entra na fila
`whatsapp`, usando o mesmo número WNFit da ponte. O destinatário é fixado por
configuração na aplicação, no worker e na ponte; não vem do formulário público.
Esse canal é operacional da WNFit e independe do módulo Mensagens da empresa.

```env
WHATSAPP_SIGNUP_ALERTS_ENABLED=true
WHATSAPP_SIGNUP_RECIPIENT=+5571996651964
```

Configurar os dois valores na aplicação e no worker, e `WHATSAPP_SIGNUP_RECIPIENT`
também na ponte. Implantar o endpoint privado `/platform-notifications` e a migration
antes de receber avisos. A ponte só aceita o destinatário configurado nesse endpoint,
mesmo quando o envio normal de mensagens está liberado para todos os telefones.

O texto inclui empresa, responsável, telefone, e-mail, tipo de negócio quando
informado, faixa de alunos, atendimento, equipe, prioridades, data/hora na Bahia,
identificação do ambiente e link para Módulos. Não inclui senha, tokens ou dados de
alunos. A origem é descrita como cadastro público WNFit; não há atribuição automática
a Instagram ou a campanhas sem informação de rastreamento.

Só cadastros concluídos geram avisos. Consulta de disponibilidade de e-mail,
formulário abandonado, cadastro inválido e criação de aluno não são novos leads
nesse fluxo. Não há importação nem disparo para empresas já existentes.

Uma reserva de fila evita enfileirar continuamente o mesmo aviso. O scheduler
`platform-signups:dispatch` roda a cada minuto e recupera avisos não enfileirados
ou reservas com mais de 30 minutos. Uma falha ao enfileirar é registrada sem
interromper o cadastro. O worker tenta até cinco vezes com backoff; a ponte usa
uma chave persistida `platform:signup:<empresa>` para evitar repetição. Resultado
ambíguo fica `uncertain`, sem reenvio automático. `sent` é aceitação pela ponte;
entrega/leitura podem ser verificadas pelo ID em `/messages/{id}`.

Desabilitar `WHATSAPP_SIGNUP_ALERTS_ENABLED` na aplicação e worker interrompe novos
avisos e cancela os pendentes quando processados. Alterar o número cancela os
pendentes destinados ao contato anterior, sem redirecionar seus dados. Reiniciar
os processos para aplicar a configuração. O endpoint aceita a chave diagnóstica
`platform:signup-test:<rodada>` para um teste explicitamente identificado, sem
criar uma empresa real.

## Liberação por empresa

A tela interna **Módulos** é a fonte de autorização do módulo Mensagens. Apenas
contas marcadas como internas WNFit podem alterar essa liberação. O módulo começa
desligado para todas as novas empresas. As antigas listas
`WHATSAPP_WELCOME_ORGANIZATION_IDS` e `MESSAGING_MODULE_ORGANIZATION_SLUGS` não são
mais utilizadas; nenhuma variável por empresa é necessária.

Com a infraestrutura pronta, manter `WHATSAPP_EVENTS_ENABLED=true` e
`WHATSAPP_WELCOME_DRIVER=baileys` na aplicação e no worker. Na ponte,
`WHATSAPP_ALLOWED_RECIPIENTS` vazio permite os telefones normais dos destinatários.
Isso não libera empresas: uma organização precisa estar ativa e ter Mensagens
habilitado na tela interna; cada tipo de mensagem também respeita a preferência
salva na própria empresa. Ao liberar uma empresa, conferir essas preferências:
boas-vindas segue `ativo`, e os demais eventos começam desligados.

Desligar o módulo bloqueia novas boas-vindas e eventos e impede a execução de
jobs pendentes quando forem processados. Os avisos ao gestor também respeitam
o módulo, além das configurações específicas desse canal. Um envio que já começou
pode terminar. Nenhum cadastro, treino ou reserva antigo é reprocessado ao liberar
o módulo. O financeiro considera as datas da execução diária, sem recuperar dias
perdidos. Não habilitar módulos nem alterar preferências de empresas durante o deploy.

Em 14/11/2026, a conferência antes desta liberação encontrou os nove ambientes
com Mensagens desligado, sem mensagens Baileys pendentes. A preparação da
infraestrutura não alterou esses módulos nem enviou outra rodada de testes.

## Homologação dos seis modelos

Antes de ativar eventos, manter `WHATSAPP_EVENTS_ENABLED=false` na aplicação e
no worker. Configurar `WHATSAPP_TEST_RECIPIENT` com o número autorizado na
aplicação. Na ponte atualizada, configurar `WHATSAPP_ALLOWED_RECIPIENTS` com esse
mesmo número em E.164 (por exemplo `+5571996651964`). A restrição vale para todos
os endpoints de envio, inclusive boas-vindas e avisos da equipe; outros números
recebem HTTP 403. Lista vazia libera os destinatários normais, portanto só remover
a restrição ao encerrar a homologação.

```sh
php artisan messages:test-templates
php artisan messages:test-templates --send --run=homologacao_01
# Opcional: usar o texto de boas-vindas salvo para um ambiente:
php artisan messages:test-templates --organization=ID --send --run=homologacao_02
```

A prévia é o comportamento padrão. O envio usa dados fictícios e a identificação
`[TESTE WNFit]`, sem criar alunos, reservas ou cobranças. Os cinco modelos de eventos
compartilham o gerador de texto com as automações; boas-vindas usa o modelo padrão
ou o texto da organização indicada. O comando não depende de habilitar eventos.
Uma rodada usa chaves `welcome-test:0:<rodada>_<tipo>` persistidas pela ponte.
Manter o identificador da rodada ao repetir uma tentativa: não trocar para
contornar `incerto`. Texto/data diferentes na mesma chave são recusados, sem
reenviar. O comando interrompe no primeiro erro e não faz retry automático.
Os IDs retornados permitem conferir os recibos no endpoint privado `/messages/{id}`.

Em 14/11/2026, a rodada `pilot_20261114_v1` enviou os seis modelos padrão apenas
ao destinatário autorizado e os seis retornaram `delivered`. Foi usado o serviço
auxiliar privado `wnfit-whatsapp-pairing`, sem endpoint público; após a rodada,
seu código foi restaurado para não executar envios em futuros reinícios.
O script exato está em `scripts/whatsapp-template-smoke.ts` (Bun); não executar
novamente com um novo identificador sem solicitar outra rodada.

### Ordem de implantação

1. Configurar a restrição de destinatários na ponte e deixar eventos desabilitados
   na aplicação e worker. Atualizar a ponte e preservar o volume `/data`.
2. Atualizar a aplicação e executar as migrations pelo pre-deploy existente.
3. Atualizar o worker, com a mesma configuração da aplicação, e conferir a ponte.
4. Conferir a prévia e os recibos da rodada. A validação dos textos não substitui
   um teste de ponta a ponta dos gatilhos e da fila antes da liberação para alunos.
5. Para liberar o piloto real, escolher as organizações e eventos, remover a
   restrição de destinatários e habilitar eventos na aplicação e no worker.
   Manter o scheduler habilitado (`SCHEDULER_ENABLED=true`) na aplicação.

## Eventos de mensagens

Treino disponível, confirmação de agendamento e avisos financeiros usam a fila
`whatsapp` e o endpoint privado `POST /events`. A publicação/renovação de uma ficha
dispara treino disponível. A inclusão de aluno pelo gestor, reserva no portal do
aluno e reservas pelos links públicos disparam confirmação. Salvar preferências
não reprocessa fichas e reservas anteriores.

Os avisos financeiros são selecionados diariamente às 08h no fuso da aplicação,
por `php artisan messages:sync-billing`: dois dias antes, no vencimento e uma vez
no prazo de atraso configurado. Não há busca retroativa de datas perdidas nem
envio de cobranças de eventos. `billing:sync` continua sem enviar mensagens.

Para habilitar, implantar a ponte com `/events`, executar a migration de
`event_key`, atualizar aplicação e worker e configurar `WHATSAPP_EVENTS_ENABLED=true`
nos dois. A organização deve estar ativa e ter o módulo Mensagens habilitado
na tela interna; cada evento respeita sua preferência.
URL e token da ponte usam as variáveis existentes. O padrão é desabilitado.
O envio de eventos é independente de `MESSAGING_MODE` e de `WHATSAPP_WELCOME_DRIVER`.
Manter o scheduler e o worker `whatsapp` ativos. Desabilitar
`WHATSAPP_EVENTS_ENABLED` na aplicação e worker interrompe novos envios e cancela
os pendentes quando forem processados. Reiniciar processos após alterar configuração.

Uma chave única persiste cada ocorrência no Laravel e na ponte. O worker revalida
preferências, organização, telefone e origem antes de enviar: pagamento, remoção
ou alteração de horário da reserva e substituição da ficha cancelam mensagens
pendentes. Uma ocorrência cancelada não é recriada automaticamente. Falha ambígua
fica `incerto`, sem reenvio automático; `enviado` indica aceitação pela ponte,
não confirmação de entrega. Testes locais usam HTTP simulado e não enviam WhatsApp.

## Piloto de avisos ao gestor

O canal de avisos do gestor é independente das boas-vindas. Ao criar uma notificação
`workout_completed` para um proprietário ativo,
o sistema registra um envio em `staff_whatsapp_deliveries` e o enfileira em `whatsapp`.
O piloto é habilitado por organização e exige o módulo Mensagens. Todos os proprietários ativos com telefone
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
WHATSAPP_EVENTS_ENABLED=true
WHATSAPP_BRIDGE_URL=http://ENDERECO_PRIVADO_DO_SERVICO:3000
WHATSAPP_BRIDGE_TOKEN=MESMO_SEGREDO_DO_BRIDGE_TOKEN
QUEUE_CONNECTION=database
```

Todos os studios com Mensagens liberado na tela interna compartilham o numero WNFit.
Sem o módulo, cadastrar um aluno não prepara nem envia boas-vindas. `ativo` em
Mensagens deve estar habilitado para boas-vindas. O texto existente e reutilizado;
esta etapa nao altera o mecanismo de login nem envia senha ou link de redefinicao.
O gestor continua entregando as credenciais pelo fluxo atual.

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

Para uma empresa, desligar Mensagens na tela interna. Para interromper globalmente
os seis modelos, definir `WHATSAPP_EVENTS_ENABLED=false` e
`WHATSAPP_WELCOME_DRIVER=default` na aplicação e no worker, manter
`MESSAGING_MODE=manual`, atualizar cache e reiniciar processos. Jobs pendentes
revalidam a autorização antes de enviar; um envio já em andamento pode terminar.
Desativar uma preferência interrompe somente seu evento. Não há endpoint genérico
de disparo ou reenvio em massa.

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
