# Avaliação física do aluno

Disponível em Alunos > Ficha > Avaliação física. Mantém a identidade e os menus existentes.

## Registro e consulta

- A última avaliação abre em consulta; criar ou editar exige uma ação explícita.
- Uma avaliação exige data não futura e pelo menos uma medida. Demais medidas são opcionais.
- Peso, altura, gordura corporal manual e circunferências têm duas casas decimais. O formulário aceita vírgula ou ponto decimal.
- MVP simplificado: formulário direto, sem mapa corporal. Dados gerais e circunferências opcionais ficam visíveis, sem seleção de regiões. Lados são os do aluno.
- Nova avaliação começa vazia, sem transportar medidas anteriores. Salvar registra todas as regiões preenchidas.
- Histórico ordenado por data decrescente e ID decrescente para desempatar avaliações no mesmo dia.
- Histórico em botões: uma avaliação selecionada mostra os dados; duas comparam entre si. Limite de duas seleções, com desmarcação para trocar. A mais recente (data e ID) é a referência atual. Consulta mostra medidas preenchidas em qualquer uma das duas. Editar exige apenas uma selecionada. Sem medida em um dos registros, não há variação. A variação é neutra, sem julgamento de melhora/piora.
- Não há exclusão, cálculo de gordura, diagnóstico ou exposição no portal do aluno.

## Persistência

Tabela `student_physical_assessments`, ligada à organização e ao aluno, com responsáveis pela criação e última edição. API autenticada em `/api/students/{student}/physical-assessments`: GET/POST e GET/PUT por ID. Segue o isolamento e a autorização das rotas existentes de alunos.

Peso e altura antigos continuam em `alunos`, sem alterações ou histórico inventado. Na ficha/anamnese, os valores de referência são a medida não vazia mais recente de cada campo, com os valores antigos como alternativa. Anamnese apenas consulta esses valores; novas medidas são registradas em Avaliação física. A API anterior de cadastro continua compatível e os valores legados não são sobrescritos pelas avaliações.

Na atualização do ambiente executar `php artisan migrate --force` e publicar o build frontend. A migração foi executada apenas no ambiente local durante esta implementação.

## Validação e entrega

Branch de publicação: `feature/avaliacao-fisica`, baseada na `main` atualizada (`78a946c`), incluindo as alterações de eventos feitas no outro computador. A implementação original está preservada em `codex/student-physical-assessment`.

- Suíte completa: 107 testes e 1.446 verificações, incluindo os fluxos de eventos e avaliação física. Build frontend concluído.
- Navegador local: estado vazio, criação com vírgula decimal, histórico com nomes e comparação de duas avaliações, incluindo medida ausente em uma delas. Revisão em viewport de 390 × 844 sem rolagem horizontal. Somente dados fictícios da organização de QA na porta 8001.
- A interface entregue usa formulário direto, sem mapa corporal. O protótipo local de mapa permanece fora desta entrega.
- Ajustado o teste de piloto de eventos herdado da main para enviar token CSRF durante a simulação de produção; nenhuma proteção de produção foi desativada.
- Deploy: executar as duas migrations aditivas com `php artisan migrate --force` e publicar o build. Os dados legados de peso e altura permanecem preservados. Produção não foi alterada nesta preparação.
