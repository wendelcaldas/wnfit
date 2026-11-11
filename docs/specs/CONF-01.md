# CONF-01 · Remover script do Figma de produção

- **Status:** Pronta para dev
- **Tema / onda:** CONF · Onda 1
- **Autor da spec:** Produto · **Aprovada por:** pendente (Wendel)
- **Branch:** `codex/conf-01-remover-figma-capture`

## Problema

`resources/views/app.blade.php:34` carrega `<script src="https://mcp.figma.com/mcp/html-to-design/capture.js" async></script>`. Isso foi confirmado em produção (wnfit.com.br) em 07/11/2026. O script de terceiro roda em todas as páginas do app Vue, inclusive no painel da equipe e no portal do aluno, onde há dados pessoais e financeiros de alunos. É um risco de LGPD e de segurança, e ainda pesa no carregamento.

## Resultado esperado

Em produção, nenhuma página carrega scripts do Figma. Em ambiente local, a captura para o Figma pode continuar disponível, se for útil ao design.

## Fora de escopo

- Implementar CSP ou revisar outros scripts externos (vira sugestão, se houver).
- Alterar fontes do Google ou qualquer outro asset.

## Critérios de aceite

- [ ] `app.blade.php` só inclui o script quando `app()->isLocal()` for verdadeiro (ou o script é removido de vez, à escolha do Wendel; o padrão desta spec é condicionar ao ambiente local).
- [ ] Teste de feature: com `APP_ENV=production`, a resposta HTML de uma rota do app (ex.: `/entrar`) não contém `mcp.figma.com`.
- [ ] Outras views Blade conferidas (`landing.blade.php`, `support/*`) sem referências ao Figma; resultado do `grep` registrado.
- [ ] `php artisan test` e `npm run build` passando.

## Referências

- `resources/views/app.blade.php:34`
- Análise de 07/11: https://claude.ai/artifact/KgK75fpd1c1zFcupKqWbWN

## Perguntas abertas

_(nenhuma)_

## Plano técnico

_(tech-lead)_

## Notas para Produto

## Registro de execução

| Data | Agente | Etapa | Evidência |
| --- | --- | --- | --- |
| 07/11/2026 | Produto | Spec escrita | Script visto em produção via navegador |
