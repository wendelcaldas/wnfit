# Estudo aplicado: identidade do administrador

Branch: `codex/admin-visual-refresh`.

A identidade visual do portal do aluno foi aplicada ao painel, à lista de alunos, ao montador de treino e ao financeiro. O AppShell recebe `visual-refresh` somente nessas quatro views. Os estilos ficam em `resources/css/admin.css`, com navegação escura, marca consistente, botões verdes, campos e cards uniformes. As demais rotas conservam sua apresentação atual.

No celular, alunos e cobranças usam cards com as mesmas ações das tabelas. Os indicadores aparecem em duas colunas; os dias do montador ficam em uma grade, sem carrossel horizontal. As ilustrações do catálogo aparecem junto aos exercícios da prescrição. Valores financeiros e regras de publicação continuam vindo dos mesmos serviços existentes.

## Validação local

- Build Vite concluído.
- Suíte PHP: 75 testes, 745 assertions.
- Busca de aluno, seleção de exercícios, salvamento de rascunho e prévia do aluno conferidos pelo navegador.
- Diálogo de pagamento aberto e cancelado; nenhum pagamento foi registrado durante a validação.
- Desktop 1440×900 e mobile 320×568 / 390×844 conferidos, sem conteúdo lateral fora da tela.
- Ambiente visual em `http://127.0.0.1:8013`, usando exclusivamente `storage/app/portal-validation.sqlite`. Rascunho e cobranças fictícias de outubro de 2026 foram adicionados nesse banco de demonstração.

As capturas ficam em `storage/app/portal-validation/admin-*-desktop.png` e `admin-*-mobile.png`. A branch não foi publicada em produção.
