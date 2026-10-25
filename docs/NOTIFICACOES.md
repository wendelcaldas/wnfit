# Notificações da equipe — treino concluído

## Comportamento

O sino do cabeçalho é compartilhado pelas telas de gestão. Contador verde-lima mostra não lidas (99+ acima de 99); clicar abre uma lista com até 20 itens e paginação para anteriores. Abrir um item mostra o resumo, marca somente aquele aviso como lido e oferece acesso à aba de treinos do aluno. A leitura persiste no banco e é independente para cada destinatário.

A faixa escura de sugestão/promoção foi retirada do painel, conforme a revisão de UX.

## Gatilho e destinatários

- Somente a transição efetiva de in_progress para completed cria o aviso. Salvar progresso, abandonar ou enviar novamente a conclusão não gera outra notificação.
- Um aviso por sessão e destinatário, protegido por transação, bloqueio da sessão e índice único. Se a persistência do aviso falhar, a conclusão é revertida para permitir nova tentativa sem perda de consistência.
- Recebem proprietários e administradores ativos da mesma organização, mais o professor responsável quando identificável.
- Compatibilidade com cadastro atual: alunos.treinador guarda o nome, não um ID. Só recebe o professor cuja conta ativa na mesma organização tem esse nome e é única. Nome ausente, renomeado, duplicado ou sem conta ativa não é adivinhado; os responsáveis administrativos ainda recebem. Não há migração automática de vínculos nem envio a todos os professores.
- O evento resolve destinatários no momento da conclusão. Professores atribuídos posteriormente não recebem retroativamente; sessões anteriores à publicação não são importadas.
- Cada usuário só lista e marca os próprios avisos, dentro da organização ativa usada pela sessão. Contas sem vínculo ativo e alunos não têm acesso aos endpoints da equipe.

## Conteúdo

Aviso conserva um resumo do evento: nome do aluno, nome da ficha/divisão, foco, data de conclusão, tempo decorrido, exercícios totalmente concluídos e séries registradas, além do link para a ficha do aluno. Não inclui telefone, e-mail, dados clínicos, cargas nem observações médicas.

A conclusão atual do portal admite sessão parcialmente executada (ao menos uma série concluída); o resumo mostra as quantidades realizadas em relação ao prescrito. Tempo decorrido é a diferença entre início e conclusão da sessão e pode incluir pausas. O aviso não representa uma verificação independente de exercício físico realizado.

## Atualização da interface

A interface carrega a caixa de entrada ao montar e abrir o sino e verifica a cada 30 segundos enquanto a página estiver visível. Retomar a aba também atualiza. Não há WebSocket, push de navegador, e-mail ou WhatsApp; não é necessário adicionar serviços de fila, Redis ou provedores pagos. O aviso pode levar até 30 segundos para aparecer sem uma ação do gestor. A atualização automática não interrompe a leitura do resumo ou a paginação de avisos anteriores.

Falhas de consulta mostram nova tentativa, sem fingir que a caixa está vazia. Falhas ao marcar leitura mantêm o contador e permitem repetir. Requisições em curso são canceladas ao desmontar o componente e descartadas se o contexto de conta mudar.

A prévia com dados ilustrativos continua disponível apenas no Vite de desenvolvimento, com a query previewNotifications=1. Não injeta dados no banco e é desativada no build de produção.

## Banco e API

- Migration: 2026_10_25_000002_create_staff_notifications cria apenas a tabela nova, com chaves para organização, usuário e sessão; não modifica tabelas ou vínculos existentes.
- Índice único por usuário, sessão e tipo; índice de caixa de entrada por organização, usuário, leitura e ID.
- Remover uma organização, conta destinatária ou sessão remove seus avisos em cascata. Não foi implementada uma rotina de expiração adicional nesta entrega.
- GET /api/notifications retorna notifications, unreadCount e nextCursor. Parâmetro before faz paginação pelo ID sem repetir registros.
- PATCH /api/notifications/{id}/read é idempotente e não altera novamente a data de leitura. Conta ou organização errada recebe 404. Mutações seguem a proteção CSRF existente.
- Respostas são private, no-store e não expõem IDs internos de destinatários.

## Validação e publicação

- 127 testes Laravel / 1.675 assertions, SQLite isolado; inclui os sete novos testes de evento, destinatários, idempotência, isolamento, leitura, paginação e rollback em falha.
- Build Vite passou.
- Testes Playwright passaram para cadastro e notificações, com respostas simuladas de API: sino, resumo, leitura, falha com nova tentativa, persistência simulada após reload, Escape, mobile e link para o aluno.
- Limitação conhecida e aceita pelo usuário para preparar o PR: não foi executado ensaio ponta a ponta completo com navegador e backend juntos, nem teste em MySQL/homologação. A tentativa de criar um ambiente E2E adicional foi bloqueada pela revisão automática; não houve gravação de dados de teste em produção.
- A migration foi aplicada somente ao banco local. O Railway já configura php artisan migrate --force no pre-deploy. Publicar código e build em conjunto; sem novas variáveis de ambiente.
- Após o merge/deploy, conferir /up, login da equipe, caixa vazia/histórico e operação normal do portal. Validar o evento com uma sessão controlada autorizada; não finalizar treinos de clientes apenas para testar.
- Rollback preferido: reverter o código e manter a tabela nova. O down remove o histórico e estados de leitura; não o executar automaticamente para voltar a versão.

Produção não foi alterada por esta implementação. O PR fica disponível para análise e merge pelo usuário.
