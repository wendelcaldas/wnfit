# Direção visual para o lançamento do WNFit

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
