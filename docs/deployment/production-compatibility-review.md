# Revisão de compatibilidade antes do deploy

Data: 29/10/2026. Escopo: árvore de trabalho atual comparada ao HEAD local. HEAD não foi confirmado como a revisão implantada. Produção não foi acessada nem alterada.

## Parecer

Não liberar o conjunto atual como uma mudança apenas visual. Há mudanças financeiras e de acesso a treinos que afetam alunos existentes. Os testes locais comprovam regras, mas não substituem uma auditoria da base real e homologação MySQL.

## Bloqueadores encontrados

1. **Geração retroativa automática em assinaturas antigas.** `RecurringBillingService::sync` passou a inferir recorrencia_inicio de cobranças antigas/proximo_vencimento. `missing` preenche competências desde essa referência. O acesso ao financeiro, perfil de aluno, cobranças e o comando agendado podem gerar esses registros. Simulação somente de leitura sobre o backup local anterior à sincronização: 12 assinaturas sem âncora gerariam 28 cobranças, R$ 3.522, em competências de maio a outubro/2026. Esses valores não representam a produção. Sem histórico confiável de pagamentos externos, dívida histórica pode ser criada indevidamente. Definir data de corte e revisão dos meses anteriores por organização; preservar a geração automática para novas matrículas.

2. **Consultar mês futuro grava cobranças futuras sem horizonte máximo.** `FinanceController` passa o fim do mês solicitado a `refreshOverdueCharges`, que o encaminha à sincronização. Não há limite de antecedência. Simulação de dezembro/2028 na base local atual: 333 cobranças novas potenciais, R$ 43.208, sem gravá-las. A geração fixa o preço atual para períodos futuros; editar o plano depois não atualiza cobranças já emitidas. Separar a navegação/consulta da emissão ou impor horizonte operacional explicitamente definido.

3. **Mudança de acesso para fichas antigas.** Antes, ficha ativa com ends_on nulo podia continuar disponível. Agora a duração congelada no snapshot determina o vencimento; sem datas/duração válidas, o acesso fica indisponível. Também há bloqueio de sessões em andamento quando a ficha expira ou é substituída. Isso preserva histórico concluído, mas pode interromper alunos no dia do deploy. Auditar fichas vigentes e sessões abertas antes da ativação; renovar ou definir transição para os casos legados.

## Outros impactos a validar

- **Receitas antigas:** `FinanceIncomeService` passou a somar todas as linhas de receitas às cobranças pagas, como entradas avulsas. O seeder antigo contém receitas agregadas chamadas Mensalidades, que podem representar os mesmos recebimentos. Verificar a origem real das receitas de cada organização para evitar duplicação. A migração atribui origem avulso e método PIX por padrão; esses rótulos não comprovam o método de registros antigos.
- **Preços de planos compartilhados:** editar valor altera novas cobranças de todas as assinaturas vinculadas; não existe preço contratado individual. Cobranças existentes não são regravadas. A interface informa a regra e bloqueia mudança de ciclo em plano vinculado. Confirmar se contratos e descontos existentes dependem de valores próprios.
- **Escala:** sincronização de todas as assinaturas na requisição e paginação em memória das entradas podem aumentar tempo de resposta e contenção em organizações maiores. O polling de notificações também percorre fichas antigas. Medir com volume representativo.
- **Agendador:** o supervisor versionado executa PHP-FPM e nginx, sem schedule:work/cron. Confirmar se há serviço agendador externo. O fallback por consultas existe, mas não garante avisos diários se ninguém acessar a aplicação.
- **Contexto Docker local:** .dockerignore não exclui database/*.sqlite nem backups em storage/app. Não estão versionados no Git, mas podem entrar em um build enviado do diretório local. Excluir bases e backups do contexto antes de usar esse caminho de publicação.

## Migrações e dados

- 2026_10_29: acrescenta campos/índices em receitas; não remove colunas no up.
- 2026_10_27: torna sessão opcional e acrescenta vínculo de ficha/índice nas notificações. Existe alteração de coluna e chave estrangeira; homologar em MySQL com registros anteriores.
- Em cópia SQLite temporária, a aplicação das migrações preservou os campos anteriores das 13 receitas do backup. Não havia notificações de sessão nessa cópia, portanto o teste não comprova preservação de notificações populadas.
- Testes automatizados usam SQLite em memória; exemplo de produção usa MySQL e Docker instala pdo_mysql. Não houve ensaio MySQL neste ambiente.
- `scripts/pre-deploy.sh` aplica migrate --force antes da troca do aplicativo e executa somente ExerciseSeeder. FinanceDemoSeeder exige ambiente local e não está no fluxo automático de deploy.
- Reverter código não desfaz cobranças novas geradas pela aplicação. Evitar rollback de migrações destrutivo como resposta automática: o down de receitas descarta metadados novos e o down de notificações remove avisos vinculados só à ficha. Backup e plano de recuperação são necessários.

## Caminho para liberação

1. Comparar a revisão efetivamente em produção com o pacote que será publicado.
2. Obter backup e executar auditoria somente de leitura das assinaturas sem âncora, competências ausentes, receitas antigas, fichas e sessões em andamento.
3. Corrigir a geração retroativa e o horizonte de emissão; definir transição para fichas antigas e regra de receitas legadas.
4. Homologar migrações e fluxos em cópia do banco com a mesma versão de MySQL e versão PHP da produção.
5. Executar build, testes, cadastro de aluno, geração idempotente, pagamento, entrada avulsa, edição de planos e acesso do aluno antes da liberação gradual.
