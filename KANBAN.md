# WNFit — Kanban

Reestruturado em 07/11/2026. O quadro anterior está no histórico do Git (último commit de `KANBAN.md` antes desta data). Análise que originou este plano: [Análise de produto e prioridades](https://claude.ai/artifact/KgK75fpd1c1zFcupKqWbWN).

## Para onde vamos

**Visão.** Ser o sistema de gestão de qualquer negócio de saúde e bem-estar que acompanha pessoas treinando: personal, studio, box de CrossFit, assessoria de corrida, academia de luta, escola de futevôlei, academia.

**Foco até mar/2027.** Personal trainers e studios pequenos (até ~150 alunos). É onde o produto atual já entrega valor e onde a venda é mais curta. Cada decisão técnica, porém, deve funcionar para outros segmentos. Nada de "personal" ou "musculação" fixo no código.

**Métrica norte.** Organizações com pelo menos 3 alunos que treinaram pelo portal nos últimos 7 dias.

**Funil acompanhado.** Visita → cadastro → 1º aluno → 1ª ficha publicada → 1ª sessão concluída pelo aluno → assinatura paga.

## Decisões registradas (07/11/2026)

| Tema | Decisão |
| --- | --- |
| Cobrança | Novos ambientes já nascem com plano e cobrança. A gratuidade é concedida por cupom (período grátis) para ambientes selecionados. Validar disposição a pagar faz parte do teste. Lançar com preço provisório (MON-02) e revisar com dados. |
| Processo | Todo card vira spec em `docs/specs/` antes de ir para o desenvolvimento. Produto escreve, Wendel aprova e os agentes de dev executam (ver `docs/specs/README.md`). |
| Segmento | Começar por personal e studio, sem perder do radar os demais segmentos (ver "Radar — multissegmento"). |
| Eventos | Módulo adicional com custo extra. Precisa de uma revisão de entrega de valor antes de voltar. Fica escondido do menu até lá. |
| Gateway | Ainda não escolhido. Spike comparativa no card MON-01. |
| Comunicação | Segue assistida (copiar/abrir WhatsApp). Disparo automático só como add-on futuro. |
| Execução | Wendel + agentes (Codex/Claude). Ondas de cerca de 2 semanas, com no máximo 2 cards em andamento. |

## Como usar este quadro

- Cada card tem um ID (`TEMA-NN`), um título, o resultado esperado e critérios de aceite curtos. Antes de ir para o desenvolvimento, ganha uma spec em `docs/specs/<ID>.md`.
- Fluxo: **Agora → Em andamento → Em validação → Feito**. "Próximo" e "Depois" só sobem quando a onda anterior fecha.
- No máximo **2 cards em andamento** ao mesmo tempo, porque toda entrega passa pela revisão do Wendel.
- Ao concluir um card, mova-o para "Feito" com data e branch/PR, e atualize a documentação afetada em `docs/knowledge`.
- Bugs novos entram em "Agora" se afetam dados, cobrança ou o cliente final. Os demais entram em "Próximo".

Temas: **CONF** confiança e qualidade · **FUN** funil e medição · **MON** monetização · **ATV** ativação do gestor · **ALU** experiência e retenção do aluno · **DS** design system · **DIST** distribuição · **OPS** operação e engenharia · **SEG** multissegmento.

---

## Em andamento

_(vazio — puxar de "Agora")_

## Em validação

_(vazio)_

---

## Agora — Onda 1: confiança e funil (09/11 → 20/11)

Objetivo: o produto não pode errar com quem chega pelos anúncios, e cada cadastro precisa ser medido.

- **CONF-01 · Remover script do Figma de produção** — `resources/views/app.blade.php` carrega `mcp.figma.com/.../capture.js` em todas as páginas logadas.
  - Aceite: script removido. Se for útil no dev, carregar só com `app()->isLocal()`. Conferir em produção que não aparece.
- **CONF-02 · Revisão de acesso entre organizações** — endpoint por endpoint, incluindo usuário com múltiplos vínculos (hoje usa a primeira organização ativa).
  - Aceite: teste de isolamento para cada recurso (alunos, treinos, financeiro, agenda, reservas, eventos, notificações). Usuário com 2 organizações acessa só a organização selecionada.
- **CONF-03 · Mensagem de cobrança correta** — tratar vencida e a vencer separadamente, com acentos e o nome do plano escrito corretamente.
  - Aceite: template de vencida diz "venceu em" com dias de atraso, e o template a vencer diz "vence em". Teste cobrindo os dois casos.
- **CONF-04 · Usernames de aluno únicos por organização** — hoje são globais e usam o primeiro nome.
  - Aceite: unicidade por organização (ou login por telefone, ver ALU-01). Migração preserva acessos existentes. Conta demo deixa de bloquear nomes comuns.
- **CONF-05 · Avaliação física: comparação com a anterior** — "Anterior" e "Variação" aparecem como "—".
  - Aceite: variação calculada contra a avaliação imediatamente anterior, com teste.
- **CONF-06 · Camada de formatação pt-BR (`format.js`)** — datas, data relativa, moeda, telefone e capitalização em um só módulo.
  - Aceite: itens 4–17 do QA de 07/11 corrigidos (datas ISO, "6 minutes ago", "Novembro De", telefone sem máscara, paginação com 8 alunos, sábado cortado na agenda, eixo negativo e período cortado no Financeiro, fuso exibido na reserva, falta de acentos).
- **CONF-07 · Status do aluno com uma única fonte** — Painel e Alunos mostram status diferentes para o mesmo aluno.
  - Aceite: status calculado no backend e reutilizado em todas as telas.
- **CONF-08 · LGPD mínima no cadastro** — termos de uso, política de privacidade e consentimento. O gestor é o controlador dos dados dos alunos.
  - Aceite: páginas públicas publicadas, aceite registrado com data no cadastro e links no rodapé e no portal do aluno.
- **FUN-01 · Analytics de produto e funil** — eventos: `signup_started`, `signup_completed`, `student_created`, `workout_published`, `student_first_login`, `session_completed`, `checklist_step_done`.
  - Aceite: ferramenta escolhida (PostHog, GA4 ou similar), sem dados pessoais de aluno nos eventos e funil visível num painel.
- **FUN-02 · Landing no `/`, Pixel e UTMs** — o `/` ainda redireciona para `/entrar`.
  - Aceite: `/` serve a landing. UTMs gravadas na organização no cadastro. Pixel Meta na landing e no cadastro, com banner de cookies.
- **ATV-01 · Esconder Eventos e Comunicações do menu** — evita sinal de produto incompleto.
  - Aceite: itens fora da navegação. Eventos continua acessível para organizações com o módulo ativo.
- **ALU-00 · Validar portal do aluno em produção** — roteiro no celular real: login, troca de senha, ficha, sessão, retomada, histórico.
  - Aceite: roteiro executado em Android e iPhone e bugs registrados como cards.
- **OPS-01 · Suíte e build verdes no `main`** — o último registro é de 17/10.
  - Aceite: `php artisan test` e `npm run build` passando, resultado registrado. Se possível, CI rodando os dois a cada PR.

## Próximo — Onda 2: monetização e ativação (23/11 → 18/12)

Objetivo: cobrar pelo produto e encurtar o caminho até o primeiro aluno treinando.

- **MON-01 · Spike de gateway** — comparar Asaas, Mercado Pago, Pagar.me e Stripe.
  - Aceite: tabela com taxas (PIX, cartão, boleto), assinatura recorrente, cupom/trial nativo, webhooks, split (para a futura cobrança do aluno) e esforço de integração. Recomendação escolhida pelo Wendel.
- **MON-02 · Preço provisório** — lançar com um preço provisório agora e ajustar com dados (decisão de 07/11). Proposta para aprovação do Wendel:
  - **Personal** R$ 59/mês, até 30 alunos ativos, 1 profissional.
  - **Studio** R$ 149/mês, até 150 alunos ativos, até 5 profissionais.
  - **Pro** R$ 299/mês, alunos ilimitados, múltiplas unidades.
  - Anual com 2 meses grátis. Add-ons (Eventos, Cobrança automática do aluno) com preço a definir.
  - Aceite: valores aprovados e publicados na landing. Revisão marcada para fev/2027 com base em conversão de trial, cancelamentos e 5–10 conversas com clientes.
- **MON-03 · Assinatura do SaaS no cadastro** — escolha de plano e pagamento ao criar o ambiente.
  - Aceite: organização com status `trial | ativa | inadimplente | cancelada`. Webhooks idempotentes. E-mail de confirmação.
- **MON-04 · Cupons de gratuidade** — cupom concede N dias grátis (ou % de desconto por N meses) a ambientes selecionados.
  - Aceite: cupom com código, validade, limite de usos e plano aplicável. Uso registrado por organização. Aviso 7 e 2 dias antes do fim.
- **MON-05 · Limites e bloqueio suave** — ao exceder alunos ativos ou com pagamento vencido.
  - Aceite: o aviso aparece primeiro e depois o modo somente leitura. Nunca apaga dados, e o portal do aluno continua funcionando por um período de tolerância.
- **MON-06 · Backoffice interno (super-admin)** — ver organizações, plano, status, uso e cupons. Conceder trial e acessar suporte.
  - Aceite: área restrita ao time WNFit, com auditoria das ações.
- **ALU-01 · Convite do aluno por link mágico** — o professor envia o link pelo WhatsApp e o aluno entra sem usuário e senha provisória. Login por telefone + código como alternativa.
  - Aceite: link de uso único com expiração. Login por senha mantido para quem já usa.
- **ATV-02 · Dados de exemplo e estados vazios úteis** — conta nova não deve ver gráficos zerados.
  - Aceite: opção "explorar com dados de exemplo" (removível com 1 clique). Cada tela vazia mostra o próximo passo.
- **ATV-03 · Onboarding por tipo de negócio** — ampliar o perfil do checklist (`onboarding/profile.js`) para personal, studio, box, corrida, luta e esportes de areia.
  - Aceite: cadastro pergunta o tipo e o checklist se adapta. O tipo fica gravado para análise de segmento.
- **DS-01 · Tokens únicos** — um `tokens.css` semântico. Remover o bloco legado (`#9be100`/`#5aa400`), a flag `visualRefresh` e os namespaces paralelos (`--lp-*`, `--lime` da ajuda).
  - Aceite: nenhuma cor HEX fora de `tokens.css` (Stylelint). Landing, app, portal e ajuda consomem os mesmos tokens.
- **DS-02 · Escalas fechadas** — fonte 12/14/16/20/24/32 (piso de 12px), raio 8/12/16/pill, espaçamento em múltiplos de 4.
  - Aceite: nenhum `font-size` abaixo de 12px, e raios e fontes fora da escala removidos.
- **DS-03 · Componentes Vue base** — `WnButton`, `WnInput`, `WnSelect`, `WnCard`, `WnStatusBadge`, `WnTabs`, `WnTable`, `WnDialog`, `WnEmptyState`, `WnPageHeader`, `WnFilterBar`. Catálogo em `/dev/ui` (só local).
  - Aceite: Painel, Alunos, Ficha do aluno, Treinos e Financeiro usam só componentes base.
- **DS-04 · Mobile da gestão** — filtros em painel inferior, KPIs compactos e "Mais" na barra inferior.
  - Aceite: em 375px, a lista de alunos e treinos aparece na primeira tela.
- **DS-05 · Ficha do aluno com abas e resumo** — trocar a grade 3×2 por abas e unificar o resumo (status, plano, vencimento, última sessão). Remover a conta duplicada da sidebar (decisão de 26/10).
  - Aceite: validado em desktop e 375px.
- **DIST-01 · Marca do negócio na reserva e no portal** — logo, nome e cor do cliente, "Horário de Brasília" e selo "Feito com WNFit".
  - Aceite: logo configurável em Configurações. O selo leva para a landing com UTM.

## Depois — Onda 3: retenção e expansão (jan → mar/2027)

- **ALU-02 · Recordes e progressão de carga por exercício** no portal do aluno.
- **ALU-03 · Sequência semanal** com dados reais e meta de frequência da ficha.
- **ALU-04 · Lembrete de treino** — link de WhatsApp gerado pelo professor, sem automação.
- **ATV-04 · Check-in e presença em aulas** — alimenta o card "Check-ins hoje", que hoje fica sempre zerado.
- **ATV-05 · Lista de espera** quando a aula estiver cheia.
- **ATV-06 · Papéis granulares** — admin, professor, recepção e financeiro, com permissões oficiais documentadas.
- **ATV-07 · Relatórios** — inadimplência, retenção de alunos, frequência e receita prevista × recebida.
- **MON-07 · Add-on: cobrança automática do aluno** (PIX recorrente e cartão pelo gateway da MON-01), em vez da baixa manual.
- **MON-08 · Add-on: Eventos** — revisão de entrega de valor (quem usa, para quê, quanto pagaria) antes de reativar. Base técnica pronta (inscrição, check-in, álbum, brinde, pesquisa, cobrança avulsa).
- **DIST-02 · Programa de indicação** — um mês grátis para quem indica e para o indicado (usa os cupons de MON-04).
- **DIST-03 · Estudos de caso reais na landing** — com autorização dos clientes.
- **DS-06 · Auditoria de acessibilidade** (axe + teclado) nas 10 telas principais.

## Radar — multissegmento

Não entra em onda até haver clientes do segmento pedindo. Serve para guiar decisões de arquitetura agora.

- **SEG-01 · Terminologia configurável** — aluno/atleta/cliente, treino/WOD/planilha, professor/coach.
- **SEG-02 · CrossFit / funcional** — WOD do dia por turma, ranking/placar, registro de PR.
- **SEG-03 · Assessoria de corrida** — planilhas por pace/zona, integração Strava/Garmin, provas e eventos (sinergia com MON-08).
- **SEG-04 · Lutas** — graduação e faixas, exames, presença mínima para graduar.
- **SEG-05 · Esportes de areia / escolas** — turmas por nível, reposição de aula, pacotes de aulas avulsas.
- **SEG-06 · Academias maiores** — múltiplas unidades avançadas, controle de acesso (catraca/biometria), comissão de professores.

## Icebox

- App nativo (o portal PWA atende por enquanto).
- Disparo automático de WhatsApp/e-mail via Twilio ou API oficial. A base existe e está inativa. Pode virar add-on.
- Assistente de IA para montar treinos.
- Gamificação além de recordes e sequência.
- `composer dev` no Windows (Pail depende de `pcntl`). Documentar um comando alternativo quando incomodar.

---

## Feito — o que o produto já tem (base em 07/11/2026)

| Área | Entregue |
| --- | --- |
| Conta e onboarding | Cadastro do ambiente (organização, unidade, plano operacional), login, checklist de primeiros passos (02/11) |
| Alunos | Lista, filtros, cadastro, ficha com histórico, foto, modalidade presencial/online, transferência de unidade |
| Avaliação física | Registro e histórico de avaliações |
| Treinos | Catálogo com 275 exercícios ilustrados, seleção visual, biblioteca, montador, duplicar/arquivar, publicação por snapshot, validade e renovação |
| Portal do aluno | Login próprio, troca de senha, ficha vigente, execução por séries, descanso, retomada, histórico, PWA |
| Financeiro | Resultados mensais, cobranças, recorrência, baixa com forma de pagamento, mensagem assistida via WhatsApp |
| Agenda | Horários, recorrência, capacidade, link público de reserva com revalidação |
| Eventos | Inscrição, acesso, check-in, álbum moderado, brinde, pesquisa, cobrança avulsa (usado em 18/10, hoje oculto) |
| Notificações | Sino, toasts, tempo real, alertas de treino por WhatsApp para o gestor |
| Configurações | Equipe, planos, unidades, horários, mensagens |
| Suporte | Central de ajuda ilustrada |
| Infra | Deploy Railway |

Registro de entregas a partir daqui:

| Data | Card | Branch / PR | Observação |
| --- | --- | --- | --- |
| | | | |

## Definition of Ready

- Card com resultado esperado e critérios de aceite escritos.
- Tela ou fluxo principal definido. Para UI nova, usar componentes base (DS-03) quando já existirem.
- Endpoints e dados mapeados, reaproveitando o que existe.
- Impacto em isolamento entre organizações avaliado.

## Definition of Done

- Fluxo principal funcionando no navegador, em desktop e 375px quando houver UI.
- Testes relevantes adicionados, `php artisan test` e `npm run build` passando.
- Sem migrations pendentes, cores fora de `tokens.css` ou textos sem acento.
- Datas, moeda e telefone formatados por `format.js`.
- Documentação em `docs/knowledge` atualizada e card movido para "Feito" com data.
