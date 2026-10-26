# Identidade visual unificada WNFit

Branch: `codex/admin-visual-refresh`.

A identidade visual do portal do aluno foi aplicada a toda a gestão: painel, alunos, cadastro e ficha do aluno, biblioteca e montador de treino, financeiro, agenda, eventos, comunicações e equipe. O AppShell aplica o padrão por default. Os estilos compartilhados ficam em `resources/css/admin.css`, com navegação escura, marca consistente, botões verdes, campos e cards uniformes. Cadastro de conta e página pública dos eventos também seguem a identidade branca, preta e verde.

No celular, alunos e cobranças usam cards com as mesmas ações das tabelas. Os indicadores aparecem em duas colunas; os dias do montador ficam em uma grade, sem carrossel horizontal. As ilustrações do catálogo aparecem junto aos exercícios da prescrição. Valores financeiros e regras de publicação continuam vindo dos mesmos serviços existentes.

As abas da ficha ficam em grade e suas cobranças aparecem com rótulos no celular. A agenda semanal empilha os dias em telas pequenas. A biblioteca reorganiza os filtros para acomodar a navegação lateral no tablet. Formulários extensos mantêm rolagem vertical para permitir acesso a todos os campos; os logins conservam a composição de altura da tela.

## Validação local

- Build Vite concluído.
- Suíte PHP: 75 testes, 745 assertions.
- Busca de aluno, seleção de exercícios, salvamento de rascunho e prévia do aluno conferidos pelo navegador.
- Diálogo de pagamento aberto e cancelado; nenhum pagamento foi registrado durante a validação.
- Desktop 1440×900 e mobile 320×568 / 390×844 conferidos, sem conteúdo lateral fora da tela.
- Extensão conferida no navegador: cadastro e ficha do aluno, financeiro da ficha, agenda semanal, equipe, comunicações, eventos, página pública e cadastro de conta. Biblioteca conferida também em 1024×768.
- Ambiente visual em `http://127.0.0.1:8013`, usando exclusivamente `storage/app/portal-validation.sqlite`. Rascunho e cobranças fictícias de outubro de 2026 foram adicionados nesse banco de demonstração.
- Evento fictício `encontro-visual-demo` adicionado somente ao banco de demonstração para conferir as telas de eventos. Nenhuma inscrição ou mensagem foi enviada.

As capturas ficam em `storage/app/portal-validation/admin-*-desktop.png` e `admin-*-mobile.png`. A branch não foi publicada em produção.

## Revisão do painel em 26/10/2026

Decisões de produto e UX aprovadas: [direção visual do painel](DIRECAO_VISUAL_LANCAMENTO.md#painel-e-experiência-operacional-aprovados-em-26102026). Este registro descreve a sessão local de desenvolvimento; os resultados anteriores acima continuam como histórico.

- Aplicativo em `http://127.0.0.1:8000`, Vite em `http://127.0.0.1:5173`, usando o checkout e o banco locais existentes. Servidores mantidos ativos; alterações locais preexistentes preservadas.
- Calendário e frequência medidos em 320×568, 430×932, 768×1024, 1024×768, 1366×768 e 1920×1080. Quando lado a lado, diferença de topo/base de 0 px e alturas de aproximadamente 380 px. No celular ficam empilhados, com rolagem normal da página e sem overflow horizontal/interno. Agosto de 2026 foi conferido com seis linhas reais de datas; a altura permaneceu estável entre meses.
- Pastilhas aprovadas com aproximadamente 72 px; legibilidade e ausência de overflow conferidas em 320, 430, 1024 e 1366 px. Painel sem faturamento, valores monetários ou gráfico de receitas. Consulta local da WNSTUDIO confirmou quatro aulas e 17 reservas no dia da revisão; esses números são dados daquele momento, não valores fixos de produto.
- Conta de demonstração existente usada para conferir avatar de iniciais, navegação para Configurações, abertura por teclado, Esc, retorno do foco e clique fora. Menu coube em 320 px. Logout não foi executado; foto real não foi validada porque a conta disponível não tinha foto. O suporte à foto depende da URL nos dados do usuário; não foi criado cadastro/upload de fotos nesta revisão.
- Dias vazios conferidos no navegador: sem semântica de botão e sem abertura de modal ao clicar. Botão “Ir para a agenda” abriu a rota `/agenda`.
- Modal preenchido validado usando o componente real com fixture HTML temporária servida pelo Vite: quatro aulas, professor/local, reservas e modalidade, além de títulos e nomes longos, incluindo palavras sem espaços. A fixture foi removida após o teste, sem alterar credenciais ou persistir seus dados no banco.
- Modal com conteúdo longo medido em 320×568, 430×932, 768×1024 e 1366×768: nenhum elemento apresentou overflow horizontal; fechar e rodapé permaneceram dentro do viewport. Corpo rolou verticalmente quando necessário. Esc fechou o diálogo e devolveu o foco ao dia 26.
- Limite de integração visual: a sessão autenticada disponível era da WNFit Demo, sem aulas. Os dados da WNSTUDIO foram conferidos pela consulta do controller; o modal preenchido foi verificado pela fixture, não por login na WNSTUDIO. Não houve alteração de senhas para contornar esse limite.
- Build Vite e `git diff --check` passaram. Após a alteração das métricas, 6 testes de agenda passaram com 44 assertions e 12 de autenticação com 179 assertions. Após o refinamento final do modal, build e os 6 testes de agenda passaram novamente. Testes de backend usam SQLite em memória, não o banco local persistente.

Estas verificações cobrem tamanhos representativos no navegador disponível, não todos os dispositivos, navegadores, níveis de zoom, tecnologias assistivas ou cenários de teclado virtual. Até a conclusão da revisão visual, não houve auditoria completa de acessibilidade, novas permissões financeiras ou publicação. A preparação posterior para produção foi autorizada separadamente.


### Preparação para produção

Publicação autorizada em 26/10/2026 pelo fluxo existente: PR para main e deploy automático do serviço wnfit/production no Railway, domínio https://wnfit.com.br. Validação prévia: suíte completa com 129 testes e 1.695 assertions, build Vite e diff sem erros. O pacote não inclui a alteração preexistente em StudentsView.vue, banco SQLite, aulas locais, fixture temporária, arquivos .env ou credenciais. Não há migrations novas nesta entrega. O estado final do deploy deve ser conferido no Railway e em /up após o merge.
