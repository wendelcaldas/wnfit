# Specs — contrato entre Produto e Desenvolvimento

Toda mudança de comportamento no WNFit nasce de um card do Kanban e de uma spec nesta pasta. A spec é a única fonte de escopo para quem implementa. Se algo não está na spec, não é feito, nem "aproveitando".

## Papéis

| Papel | Quem | Faz | Não faz |
| --- | --- | --- | --- |
| Decisor | Wendel | Aprova specs, responde perguntas abertas, aprova merge e deploy | — |
| Produto | Claude no projeto WNFIT (Cowork) | Escreve e prioriza cards, escreve specs, responde dúvidas de escopo, valida a entrega contra o resultado esperado | Não altera código de aplicação |
| Tech lead | Agente `tech-lead` | Lê a spec, mapeia arquivos com evidência e escreve o plano técnico na spec | Não muda escopo e não implementa |
| Dev backend | Agente `dev-backend` (ou Codex) | Implementa Laravel: rotas, controllers, serviços, models, migrations, jobs e testes PHP | Não mexe em `resources/js` nem em CSS |
| Dev frontend | Agente `dev-frontend` (ou Codex) | Implementa Vue, CSS e Blade com componentes base e tokens | Não mexe em migrations nem em regras de negócio no backend |
| QA | Agente `qa-reviewer` | Confere cada critério de aceite com evidência, roda testes e build, e revisa isolamento e regressões | Não corrige código. Devolve ao dev com o achado |

## Ciclo de um card

```
Kanban (Agora) → spec "Rascunho" (Produto) → "Pronta para dev" (Wendel aprova)
→ plano técnico (tech-lead) → implementação em branch codex/<card-id>-<slug> (dev)
→ "Em QA" (qa-reviewer) → "Em validação" (Wendel/Produto no navegador)
→ merge + deploy (Wendel) → "Feito" no Kanban
```

O campo `Status` no topo da spec acompanha esse ciclo: `Rascunho | Pronta para dev | Em desenvolvimento | Em QA | Em validação | Feito | Bloqueada`.

## Regras contra alucinação

1. **Fonte única.** O escopo vem da spec. Contexto de negócio vem de `docs/knowledge`. Comportamento atual vem do código. Nada vem da memória do modelo.
2. **Evidência obrigatória.** Toda afirmação sobre o código cita `arquivo:linha`. Todo critério marcado como atendido cita o teste ou o passo de verificação.
3. **Dúvida para, não adivinha.** Se a spec for ambígua ou contradisser o código, o agente escreve a pergunta em "Perguntas abertas", muda o status para `Bloqueada` e para. Produto ou Wendel respondem na própria spec.
4. **Escopo fechado.** Melhorias percebidas fora do escopo viram sugestão em "Notas para Produto", nunca código.
5. **Um card por branch.** Nada de misturar cards num commit.
6. **Registro.** Cada etapa acrescenta uma linha em "Registro de execução" com data, agente, o que fez e a evidência.

## Comandos

- Claude Code na raiz do repositório: `/card CONF-01` executa o ciclo tech-lead → dev → QA para a spec indicada.
- Codex: abrir a tarefa com "Implemente a spec `docs/specs/CONF-01.md` seguindo `AGENTS.md`".

Modelo de spec: [_TEMPLATE.md](_TEMPLATE.md).
