# Cadastro em etapas

Branch: `codex/cadastro-em-etapas`.

## Versão para experimentar — onboarding por perfil (25/10/2026)

Branch mantida: codex/cadastro-em-etapas. A versão abaixo substitui a pergunta de tipo de negócio por quatro perguntas sobre a operação. O contrato histórico fica registrado ao final.

### Jornada atual

1. Sua conta, em duas telas: nome do responsável e e-mail; depois, senha e confirmação. A consulta de e-mail existente acontece antes da tela de senha. Voltar preserva os dados somente em memória.
2. Sua rotina, uma pergunta por tela: faixa de alunos; atendimento presencial/online/híbrido; sozinho/equipe; de uma a três prioridades.
3. Seu ambiente, em duas telas: primeiro nome profissional ou da organização e telefone opcional; ao continuar, revisão com dados e respostas já visíveis, aceite dos termos e criação do ambiente. O bloco de ponto de partida foi removido do cadastro. A sugestão continua no painel após a criação.

Não há avanço automático após selecionar respostas. Voltar e revisar preservam os campos somente em memória enquanto a página permanece aberta. Recarregar descarta o cadastro incompleto; senhas e respostas não são salvas em storage do navegador. A primeira tela consulta somente a disponibilidade do e-mail antes de avançar. Usuário e ambiente são criados somente no envio final.

A primeira prioridade selecionada determina o convite inicial no painel. A resposta sobre equipe acrescenta orientação para cadastrar profissionais. Quantidade de alunos e modalidade são armazenadas para conhecer a operação e aparecem na revisão, sem atribuir classificação, limites ou recursos automaticamente. O painel mostra o mesmo convite enquanto o ambiente não tiver alunos ativos; depois, retoma o destaque padrão. Não existe checklist persistente de conclusão nem edição do perfil após o cadastro nesta versão.

### Layout estável entre perguntas

Cabeçalho, progresso e ações mantêm posições fixas dentro do viewport. A área de campos e opções tem rolagem própria, incluindo revisão e termos quando necessário. Ao mudar de pergunta, a rolagem interna volta ao início e o título recebe foco sem deslocar a página. Em telas baixas, o cabeçalho é compacto; em paisagem baixa, a faixa de marca é ocultada para dar espaço às opções. Estilos restritos ao cadastro.

Teste de navegador verifica posições do título, progresso, conteúdo e ações nas oito telas em 1280×900, 390×844, 320×568 e 844×390, além de ausência de rolagem da página e de sobreposição das ações.

### E-mail existente na primeira tela

Ao continuar na primeira tela, os campos são validados e POST /api/register/check-email verifica o e-mail normalizado (trim e minúsculas). Conta existente bloqueia o avanço e abre um modal nativo com Corrigir e-mail (fecha e devolve o foco ao campo) e Ir para login. Esc também retorna ao campo; Tab permanece no modal. A validação final abre o mesmo aviso se houver duplicidade após a consulta. A consulta não envia senhas nem cria dados, usa CSRF e limite próprio de 20 tentativas por minuto. Falhas de rede e 429 mantêm a pessoa na etapa, com nova tentativa. Campos e envio ficam bloqueados durante a consulta para impedir requisições concorrentes. A unicidade continua validada no cadastro final para cobrir mudanças após a consulta. Recuperação de senha segue como tarefa futura.

### Validações de entrada

- Nome e nome do negócio: espaços nas pontas removidos, entre 2 e 255 caracteres; sem restringir nomes a letras ASCII.
- E-mail: formato validado e espaços nas pontas removidos; normalização para minúsculas antes de consultar e enviar.
- Telefone opcional: teclado numérico no celular; somente dígitos ASCII no valor, inclusive ao colar telefone formatado; de 10 a 15 dígitos com DDD. Frontend higieniza e backend rejeita valores fora desse formato. API de cadastro agora espera telefone numérico em string, preservando zeros iniciais.
- Senha: mínimo de oito caracteres, confirmação idêntica; sem trim ou transformação de senha.
- Testes adicionais: modal, foco, Esc/Tab, dimensão em 320×568, telefone inválido, colagem sanitizada, nomes curtos e validação direta no backend.

### Publicação e contas existentes

- A migração preserva os campos antigos, usuários e vínculos. Teste específico recria a estrutura anterior, aplica a alteração e confere personal/studio/academia, telefone formatado, login e ausência de perfil.
- SQLite reconstrói a tabela ao alterar tipo. A migração roda fora de transação e suspende as FKs somente nessa conexão durante a alteração, restaurando-as ao terminar; isso impede exclusões em cascata dos vínculos. MySQL executa a alteração de schema normalmente, sem suspender FKs.
- A execução da migração ainda precisa ser conferida em um ambiente MySQL descartável ou de homologação; os testes automatizados usam SQLite. MySQL local não estava ativo na preparação do PR.
- Railway já executa php artisan migrate --force antes de ativar a versão. Fazer backup/snapshot antes de publicar. Não executar migrate:fresh em ambientes com dados reais.
- Smoke test: conta antiga (login, painel, alunos e financeiro); conta nova (perfil, revisão e criação); e-mail existente (modal e login); /up.
- Em retorno à versão anterior, reverter o código e preservar o schema. O down remove as respostas do perfil e mantém tipo opcional. Abas antigas de cadastro devem ser atualizadas, pois o contrato novo exige telefone numérico.
- Perfil não concede módulos nem limites; organizações antigas não precisam refazer onboarding. Trial e recuperação de senha permanecem fora desta entrega.

### Persistência e compatibilidade

- Nova migration 2026_10_25_000001_add_onboarding_profile_to_organizacoes: JSON onboarding_profile e tipo opcional. Migração aplicada somente no banco local, após cópia em storage/app/before-onboarding-2026-10-25.sqlite.
- O novo cadastro envia onboarding_profile com student_range, service_mode, team e priorities, sem company_type. Não se infere Personal/Studio/Academia: tipo fica nulo para esses cadastros.
- O contrato antigo com company_type continua aceito. Organizações existentes preservam tipo e recebem perfil nulo. Nenhum recurso é bloqueado por essas respostas.
- API valida chaves permitidas, enumerações, perfil completo e prioridades distintas (1–3); criação continua transacional. Perfil retorna em cadastro, login e /api/me somente na organização da sessão.
- Rollback remove o JSON, mantendo tipo opcional para não inventar categorias nas contas novas. Backup local contém o estado anterior para recuperação.
- O plano mensal de R$120 criado pelo contrato anterior continua sendo o plano que o cliente oferece aos alunos, sem representar assinatura do SaaS. Nenhuma cobrança do SaaS ou trial foi acrescentado.
- A intenção comercial segue sendo um mês gratuito; implementação do trial será feita depois, por decisão do usuário.

### Validação desta versão

- Suíte Laravel: 114 testes, 1.587 assertions. Novos casos cobrem persistência após login, compatibilidade, enums, limites, duplicatas, chaves desconhecidas, transação e isolamento entre contas.
- Build Vite concluído.
- Navegador com API simulada: sete telas, validação, teclado, foco, limite de três prioridades, retorno com dados, termos, erros 422 na conta/perfil/ambiente, falha de rede, nova tentativa, envio único e convite no painel; nenhuma conta criada no banco persistente pelo teste.
- Capturas desktop/mobile das seis telas e welcome.png em storage/app/register-preview, sem overflow horizontal a 390px.
- Repetir: disponibilizar Laravel e Vite; Playwright disponível ao Node; node tests/browser/register.cjs. WNFIT_BASE_URL é opcional, padrão http://127.0.0.1:8000.
- Branch preparada para PR; produção não foi alterada.

## Histórico — entrega original

### Contrato preservado

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
