# Modalidade de acompanhamento do aluno

Implementação em 26/10/2026. Após a validação local, preparação de PR autorizada separadamente; merge e deploy não autorizados nesta etapa.

## Dados e contratos

- `alunos.modalidade`: string nullable, com valores de API `presencial`, `online` e `hibrido`. É uma classificação exclusiva do acompanhamento do aluno, independente da modalidade de aulas/agendamentos e dos valores cobrados.
- Migration `2026_10_26_000001_add_modalidade_to_alunos_table`: adiciona a coluna sem classificar ou atualizar registros antigos. Foi aplicada apenas ao SQLite local; precisa ser executada em qualquer outro ambiente que receber este código.
- Cadastro normal (`POST /api/students`): exige escolha explícita entre as três modalidades. Ausência, null ou valor inválido retornam 422. Formulário inicia sem seleção; ambos os botões de salvar enviam a modalidade.
- Edição (`PATCH /api/students/{id}`): campo opcional no patch, aceita null como “Não informado” para compatibilidade com legado. Alterar saúde, contato ou outros campos sem enviar modalidade preserva a classificação existente. A ficha permite escolher uma categoria ou deixar Não informado; nenhuma inferência automática ocorre.
- API de lista e detalhe retorna `modality` e `modalityLabel`. A escolha aparece no cadastro, edição de dados pessoais, contexto da ficha, tabela e cartões móveis.
- Os outros caminhos de criação em `EventRegistrationService` e `EventParticipantController` criam perfis `participante`, sem matrícula regular. Continuam com modalidade null e não entram no resumo de alunos ativos. Seeders/factories internos também podem representar legado com null; não foi criada uma exigência global no modelo que quebre esses fluxos.
- A validação nova exige atualizar consumidores de cadastro regular para enviar `modalidade`. Os testes existentes de cadastro, mensagens e acesso foram adaptados, preservando seus cenários originais.

## Faixa e filtros

A faixa preta mantém o total global de alunos ativos da organização à esquerda e o resumo discreto por Presencial, Online e Híbrido à direita. “Não informado” aparece quando houver ativos sem classificação. Híbrido é uma categoria própria, sem duplicação; a soma das quatro categorias corresponde aos ativos com perfil `aluno`.

O resumo (`modalitySummary`) segue a mesma base global do total de ativos e não muda com busca, status, professor, plano ou modalidade da listagem. O filtro de modalidade afeta somente a lista, inclui Não informado e é restaurado por Limpar filtros. A remoção local anterior do seletor de participantes foi preservada. Valores, planos, cobranças e permissões financeiras não foram alterados.

## Validação

- Suíte completa: 132 testes, 1.745 assertions. Três testes específicos cobrem cadastro das três opções, validação, legado, patches parciais, persistência, isolamento entre organizações, categorias exclusivas, exclusão de pausados/participantes e resumo independente dos filtros.
- Build Vite e `git diff --check` passaram.
- Navegador com sessão demo local: faixa e filtro em 320×568, 430×932, 768×1024, 1024×768 e 1366×768, sem overflow horizontal. Cadastro e edição conferidos em celular/desktop, incluindo escolha inicial vazia obrigatória e legado Não informado. Filtrar Online esvaziou a lista demo, mantendo os 11 ativos na faixa; Limpar filtros restaurou a seleção.
- Nenhum aluno real foi classificado arbitrariamente para a inspeção visual. Persistência e respostas com categorias preenchidas foram conferidas nos testes com banco em memória; no navegador, opções foram selecionadas sem salvar cadastros reais. A migração preserva os cadastros antigos como null; qualquer classificação depende de uma ação explícita de cadastro/edição.
- Estas verificações não cobrem todos os navegadores, dispositivos ou tecnologias assistivas. Produção permanece sem esta alteração até um próximo deploy autorizado.
