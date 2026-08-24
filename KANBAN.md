# WNFit Kanban

Este quadro organiza as proximas features por prioridade e reduz ambiguidade antes de iniciar implementacao.

## Prioridades

- P0: estabilidade, bugs bloqueantes e fluxos essenciais quebrados.
- P1: features centrais para operacao diaria do studio.
- P2: melhorias importantes, mas que podem esperar uma entrega principal.
- P3: ideias futuras ou refinamentos de baixo risco.

## Done

- Base Twilio WhatsApp para cobrancas: driver, configuracao, historico de mensagens e webhook de status.
- Configuracao de mensagens por studio: remetente WhatsApp, template editavel e preview operacional.
- Mensagem automatica de boas-vindas ao cadastrar novo aluno, com template configuravel e historico.
- Autenticacao: login, registro, logout e sessao atual.
- Dashboard inicial com indicadores operacionais.
- Alunos: listagem, filtros, cadastro, edicao e detalhe.
- Financeiro por aluno: assinatura, cobranca, envio e baixa de pagamento.
- Treinos: listagem, criacao, edicao, exercicios e vinculo treino-aluno.
- Catalogo de exercicios com filtros e cadastro.
- Agenda: criacao/edicao de agendamentos, recorrencia e alunos vinculados.
- Usuarios da organizacao: listagem, cadastro e atualizacao.
- Deploy Railway com ajustes para proxy.

## QA / Stabilize

- P0 - Validar agenda recem-integrada em fluxo real.
  - Testar criacao simples, edicao, recorrencia semanal/quinzenal/mensal e limite de repeticao.
  - Conferir vinculo de alunos e capacidade.
  - Garantir que edicao de uma ocorrencia nao altera indevidamente a serie.

- P0 - Rodar suite completa apos atualizacao do main.
  - `php artisan test`
  - `npm run build`

- P1 - Ajustar experiencia local no Windows.
  - O script `composer dev` usa `php artisan pail`, que depende de `pcntl` e falha no Windows.
  - Criar script alternativo sem Pail ou documentar comando local recomendado.

## Next Up

- P1 - Epic: Comunicacao e cobranca com aluno.
  - Objetivo: reduzir inadimplencia, esquecimento de vencimentos e trabalho manual de contato.
  - Primeiro foco: mensagens automaticas de cobranca via Twilio WhatsApp.
  - Entregar uma central simples com alunos, status financeiro, vencimento, ultima mensagem e acao de contato manual.
  - Manter arquitetura preparada para trocar/adicionar provedor sem reescrever o financeiro.

- P1 - Central de cobrancas.
  - Tela de cobrancas com filtros por status, vencimento e aluno.
  - Acoes rapidas: gerar cobranca, enviar mensagem, marcar como enviada manualmente, marcar como paga.
  - Destaques para vencidas, vencendo hoje e proximos 7 dias.

- P1 - Integracao Twilio WhatsApp.
  - Proximo passo: validar credenciais reais, sender WhatsApp e template aprovado em ambiente de homologacao.
  - Proximo passo: adicionar assinatura/validacao dos webhooks Twilio.

- P1 - Templates de mensagem.
  - Proximo passo: separar modelos por tipo: vencimento proximo, cobranca vencida, pagamento confirmado e lembrete de aula.
  - Separar templates aprovados no WhatsApp de mensagens livres dentro da janela de atendimento.

- P1 - Automacoes de cobranca.
  - Enviar aviso antes do vencimento.
  - Enviar lembrete no dia do vencimento.
  - Enviar cobranca apos atraso.
  - Evitar duplicidade de envio para a mesma cobranca/evento.

- P1 - Comunicacoes manuais.
  - Permitir envio manual a partir da central de cobrancas e do detalhe do aluno.
  - Reaproveitar templates e registrar historico.
  - Manter fallback para copiar mensagem caso o provedor esteja indisponivel.

- P2 - Relatorios operacionais.
  - Criar rota, tela e endpoints para indicadores de alunos, agenda, treinos e financeiro.
  - Priorizar indicadores que apoiem cobranca e comunicacao: inadimplencia, vencimentos e contatos feitos.

- P2 - Check-ins e presenca.
  - Tela para registrar presenca em aulas/agendamentos.
  - Indicadores de frequencia no detalhe do aluno.

- P2 - Planos e receitas.
  - CRUD de planos.
  - Visao consolidada de receitas previstas e recebidas.

## Backlog

- P2 - Melhorias de agenda.
  - Visual semanal/mensal mais rico.
  - Deteccao de conflito de horario por professor/sala.
  - Lista de espera quando capacidade estiver cheia.

- P2 - Evolucao de aluno.
  - Medidas, objetivos, observacoes clinicas e historico de progresso.

- P2 - Biblioteca de treinos.
  - Templates reutilizaveis.
  - Duplicar treino existente.
  - Arquivar/restaurar treinos.

- P3 - Permissoes mais granulares.
  - Perfis por papel: admin, professor, recepcao e financeiro.

- P3 - Notificacoes.
  - Eventos internos para vencimento, ausencia e proximas aulas.

## Icebox

- Integracoes externas de pagamento.
- Disparo automatico por WhatsApp/email.
- App do aluno.
- Multiunidade avancado.

## Proximo Plano Sugerido

1. Fechar QA da agenda e testes do main.
2. Corrigir script local para Windows.
3. Implementar base de mensagens: tabelas, templates, historico e interface `MessagingService`.
4. Implementar driver Twilio WhatsApp e webhooks de status.
5. Implementar Central de cobrancas com envio automatico e envio manual.
6. Criar automacoes de vencimento/atraso com protecao contra duplicidade.

## Definition of Ready

- Objetivo da feature claro.
- Tela ou fluxo principal definido.
- Endpoints necessarios mapeados.
- Dados existentes reaproveitados quando possivel.
- Criterios de aceite escritos antes da implementacao.

## Definition of Done

- Fluxo principal funcionando no navegador.
- Testes relevantes adicionados ou atualizados.
- `php artisan test` passando.
- `npm run build` passando.
- Sem migrations pendentes.
- Kanban atualizado com o resultado da entrega.
