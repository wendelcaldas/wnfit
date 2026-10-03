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
