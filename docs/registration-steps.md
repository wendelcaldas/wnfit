# Cadastro em etapas

Branch: `codex/cadastro-em-etapas`.

## Contrato preservado

- Laravel 12, Vue 3, Vue Router 5, Pinia e Tailwind 4. A autenticação atual usa sessão/CSRF, sem Sanctum instalado.
- `POST /api/register` continua recebendo o mesmo payload, somente ao concluir a revisão.
- Nome, e-mail, empresa, tipo, senha confirmada (mínimo de oito caracteres) e termos continuam obrigatórios. Telefone permanece opcional (máximo de 20 caracteres).
- Tipos aceitos: `academia`, `studio`, `personal`. O papel `proprietario` continua atribuído pelo backend.
- A transação existente cria usuário, organização, vínculo e plano; autentica e regenera a sessão. O destino permanece `/painel`.
- Não há trial nem exigência de verificação de e-mail implementados nesse fluxo. Nenhuma promessa comercial foi adicionada.
- O layout existente, AuthShowcase, largura máxima de 660px e tokens foram preservados. O Figma orientou somente sequência e organização, conforme o pedido.
- Confirmação de senha, telefone opcional e checkbox dos termos foram mantidos, mesmo quando ausentes no protótipo.
- Não foi encontrado AGENTS.md no repositório ou nos diretórios ancestrais consultados.

## Validação

- `php artisan test`: 107 testes, 1446 assertions, incluindo criação real com banco SQLite em memória, isolamento, termos e login/logout.
- `npm run build`: passou.
- `git diff --check`: passou.
- `tests/browser/register.cjs`: passou usando Playwright e Chrome, servidor local e respostas de API simuladas; nenhuma conta foi criada pelo teste de interface.
- Cobertura de interface: campos inválidos, confirmação divergente, Enter, foco, retorno com dados/senhas, ausência de envio intermediário, resumo sem senha, termos, erros 422 nas etapas 1 e 2, falha de rede, nova tentativa, bloqueio de requisições concorrentes, redirecionamento e ausência de senha em storage.
- Capturas de todas as etapas em 1280px e 390px: `storage/app/register-preview/desktop-{1,2,3}.png` e `mobile-{1,2,3}.png`. Inspeção visual e verificação automática de ausência de overflow horizontal.

Para repetir o teste de interface, disponibilize a aplicação em `http://127.0.0.1:8091` (incluindo o Vite se `public/hot` existir), instale/disponibilize Playwright ao Node e execute `node tests/browser/register.cjs`. O Chrome é o padrão; `WNFIT_BROWSER_CHANNEL` permite escolher outro canal instalado. `WNFIT_PREVIEW_DIR` permite alterar a pasta das capturas.

## Limitações

O teste de interface simula a API; o backend é validado separadamente pela suíte Laravel. Não foi realizado cadastro manual integrado em banco persistente. As dependências existentes reportaram 12 alertas no `npm ci`; não foram atualizadas nesta tarefa. Alterações locais preexistentes foram preservadas. Sem merge ou deploy.
