# Eventos — participação, experiência e financeiro

## Entregas e branches

As branches são encadeadas: cada uma parte da entrega anterior, sem misturar o trabalho local de avaliação física.

| Entrega | Branch |
|---|---|
| Preparação de contas | `codex/event-participant-access` |
| Inscrição e perfis unificados | `codex/event-unified-registration` |
| Página do aluno e configuração | `codex/event-configurable-experience` |
| Presente e retirada | `codex/event-gifts` |
| Álbum privado e moderação | `codex/event-shared-album` |
| Pesquisa integrada | `codex/event-feedback` |
| Cobrança avulsa e integração financeira | `codex/event-paid-registration` |

## Participação

- O gestor escolhe público aberto ou exclusivo para alunos. Novos visitantes recebem cadastro `perfil_tipo=participante`, `status=participante`, sem conta, assinatura ou mensalidade automática.
- O filtro da lista de pessoas permite alternar entre alunos e participantes. Os indicadores de alunos ativos não incluem visitantes.
- Aluno autenticado confirma inscrição com seu cadastro. Nome e telefone não são sobrescritos por dados da inscrição.
- Telefone público não autentica nem revela perfil existente. Nesses casos, solicitar login ou atendimento pela equipe.
- Equipe busca pessoas da própria organização ou cria participante sem telefone obrigatório. Pessoas diferentes podem compartilhar telefone; ao criar outro perfil com número já existente, a equipe confirma explicitamente que é outra pessoa.
- Uma inscrição por pessoa/evento; capacidade é compartilhada entre inscrição pública, autenticada e pela equipe. A inclusão pela equipe usa `request_id` UUID para impedir duplicação em nova tentativa após perda de conexão; reutilizar a chave para outra pessoa retorna conflito.
- Inclusão pela equipe registra origem e responsável, sem registrar aceite ou consentimento de marketing em nome da pessoa.
- Inscrições antigas permanecem acessíveis por cookie/código. Não há vínculo automático por telefone; um cadastro antigo sem vínculo deve ser conferido pela equipe antes de uma nova inscrição autenticada.
- Ação de preparar acesso continua separada: gera conta se ausente e preserva senha existente. Senha provisória aparece somente na resposta inicial.

## Papéis

| Ação | Professor | Proprietário/administrador |
|---|---|---|
| Consultar evento e participantes | Sim | Sim |
| Incluir participante e registrar presença | Sim | Sim |
| Aprovar/remover fotos e confirmar entrega de presente | Sim | Sim |
| Preparar acesso a partir do evento | Não | Sim |
| Configurar evento, preço e recursos; encerrar | Não | Sim |
| Confirmar pagamento de inscrição | Não | Sim |

As verificações são feitas nas APIs. A restrição de pagamento também cobre as rotas financeiras do perfil e da central.

## Recursos e portal

A gestão contém Visão geral, Participantes e Configurações. Recursos de um novo evento começam desativados. A pesquisa de eventos legados sem configuração explícita preserva o comportamento anterior.

- Presente: nome, frase, quantidade e orientação de retirada. Uma unidade garantida por participante; a quantidade habilitada deve cobrir os inscritos e limita novas inscrições. Revelação salva um snapshot imutável. Atualização, outro aparelho e entrega repetida não criam outro resultado/recebimento. A raspagem possui alternativa por botão.
- Para a pessoa sem celular, a equipe pode registrar a entrega do presente garantido diretamente. O mesmo snapshot e responsável ficam salvos; ao entrar posteriormente, a pessoa verá o presente e a retirada confirmada.
- Álbum: câmera/galeria, prévia, redução no navegador, progresso, autorização de imagem e aprovação. O autor vê seu envio pendente; os demais veem somente aprovados. Remoção oculta a foto, preservando o registro e permitindo revisão pela equipe.
- Pesquisa: nota 1–5 e comentário opcional, liberados após encerramento manual e presença. Conta e código público atualizam o mesmo registro. A nota não condiciona o presente.
- Desativar recurso preserva registros. Álbum permanece utilizável após encerramento enquanto estiver habilitado. Eventos cancelados ficam indisponíveis para a experiência do aluno.
- Boas-vindas são confirmadas uma vez por inscrição. O menu e as APIs dependem de vínculo com evento publicado da organização.

## Financeiro

Evento pago gera uma única `Cobranca` por inscrição, com `origem=evento`, descrição com o nome do evento e `assinatura_id=null`. Vencimento é a data da inscrição; preço fica congelado após a primeira inscrição. Participação ocupa vaga mesmo com pagamento pendente e não bloqueia os recursos habilitados.

A equipe de gestão pode registrar pagamento durante a inclusão ou posteriormente. Forma e data são explícitas; métodos: PIX, crédito, débito, dinheiro, transferência e boleto. Repetir a operação preserva um único pagamento. Cobranças aparecem na gestão do evento, no perfil e no financeiro geral, diferenciadas de mensalidades. Mensagens de cobrança de eventos continuam manuais e usam descrição da inscrição.

Ao cancelar evento, cobranças abertas são canceladas e pagamentos existentes permanecem no histórico com indicação de devolução a conferir. Não há gateway, débito automático, pagamento parcial ou reembolso automático. Evento cancelado não é reaberto; preparar outro evento para uma nova edição.

## APIs principais

- Público: `/api/public/events/{slug}` e ações existentes de inscrição, recuperação, presença e pesquisa.
- Aluno: `/api/student/events`, detalhe por ID e ações de inscrição, boas-vindas, presença, presente, fotos e pesquisa.
- Gestão: `/api/events/{id}/people`, `/registrations`, `/settings`, `/photos` e ações de pagamento/entrega por inscrição.
- Fotos são acessadas por endpoints autenticados do evento, inclusive miniaturas. Paths do storage não são publicados. APIs do aluno usam respostas privadas sem cache.

## Publicação e armazenamento

1. Copiar o banco antes da publicação; aplicar as migrations em ordem e gerar o frontend. As alterações são aditivas, exceto a retirada da unicidade por telefone e a aceitação de campos ausentes em inscrições pela equipe. Não associam registros antigos automaticamente.
2. O Docker inclui GD, JPEG/WebP e o adaptador S3. Configure bucket privado e as variáveis AWS já previstas no disco `s3`; `EVENT_PHOTO_DISK=s3`.
3. Confirmar upload, leitura autenticada e permanência de uma foto após novo deploy. Só então definir `EVENT_PHOTO_STORAGE_PERSISTENT=true`. Sem essa confirmação, produção rejeita uploads com 503; local usa disco local. A confirmação real do bucket não foi realizada no ambiente desta entrega.
4. Começar com `EVENT_EXPERIENCE_PILOT_USERNAME=wendel`. Com a variável preenchida, só esse usuário acessa a nova experiência, além de precisar estar inscrito. Após validar, esvaziar a variável e reconstruir/limpar o cache de configuração para liberar todos os participantes vinculados. As APIs aplicam o mesmo controle do menu.
5. Conferir evento de 18/10: gratuito, público escolhido, capacidade 20, presente nécessaire habilitado com quantidade 20 e instrução de retirada; ativar álbum somente com armazenamento pronto. Conferir pesquisa, horário e vínculo de todos os participantes.

## Verificação e limites

- Validação final: 103 testes, 1.382 assertions, build de produção e revisão no navegador passaram.
- Testes cobrem perfis, público, papéis, telefone compartilhado, lotação, vínculos antigos, piloto, snapshot/estoque/entrega, autorização de imagem, moderação, leitura privada, pesquisa única, cobrança sem assinatura, baixa repetida e cancelamento.
- A configuração do PHPUnit força ambiente de teste e SQLite em memória, evitando que variáveis de outro ambiente direcionem a suíte para o banco da aplicação.
- O ambiente de revisão usa cópia do banco na porta 8001. Dados de QA são locais. Produção não foi alterada.
- Testes de banco foram executados em SQLite; concorrência real em MySQL e implantação Docker/Railway precisam de homologação no ambiente de destino.
- S3 remoto e persistência após deploy são pendências de configuração/validação externa, não demonstradas por um disco local ou fake de teste.
- No navegador local foram conferidos retorno após login, boas-vindas, presença, presente, envio/miniatura pendente, aprovação, retirada, baixa de inscrição paga e atualização dos totais. A experiência do aluno foi conferida em viewport de 390 × 844, sem rolagem horizontal.
- A interface aceita imagens que o navegador consegue abrir, reprocessadas como JPEG. Se o aparelho não conseguir decodificar HEIC, a tela orienta escolher JPG/PNG/WebP; não há conversor HEIC no servidor.

## Refinamento da experiência do aluno

Branch: `codex/student-event-experience` (sobre a entrega de inscrições pagas).

- A página do evento organiza apresentação/presença, entrada do álbum, pesquisa e presente, nessa ordem. As fotos e seus pedidos de carregamento ficam exclusivamente em `/aluno/evento/:id/album`, com retorno ao evento.
- A avaliação usa cinco estrelas acessíveis e comentário opcional. O formulário fica visível com orientação enquanto aguarda encerramento manual e presença; as regras da API permanecem.
- Presente opcional abre em diálogo nativo após as boas-vindas, enquanto não revelado. Fechar não revela nem registra retirada. O diálogo só abre automaticamente uma vez por entrada na página; o botão ao final permite retomar.
- Após revelar, o servidor mantém o resultado e o cartão passa a informar nome/status do brinde, com acesso aos detalhes. Revelação e retirada física continuam separadas. Retornar/recarregar não abre automaticamente um presente já revelado.
- Diálogo permite Escape, fechamento explícito e retorno de foco; rolagem de fundo bloqueada. A revelação também possui botão acessível como alternativa ao gesto de raspar.
- Validação: build, 103 testes PHP (1.382 assertions), navegador desktop e 390 × 844; boas-vindas → abertura automática → fechar/reabrir → revelar → recarregar sem pop-up; navegação do álbum, avaliação salva e evento sem recursos opcionais. Alterações e dados de QA exclusivamente locais.
