# Mapa tecnico WNFit

FigJam: https://www.figma.com/board/66xIPF4GlUuCcGufNlMwOU

Criado na equipe Wendel Caldas's team, a partir das migrations, modelos, rotas, controllers, middlewares e services do repositorio em 04/10/2026. O quadro contem tres diagramas editaveis, com fontes Mermaid nesta pasta.

1. `01-gestao-financeiro.mmd`: organizacao, equipe, alunos, planos, assinaturas, cobrancas, pagamentos, mensagens e registros de apoio.
2. `02-treino-agenda-eventos.mmd`: acesso do aluno, biblioteca, prescricao publicada, sessoes, agenda e eventos publicos.
3. `03-fluxo-operacional.mmd`: processos e persistencia, incluindo guards, troca de senha inicial, snapshots, recorrencia financeira, WhatsApp manual/Twilio e participacao em eventos.

## Como ler

As entidades usam os nomes reais das tabelas, com um recorte dos campos principais. PK identifica chave primaria, FK chave estrangeira e UK unicidade individual. Unicidades compostas relevantes estao descritas nos campos ou abaixo. As cardinalidades representam as restricoes das migrations; nao sao uma garantia de todas as regras de negocio. Tabelas de organizacao, usuario e aluno reaparecem no segundo diagrama como referencias ao mesmo objeto.

No fluxo operacional, cilindros verdes representam persistencia. Setas tracejadas indicam consultas ou dependencias, nao necessariamente comunicacao assincrona. A implementacao atual usa Laravel e Vue; a landing em `/site` e HTML servido pelo Laravel. A raiz redireciona para `/entrar`.

## Particularidades do modelo atual

- `organizacao_usuario` vincula equipe a empresa, com papel/status e UNIQUE(organizacao_id, user_id). Controllers filtram entidades por organizacao; varios selecionam a primeira organizacao associada ao usuario. O mapa nao presume um seletor global de empresa ou controle completo por papel.
- `student_accounts` e separado de `users`: aluno_id e username sao unicos. O guard student compartilha a infraestrutura de sessao HTTP do Laravel com o guard web, mas tem autenticacao propria. A credencial tem versao para invalidar sessoes apos redefinicao.
- `student_workout_plans.snapshot` preserva a prescricao e `student_workout_sessions.snapshot` preserva o dia executado. Nao existem tabelas separadas de series executadas: o progresso esta no JSON `progress`.
- `aluno_treino` permanece como vinculo historico; a ficha corrente do portal e obtida de `student_workout_plans`.
- Catalogo global de exercicios: organizacao_id nulo. Exercicios privados pertencem a uma organizacao.
- `cobrancas` pode ter assinatura_id nulo. UNIQUE(assinatura_id, competencia) limita competencias repetidas de uma assinatura.
- `pagamentos.cobranca_id` nao tem UNIQUE nas migrations. O modelo usa hasOne e o service registra um pagamento com lock/transacao e protecao contra repeticao. Por isso o ER mostra 0:N fisico; o fluxo usual e um pagamento por cobranca.
- Mensagens podem ter vinculo direto opcional com cobranca; `cobranca_mensagem` permite agrupar varias cobrancas em uma mensagem.
- `billing:sync` e agendado diariamente e tambem usado por consultas que atualizam cobrancas. Sincronizar competencias nao envia mensagens automaticamente.
- `agendamentos.serie_id` agrupa recorrencias e nao e FK para uma tabela de series. `agendamento_aluno` tem UNIQUE(agendamento_id, aluno_id).
- `events` e `cobranca_eventos` sao dominios distintos: encontros publicos versus auditoria financeira. `event_registrations` nao tem aluno_id; suas inscricoes sao independentes de alunos/mensalidades, com UNIQUE(event_id, phone), hashes e cookie individual.
- `aulas`, `checkins` e `receitas` sao objetos existentes independentes. Nao foi inventado vinculo entre aula e agendamento, receita e pagamento ou sessao de treino e check-in.
- Infraestrutura Laravel: sessions, password_reset_tokens, cache, cache_locks, jobs, job_batches e failed_jobs. A existencia das tabelas nao significa que mensagens sejam enviadas por uma fila; o fluxo mapeado segue os services atuais.

Nenhum registro de aluno, credencial real, dado de saude, configuracao privada ou segredo de integracao foi enviado ao FigJam. O material contem estrutura de codigo e esquema, sem dados do banco.
