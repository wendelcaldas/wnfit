# WNFit — Grafana de clientes

Serviço: wnfit-grafana, projeto adaptable-abundance, ambiente production.
URL: https://wnfit-grafana-production.up.railway.app/d/wnfit-clientes
Imagem fixada: grafana/grafana:13.2.4.

## Acesso e persistência

- Acesso autenticado, sem cadastro público ou acesso anônimo.
- Usuário inicial: wnfit-admin. Senha aleatória está na variável GF_SECURITY_ADMIN_PASSWORD do serviço; nenhum segredo está nestes arquivos.
- Volume wnfit-grafana-data: 500 MB em /var/lib/grafana. Preserva usuários, preferências e banco interno do Grafana entre deploys.
- O processo inicia como root para ajustar a propriedade do volume e passa ao usuário grafana (UID 472) antes de executar o servidor.
- Dashboard padrão provisionado por arquivo. Para editar permanentemente, alterar dashboard.json e publicar a configuração; alterações temporárias pela interface podem ser sobrescritas.

## Fonte de dados

- MySQL de produção pela rede privada Railway, na mesma região us-east4-eqdc4a.
- Schema separado wnfit_analytics com views clients, student_growth e staff; não são alteradas as tabelas do aplicativo.
- Usuário wnfit_grafana_reader possui somente SELECT nessas views, sem acesso direto às tabelas de usuários, contatos ou dados clínicos. Credencial independente da aplicação.
- TLS habilitado com certificado privado autoassinado (sem validação por CA); bootstrap também exige transporte TLS. Nenhum proxy público novo do MySQL foi criado.
- Até três conexões e consultas com limite de execução de 5 segundos; atualização padrão a cada 5 minutos.
- Os dados internos do Grafana usam SQLite no volume, separado do MySQL operacional.

## Definições

- Cliente: organização cadastrada. Inclui cadastros de teste existentes em produção; filtro Cliente permite selecionar a base desejada. Não há identificação automática de testes.
- Cliente ativo no cadastro: organizacoes.ativa = 1, sem inferir assinatura paga ou atividade recente.
- Cliente com alunos: organização com ao menos um perfil_tipo=aluno.
- Alunos: somente perfil_tipo=aluno, excluindo participantes exclusivos de eventos. Ativos: status=ativo.
- Acessos criados: contas de aluno do portal, sem significar acesso recente.
- Vínculos ativos de equipe: soma por organização; um mesmo profissional pode contar mais de uma vez.
- Treinos e eventos: registros cadastrados, inclusive históricos; não representam exclusivamente publicações ou futuros eventos.
- Treinos iniciados em 30 dias: sessões de alunos com started_at nos últimos 30 dias corridos, janela fixa.
- Totais e tabela: estado atual de produção. Gráficos de crescimento: registros criados no período selecionado, agrupados por dia UTC e apresentados no fuso America/Bahia. Exclusões posteriores deixam de aparecer; não são snapshots históricos.
- Não há ainda métrica confiável de gestores ativos em 7/30 dias, receita do SaaS ou trial. Requer instrumentação e regras comerciais próprias.

## Provisionamento

1. setup.sql contém somente criação das views e usuário de leitura. Placeholders __APP_DB__ e __READER_PASSWORD__ são substituídos pelo bootstrap, nunca versionados com valores reais.
2. O serviço temporário wnfit-metrics-setup (mysql:9, sem domínio ou volume, restart NEVER) executou bootstrap.sh uma vez. Confirmou leitura e recusa de acesso às tabelas brutas. Após concluir, variáveis de credenciais foram esvaziadas e seu comando foi substituído por uma saída sem ação. Não deve permanecer como processo em execução.
3. Grafana recebe os arquivos em base64 nas variáveis DATASOURCE_B64, PROVIDER_B64, DASHBOARD_B64 e START_SCRIPT_B64. Isso permite usar a imagem oficial sem modificar o WNFit ou seu pipeline.
4. start.sh materializa os arquivos de configuração no volume e executa /run.sh como usuário grafana.
5. Senhas são geradas com crypto.randomBytes; não reutilizar root do MySQL no datasource nem tornar o painel público.

## Verificação

WNFIT_GRAFANA_URL aponta para a URL HTTPS; WNFIT_GRAFANA_PASSWORD contém a senha admin, fornecida por variável de ambiente. Não salvar em scripts ou commits.

- node services/grafana/verify.cjs: saúde, autenticação, conexão MySQL e execução das 14 consultas do dashboard.
- node services/grafana/verify-browser.cjs: com Playwright e Chrome disponíveis, verifica login e renderização dos dados. Captura local em storage/app/grafana-preview/dashboard.png, ignorada pelo Git.
- Confirmação inicial: 6 organizações, 23 alunos; não corresponde necessariamente a clientes comerciais válidos.
- Rotação da senha admin após o primeiro uso deve ser feita no perfil do Grafana. Alterar somente a variável de bootstrap não troca a senha de um banco interno já inicializado.

## Operação

O dashboard gera consumo adicional de computação e volume no Railway. O aplicativo e o worker existentes não foram reiniciados para esta configuração. Não há Prometheus, Loki ou collector nesta entrega.

Para desligar, remover/restringir o serviço Grafana e revogar a conta de leitura; preservar o volume caso precise recuperar usuários e configurações. Não apagar as tabelas do WNFit.
