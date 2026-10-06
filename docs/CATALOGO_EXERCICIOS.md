# Catálogo de exercícios e biblioteca visual

## Diagnóstico e implementação

Antes desta etapa, o banco configurado tinha 24 exercícios globais e nenhuma imagem. Era uma seleção básica, com apenas uma opção para glúteos e uma para panturrilhas, poucas variações de equipamento e sem instruções no seeder original.

A primeira expansão levou a base de 24 para 80 exercícios. A expansão de outubro de 2026 acrescenta outros 120, totalizando 200 nomes únicos, com grupo principal/secundário, equipamento, categoria, nível e instrução curta. A nova seleção inclui adutores, alternativas com elásticos e suspensão, mobilidade, alongamentos e condicionamento. Os registros anteriores são preservados.

Fonte dos dados do projeto: `database/data/exercises.php`. Os nomes originais foram preservados para manter compatibilidade e evitar duplicações. O seeder é idempotente: cria entradas ausentes e preenche apenas instruções vazias nos exercícios existentes; preserva IDs, metadados revisados, mídia, exercícios desativados e dados particulares de cada organização. Por isso, a classificação antiga de um registro existente pode diferir da classificação proposta na base versionada.

Executado no banco local configurado e na demonstração isolada. Para outros ambientes, aplicar `php artisan db:seed --class=ExerciseSeeder` no processo de atualização autorizado. Nenhuma alteração foi publicada em produção nesta etapa.

## Biblioteca de demonstrações

A biblioteca própria segue o padrão visual aprovado: manequim cinza, posições de início e execução, seta de movimento e principais músculos em verde. Os 200 exercícios da base têm arquivos WebP específicos, em `public/media/exercises/v1`, e um índice versionado em `resources/js/data/exerciseIllustrations.json`.

- [Agachamento sem carga — protótipo](design/exercises/agachamento-sem-carga-prototipo.png)
- [Elevação lateral — protótipo](design/exercises/elevacao-lateral-prototipo.png)

Os dois protótipos foram aprovados como direção visual e reaproveitados na biblioteca. Os demais desenhos foram gerados com a ferramenta integrada de imagens. Cada variação tem sua própria demonstração: o agachamento sem carga, por exemplo, não é usado para o agachamento com barra. A conferência visual identificou e corrigiu um desenho da flexora unilateral que representava extensão do joelho; a versão final mostra flexão unilateral em pé. A galeria permite a revisão técnica pelo professor antes do lançamento.

Briefs: manequim simplificado cinza, fundo branco, contorno escuro, duas posições de corpo inteiro, setas verdes e músculos principais destacados. No agachamento, quadríceps e glúteos; na elevação lateral com halteres, deltoides com ênfase lateral. Título, legenda e uma dica curta em português.

O seeder associa a imagem ao exercício global apenas quando não há mídia cadastrada, preservando imagens revisadas e exercícios particulares. “Como fazer” agora exibe a imagem responsiva junto do texto e oferece ampliação em um diálogo. O arquivo só é solicitado após abrir a seção. Erros de imagem mantêm as instruções disponíveis. URLs são verificadas para permitir apenas HTTP/HTTPS e caminhos locais.

Fichas e sessões publicadas continuam com snapshots intactos: quando uma imagem já foi registrada, ela é preferida; versões antigas sem imagem podem usar o índice de ilustrações pela correspondência exata do nome normalizado, sem reescrever a prescrição ou o histórico. Exercícios personalizados identificados como tais não recebem esse fallback. Arquivos finalizados de uma versão não devem ser substituídos por uma nova versão de conteúdo; criar `v2` para futuras revisões publicadas.

Galeria local: `/media/exercises/`. Para reconstruir e verificar a cobertura, executar `php scripts/build-exercise-library.php --strict`. O teste de catálogo verifica a cobertura dos 200 itens, o formato dos arquivos e a preservação da mídia anterior.

Como alternativa externa, [wger](https://github.com/wger-project/wger#license) disponibiliza dados sob licenças Creative Commons específicas de cada entrada; verificar cada imagem e registrar autoria/licença antes de integrar. As imagens de terceiros não foram copiadas.

Referências técnicas consultadas para os dois exemplos: [agachamento sem carga, ACE](https://www.acefitness.org/resources/everyone/exercise-library/135/bodyweight-squat/) e [elevação lateral, Catalyst Athletics](https://www.catalystathletics.com/exercise/825/Dumbbell-Lateral-Raise/). As orientações do catálogo foram redigidas para a aplicação; não são reprodução integral dessas bibliotecas.

## Lista original — 24 exercícios

- Agachamento livre
- Leg press 45
- Cadeira extensora
- Mesa flexora
- Levantamento terra romeno
- Elevacao pelvica
- Panturrilha em pe
- Supino reto
- Supino inclinado com halteres
- Crucifixo com halteres
- Remada curvada
- Puxada frontal
- Remada baixa
- Desenvolvimento com halteres
- Elevacao lateral
- Rosca direta
- Rosca martelo
- Triceps na polia
- Triceps frances
- Prancha abdominal
- Abdominal supra
- Flexao de bracos
- Afundo
- Burpee

## Primeira expansão — 80 exercícios, agrupados

### Quadriceps (13)

- Agachamento livre — Barra
- Leg press 45 — Maquina
- Cadeira extensora — Maquina
- Afundo — Peso corporal
- Agachamento sem carga — Peso corporal
- Agachamento goblet — Halteres
- Agachamento frontal — Barra
- Agachamento no Smith — Maquina
- Hack squat — Maquina
- Leg press horizontal — Maquina
- Agachamento bulgaro — Halteres
- Subida no step — Step
- Bicicleta ergometrica — Bicicleta

### Posteriores de coxa (6)

- Mesa flexora — Maquina
- Levantamento terra romeno — Barra
- Cadeira flexora — Maquina
- Flexora unilateral — Maquina
- Terra romeno com halteres — Halteres
- Flexao de joelhos na bola — Bola

### Gluteos (6)

- Elevacao pelvica — Barra
- Ponte de gluteos — Peso corporal
- Cadeira abdutora — Maquina
- Extensao de quadril na polia — Polia
- Coice em quatro apoios — Peso corporal
- Abducao de quadril com elastico — Elastico

### Panturrilhas (4)

- Panturrilha em pe — Maquina
- Panturrilha sentada — Maquina
- Panturrilha no leg press — Maquina
- Panturrilha unilateral — Peso corporal

### Peitoral (10)

- Supino reto — Barra
- Supino inclinado com halteres — Halteres
- Crucifixo com halteres — Halteres
- Flexao de bracos — Peso corporal
- Supino reto com halteres — Halteres
- Supino inclinado com barra — Barra
- Peck deck — Maquina
- Crossover na polia — Polia
- Flexao inclinada — Peso corporal
- Flexao com joelhos apoiados — Peso corporal

### Costas (9)

- Remada curvada — Barra
- Puxada frontal — Polia
- Remada baixa — Polia
- Remada unilateral com halter — Halteres
- Remada com peito apoiado — Halteres
- Remada na maquina — Maquina
- Puxada com pegada neutra — Polia
- Pullover na polia — Polia
- Barra fixa assistida — Maquina

### Ombros (7)

- Desenvolvimento com halteres — Halteres
- Elevacao lateral — Halteres
- Elevacao frontal — Halteres
- Crucifixo invertido — Halteres
- Desenvolvimento na maquina — Maquina
- Elevacao lateral na polia — Polia
- Face pull — Polia

### Biceps (6)

- Rosca direta — Barra
- Rosca martelo — Halteres
- Rosca alternada — Halteres
- Rosca Scott — Barra
- Rosca concentrada — Halteres
- Rosca na polia — Polia

### Triceps (6)

- Triceps na polia — Polia
- Triceps frances — Halteres
- Triceps com corda — Polia
- Triceps testa — Barra
- Triceps unilateral acima da cabeca — Halteres
- Flexao com pegada fechada — Peso corporal

### Core (8)

- Prancha abdominal — Peso corporal
- Abdominal supra — Peso corporal
- Prancha lateral — Peso corporal
- Dead bug — Peso corporal
- Bird dog — Peso corporal
- Abdominal reverso — Peso corporal
- Abdominal na polia — Polia
- Mountain climber — Peso corporal

### Corpo inteiro (5)

- Burpee — Peso corporal
- Caminhada do fazendeiro — Halteres
- Polichinelo — Peso corporal
- Caminhada na esteira — Esteira
- Eliptico — Eliptico


## Segunda expansão — 120 novos exercícios

Detalhes do padrão e aplicação: [Expansão para 200](design/exercises/EXPANSAO_200.md). Fonte dos novos registros: `database/data/exercises-expansion.json`.

### Variações de musculação (60)

- Cadeira adutora — Maquina
- Agachamento sumo com halter — Halteres
- Agachamento com halteres ao lado do corpo — Halteres
- Leg press unilateral — Maquina
- Cadeira extensora unilateral — Maquina
- Afundo reverso com halteres — Halteres
- Afundo lateral com halter — Halteres
- Terra romeno unilateral com halter — Halteres
- Levantamento terra com kettlebell — Kettlebell
- Elevacao pelvica na maquina — Maquina
- Extensao de quadril na maquina — Maquina
- Abducao de quadril na polia — Polia
- Puxada supinada — Polia
- Puxada unilateral na polia alta — Polia
- Remada baixa unilateral — Polia
- Remada cavalinho com apoio — Maquina
- Remada curvada com halteres — Halteres
- Remada alta com peito apoiado — Maquina
- Barra fixa pronada — Barra fixa
- Barra fixa supinada — Barra fixa
- Pullover com halter — Halteres
- Extensao de tronco no banco romano — Banco romano
- Supino na maquina — Maquina
- Supino inclinado na maquina — Maquina
- Supino declinado com halteres — Halteres
- Supino unilateral com halter — Halteres
- Crucifixo inclinado com halteres — Halteres
- Crucifixo na polia baixa — Polia
- Crucifixo na polia alta — Polia
- Crucifixo unilateral na polia — Polia
- Supino no solo com halteres — Halteres
- Supino com pegada fechada — Barra
- Desenvolvimento com barra sentado — Barra
- Desenvolvimento unilateral com halter — Halteres
- Desenvolvimento landmine unilateral — Barra
- Elevacao lateral na maquina — Maquina
- Elevacao lateral com peito apoiado — Halteres
- Crucifixo invertido na maquina — Maquina
- Rotacao externa na polia — Polia
- Encolhimento com halteres — Halteres
- Rosca inclinada com halteres — Halteres
- Rosca Scott unilateral com halter — Halteres
- Rosca com barra W — Barra
- Rosca inversa com barra — Barra
- Triceps testa com halteres — Halteres
- Triceps unilateral na polia — Polia
- Triceps acima da cabeca na polia — Polia
- Triceps na maquina — Maquina
- Pallof press na polia — Polia
- Abdominal na maquina — Maquina
- Abdominal com bola suica — Bola
- Elevacao de joelhos na cadeira romana — Cadeira romana
- Prancha com antebracos na bola — Bola
- Abdominal com roda — Roda abdominal
- Panturrilha no Smith — Maquina
- Panturrilha sentada com halteres — Halteres
- Elevacao de ponta dos pes na parede — Peso corporal
- Flexao de punhos com halteres — Halteres
- Extensao de punhos com halteres — Halteres
- Adutor na polia — Polia

### Peso corporal, elásticos e suspensão (25)

- Sentar e levantar da cadeira — Cadeira
- Agachamento com apoio na parede — Peso corporal
- Agachamento com fita de suspensao — Suspensao
- Afundo reverso sem carga — Peso corporal
- Ponte de gluteos unilateral — Peso corporal
- Abducao de quadril deitado — Peso corporal
- Clamshell com elastico — Elastico
- Caminhada lateral com miniband — Elastico
- Ponte de gluteos com miniband — Elastico
- Flexao de joelhos com elastico em pe — Elastico
- Agachamento com elastico — Elastico
- Remada com elastico sentado — Elastico
- Remada com fita de suspensao — Suspensao
- Puxada com elastico — Elastico
- Pull apart com elastico — Elastico
- Rotacao externa com elastico — Elastico
- Elevacao lateral com elastico — Elastico
- Rosca com elastico — Elastico
- Triceps com elastico — Elastico
- Supino em pe com elastico — Elastico
- Flexao na parede — Peso corporal
- Prancha com joelhos apoiados — Peso corporal
- Prancha lateral com joelhos apoiados — Peso corporal
- Pallof press com elastico — Elastico
- Bird dog apenas pernas — Peso corporal

### Mobilidade e alongamentos (20)

- Mobilidade de tornozelo na parede — Peso corporal
- Circulos de tornozelo sentado — Cadeira
- Alongamento de panturrilha na parede — Peso corporal
- Alongamento de soleo na parede — Peso corporal
- Alongamento de quadriceps em pe — Peso corporal
- Alongamento de posterior sentado — Peso corporal
- Alongamento de posterior com faixa — Faixa
- Alongamento de gluteos deitado — Peso corporal
- Alongamento de flexores do quadril — Peso corporal
- Mobilidade de quadril 90 90 — Peso corporal
- Mobilidade de adutores em quatro apoios — Peso corporal
- Alongamento borboleta sentado — Peso corporal
- Mobilidade gato e camelo — Peso corporal
- Rotacao toracica em quatro apoios — Peso corporal
- Rotacao toracica deitado de lado — Peso corporal
- Alongamento de peitoral na parede — Peso corporal
- Alongamento de dorsal ajoelhado — Banco
- Deslizamento de bracos na parede — Peso corporal
- Alongamento de triceps acima da cabeca — Peso corporal
- Alongamento de flexores do punho — Peso corporal

### Condicionamento (15)

- Marcha estacionaria — Peso corporal
- Polichinelo sem salto — Peso corporal
- Corrida estacionaria — Peso corporal
- Corrida na esteira — Esteira
- Caminhada inclinada na esteira — Esteira
- Bicicleta reclinada — Bicicleta reclinada
- Remo ergometrico — Remo
- Escada ergometrica — Escada
- Pular corda — Corda
- Passos laterais entre cones — Cones
- Ergometro de esqui — Ergometro de esqui
- Shadow boxing — Peso corporal
- Cordas navais alternadas — Cordas navais
- Deslocamento em zigue zague entre cones — Cones
- Mountain climber com apoio elevado — Banco
