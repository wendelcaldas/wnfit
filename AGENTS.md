# WNFit

Ponto de entrada para Codex e demais agentes. Leia [CONTEXT.md](docs/knowledge/CONTEXT.md) e as [diretrizes por função](docs/knowledge/08-ai/README.md).

WNFit é uma plataforma de gestão fitness para negócios, profissionais e alunos. Laravel e Eloquent ficam em `app/`, `routes/` e `database/`. Vue, Pinia, Router, Vite, Tailwind e views Blade ficam em `resources/`. Testes em `tests/`. O piloto de WhatsApp tem serviço separado em `services/whatsapp/`.

1. Inspecione status Git e instruções aplicáveis. Preserve alterações locais. Trace rota, middleware, controller, serviço, model, migration, UI e teste antes de concluir comportamento.
2. Reaproveite os padrões do módulo e confirme isolamento de organização, papéis e titularidade dos recursos. Preserve compatibilidade com dados antigos e trate repetição e concorrência em operações sensíveis.
3. Use `npm run test:inputs` para máscaras e `npm run build` para frontend quando autorizados. Use `php artisan test --filter Nome` com ambiente de testes isolado. A suíte pode executar migrations. Para trabalho local, `php artisan serve` e `npm run dev` podem rodar em terminais separados. O script `composer dev` inclui Pail e tem limitação documentada no Windows.
4. Preserve os assets originais, tokens, proporções e tipografia. Consulte [Design](docs/knowledge/04-design/README.md) para validar variantes.
5. Proteja credenciais, dados pessoais e financeiros. Ações em produção, deploy e publicação exigem autorização no contexto da tarefa.
6. Atualize documentação e evidências junto de alterações de comportamento. Registre versão, fonte, escopo e limites da verificação.

## Fluxo por card (desde 07/11/2026)

Trabalho de desenvolvimento só começa a partir de uma spec em [`docs/specs/`](docs/specs/README.md) com status `Pronta para dev`. A spec é o único escopo. Papéis, ciclo e regras contra alucinação estão em `docs/specs/README.md`.

- Use a branch indicada na spec (`codex/<card-id>-<slug>`), um card por branch.
- Antes de codar, preencha ou confira o "Plano técnico" com `arquivo:linha`.
- Cada critério de aceite precisa de teste ou passo de verificação registrado.
- Ambiguidade ou decisão de produto: escreva em "Perguntas abertas", mude o status para `Bloqueada` e pare. Não adivinhe.
- Ideias fora do escopo vão para "Notas para Produto", nunca para o código.
- Registre cada etapa em "Registro de execução". Merge, push para `main` e deploy são do Wendel.
- No Claude Code: `/card <CARD-ID>` orquestra os subagentes `tech-lead`, `dev-backend`, `dev-frontend` e `qa-reviewer` (`.claude/agents/`).
