---
description: Executa o ciclo de desenvolvimento de um card (tech-lead → dev → QA) a partir de docs/specs/<CARD-ID>.md
argument-hint: <CARD-ID>  (ex.: CONF-01)
---

Card: $ARGUMENTS

Você é o orquestrador do desenvolvimento. Não implemente nada diretamente: delegue a cada subagente e respeite os papéis em `docs/specs/README.md`.

1. Abra `docs/specs/$ARGUMENTS.md`. Se não existir ou o status não for `Pronta para dev` (ou `Em desenvolvimento` com achados de QA), pare e informe.
2. Se o "Plano técnico" estiver vazio, chame o subagente `tech-lead` para esta spec. Se ele marcar `Bloqueada`, pare e mostre as perguntas abertas.
3. Chame `dev-backend` se o plano tiver passos de backend. Depois, chame `dev-frontend` se houver passos de frontend. Em ambos os casos, pare se o status virar `Bloqueada`.
4. Chame `qa-reviewer`.
5. Se o QA devolver achados, repita o passo 3 só para os achados, no máximo 2 vezes. Depois disso, pare e mostre os achados.
6. Ao final, mostre: status da spec, critérios atendidos, testes executados, branch e o roteiro de validação para o Wendel.

Não faça merge, push para `main` nem deploy.
