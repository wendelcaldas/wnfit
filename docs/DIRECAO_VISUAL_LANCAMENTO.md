# Direção visual para o lançamento do WNFit

Decisões mais recentes da gestão: [painel e experiência operacional aprovados em 26/10/2026](#painel-e-experiência-operacional-aprovados-em-26102026). A restrição inicial ao portal do aluno, descrita abaixo, é histórica; a identidade foi posteriormente aplicada à gestão conforme [validação administrativa](VALIDACAO_UI_ADMIN.md).

Pesquisa em 03/10/2026, com a primeira aplicação aprovada para o portal do aluno.

## Aplicação aprovada em 03/10/2026

A primeira aplicação foi autorizada somente para a visão do aluno: login, início, ficha, execução, histórico e troca de senha. Os tokens e ajustes de componentes ficam restritos a `.student-app` e `.student-login-page`; o painel do professor não recebe este redesign.

A proposta gerada foi traduzida para componentes reais, preservando o emblema existente e os fluxos do MVP. O progresso semanal usa a quantidade real de sessões concluídas e a frequência da ficha; marcadores fictícios de dias não foram incluídos. A execução mantém os campos de carga e repetições, salvamento, retomada, descanso e confirmação de conclusão.

Validação: build de produção, 13 testes do portal com 101 assertions e inspeção no navegador em 390 × 844 e 1366 × 900. Login, salvamento de série, descanso, retomada, conta e tela de senha foram conferidos. Nenhuma senha foi alterada durante a revisão visual.

## Referências e limites da análise

- [WellTrainer](https://welltrainer.com.br/): landing page pública e representações do produto. Títulos grandes e pesados, fundo claro quente, áreas pretas, verde elétrico e chamadas de ação evidentes. Não foi acessada a área autenticada; esta análise não equivale a testar seus fluxos internos.
- [Hevy](https://www.hevyapp.com/): exemplos públicos de registro de séries, descanso e histórico. Referência para dar prioridade aos dados e à ação durante o treino.
- [Fitbod](https://fitbod.me/): referência complementar para apresentação de planos e orientação de exercícios; recursos e telas apresentados no site público.
- [StayActive, Capi Product](https://contra.com/p/WErccLNq-fitness-platform-or-app-and-dashboard-design-or-stay-active): estudo de design envolvendo aplicativo do aluno e painel do professor. Referência para uma identidade compartilhada entre duas experiências.
- [Fitness Trainer App, Muhammad Usman](https://www.behance.net/gallery/231755225/Fitness-Trainer-App-Personalized-Coaching-UI): conceito publicado com preto e verde; referência exploratória de atmosfera, sem comprovação de usabilidade. A página completa não abriu na consulta; descrição disponível nos resultados de pesquisa.

## Direção recomendada

Identidade esportiva contemporânea, clara e acolhedora. Branco predominante, preto para hierarquia e blocos de destaque, verde para ação e progresso. Uma linguagem visual compartilhada, com mais espaço no portal do aluno e maior densidade no painel profissional.

### Paleta proposta

| Papel | Cor | Aplicação |
|---|---|---|
| Superfície | #FFFFFF | Cartões, formulários e áreas de leitura |
| Fundo | #F5F6F4 | Separação discreta das superfícies |
| Preto | #151715 | Texto, títulos e destaque principal |
| Verde de marca | #A3E635 | Ação principal com texto preto |
| Verde escuro | #365314 | Links e texto verde sobre fundo claro |
| Verde suave | #EEF8DE | Seleção e progresso secundário |
| Cinza | #62685F | Texto secundário |
| Borda | #E3E7DF | Separação de cartões e campos |

Valores são propostas, não cores extraídas do WellTrainer. Conferir contraste no componente real; não usar verde luminoso como texto pequeno sobre branco. Estados de erro precisam de cor semântica, mensagem e ícone próprios.

### Regras de acabamento

- Preservar Plus Jakarta Sans no corpo e Space Grotesk nos títulos; explorar pesos e tamanhos antes de trocar fontes.
- Escala de espaçamento de 8 px. Controles com área de toque mínima de 44 px.
- Raios de 12 px em campos, 16 px em cartões comuns e 24 px no destaque principal. Evitar arredondamento máximo em todos os elementos.
- Uma ação principal evidente por contexto. Botões secundários com fundo neutro ou contorno.
- Ícones com a mesma espessura e tamanho; números de séries, carga e descanso alinhados e legíveis.
- Sombras discretas para indicar elevação. Gradientes e decoração restritos aos blocos de marca.
- Transições curtas de 150–200 ms, respeitando preferência por movimento reduzido.
- Fotografias reais, quando disponíveis, na entrada e apresentação. Durante a sessão, imagens devem explicar o exercício.

## Aplicação às telas

1. Login: marca mais presente, mensagem curta, formulário branco e botão verde. No desktop, composição com área escura e fotografia opcional; no celular, foco no formulário.
2. Início do aluno: destaque para continuar/iniciar treino, progresso semanal com dados reais e histórico compacto. Sem indicadores de calorias ou duração estimada sem dados confiáveis.
3. Ficha: divisões A/B/C evidentes, resumo de objetivo e validade, lista de exercícios com metadados alinhados.
4. Execução: carga, repetições e conclusão como elementos prioritários; progresso compacto e descanso legível. Evitar fotos grandes e excesso de cartões nessa tela.
5. Professor: navegação e cabeçalhos consistentes, tabelas com bom alinhamento e editor compacto. Dar peso visual a salvar, visualizar e publicar, sem competição entre ações.

## Ordem de implementação

1. Consolidar tokens de cor, tipografia, espaçamento, raios e estados; revisar cores literais espalhadas no CSS.
2. Produzir uma proposta visual navegável de login, início do aluno e execução; validar em celular e desktop.
3. Aplicar os componentes aprovados à ficha, histórico e conta.
4. Harmonizar painel, alunos e montador do professor.
5. Revisar contraste, foco de teclado, carregamento, vazio, erro, salvamento e responsividade antes do lançamento.

Critério de conclusão: identidade consistente nas telas prioritárias, ações claras, boa leitura no celular e nenhuma regressão nos fluxos aprovados do MVP.

## Ajuste para uso mobile e PWA

Divisões de treino agora usam uma grade que se reorganiza em duas ou três colunas, sem rolagem lateral. O login acompanha a altura dinâmica do viewport e reduz a apresentação em telas baixas, sem fixar uma altura que corte controles. O formulário foi conferido sem rolagem em 320 × 568, 375 × 667 e 390 × 844. Campos mantêm fonte de 16 px para evitar zoom automático em navegadores móveis. Conteúdo adicional, teclado, ampliação de texto e sessões longas podem exigir rolagem vertical; não há bloqueio que impeça acessar campos ou mensagens. A tela de senha também recebeu espaçamento compacto para aparelhos baixos. Estas alterações continuam restritas ao portal do aluno.

## Painel e experiência operacional aprovados em 26/10/2026

Direção aprovada pelo usuário após revisão do painel e do modal de compromissos: descomplicar a operação, mostrar primeiro o que ajuda a decidir e revelar os detalhes quando solicitados. Esta orientação complementa a identidade existente; não autoriza retirar indiscriminadamente textos, indicadores ou orientações úteis.

### Princípios para os próximos trabalhos

- Priorizar informações reais e relevantes à tarefa e à operação do negócio, com definições claras; não inventar números para preencher a interface.
- Manter hierarquia clara: contexto, informação principal e ação. Reduzir decoração, repetição e mensagens transitórias que competem com o conteúdo.
- Buscar densidade equilibrada. Compactar sem sacrificar leitura, respiro, alvos de interação ou descoberta das ações. A revisão das pastilhas passou por versões mais altas e mais finas antes da aprovação do meio-termo.
- Usar divulgação progressiva: resumo no painel, detalhes na data selecionada e gestão completa na agenda. Evitar acrescentar ações que o fluxo não oferece.
- Preservar os padrões de acessibilidade: nomes acessíveis, semântica coerente, foco visível, teclado, fechamento de diálogos e retorno do foco. A revisão não substitui uma auditoria completa de acessibilidade.
- Tratar responsividade como comportamento do conteúdo: alinhar blocos lado a lado; empilhar quando necessário; permitir rolagem vertical normal da página. Evitar cortes, sobreposição e overflow horizontal, inclusive com nomes longos.
- Carregamento discreto não significa erro oculto. Manter feedback acessível de carregamento e erros relevantes visíveis, com possibilidade de tentar novamente.

### Decisões concretas do painel

1. **Indicadores operacionais:** quatro pastilhas de altura intermediária, atualmente cerca de 72 px: Alunos ativos, Check-ins hoje, Aulas hoje e Reservas hoje. Reservas são inscrições confirmadas em agendamentos não cancelados que começam no dia; uma pessoa em duas aulas conta como duas reservas. Não representam alunos únicos nem presença efetiva. As contagens respeitam a organização e a data local da aplicação.
2. **Privacidade financeira:** retirados o indicador de faturamento, o gráfico de receitas e os dados financeiros correspondentes do payload do painel. O Financeiro permanece na área própria. Esta mudança não implementou nem garante permissões ou restrições novas de acesso ao Financeiro.
3. **Calendário compacto:** navegação mensal e pontos nas datas com compromissos; consulta de todo o mês exibido. Somente datas com eventos são botões e abrem o modal. Dias vazios são informativos. Manter o botão “Ir para a agenda”, sem restaurar o título e as instruções redundantes removidos.
4. **Estabilidade e alinhamento:** calendário e frequência têm topo/base e altura alinhados quando lado a lado. Os dias permanecem compactos; o espaço extra pertence ao contêiner. Reservar espaço para até seis linhas de datas evita variação de altura entre meses. Não mostrar aviso visual piscando durante a navegação; manter `aria-busy`, aviso para leitores de tela e tratamento de erro.
5. **Conta no cabeçalho:** somente avatar no gatilho, com foto quando a URL estiver disponível e iniciais como fallback, inclusive em falha da imagem. Menu com Configurações e Sair, preservando as rotas e autenticação existentes. Sem nome, cargo ou seta adicionais no gatilho. Suportar teclado, Esc, clique fora e saída do foco.
6. **Compromissos do dia:** agenda por horário, com cartões leves e hierarquia para título, modalidade, professor, local e reservas/capacidade. Cabeçalho com data e fechar; rodapé com acesso à agenda completa. Estilos próprios do diálogo, sem depender do modal de edição da agenda. Margens de viewport, quebra de nomes longos e corpo com rolagem vertical contida quando necessária; cabeçalho e rodapé permanecem acessíveis, sem overflow horizontal.

Implementação principal: `DashboardView.vue`, `DashboardCalendar.vue`, `HeaderAccount.vue`, `AppShell.vue` e `DashboardController.php`. Validar mudanças nesses componentes com os fluxos existentes; não desfazer as decisões aprovadas ao ajustar apenas um detalhe visual.

Evidências e limites desta sessão: [validação administrativa — revisão de 26/10/2026](VALIDACAO_UI_ADMIN.md#revisão-do-painel-em-26102026).
