# Catálogo de exercícios e biblioteca visual

## Diagnóstico e implementação

Antes desta etapa, o banco configurado tinha 24 exercícios globais e nenhuma imagem. Era uma seleção básica, com apenas uma opção para glúteos e uma para panturrilhas, poucas variações de equipamento e sem instruções no seeder original.

A base versionada agora tem 80 exercícios: 56 novos e os 24 originais, com grupo principal/secundário, equipamento, categoria, nível e instrução curta. É uma cobertura inicial maior para musculação geral e alguns movimentos funcionais/cardio. Mobilidade, adutores, variações com elásticos e movimentos específicos de modalidades são próximos candidatos; quantidade não substitui revisão técnica.

Fonte dos dados do projeto: `database/data/exercises.php`. Os nomes originais foram preservados para manter compatibilidade e evitar duplicações. O seeder é idempotente: cria entradas ausentes e preenche apenas instruções vazias nos exercícios existentes; preserva IDs, metadados revisados, mídia, exercícios desativados e dados particulares de cada organização. Por isso, a classificação antiga de um registro existente pode diferir da classificação proposta na base versionada.

Executado no banco local configurado e na demonstração isolada. Para outros ambientes, aplicar `php artisan db:seed --class=ExerciseSeeder` no processo de atualização autorizado. Nenhuma alteração foi publicada em produção nesta etapa.

## Biblioteca de demonstrações

A biblioteca própria segue o padrão visual aprovado: manequim cinza, posições de início e execução, seta de movimento e principais músculos em verde. Os 80 exercícios da base têm arquivos WebP específicos, em `public/media/exercises/v1`, e um índice versionado em `resources/js/data/exerciseIllustrations.json`.

- [Agachamento sem carga — protótipo](design/exercises/agachamento-sem-carga-prototipo.png)
- [Elevação lateral — protótipo](design/exercises/elevacao-lateral-prototipo.png)

Os dois protótipos foram aprovados como direção visual e reaproveitados na biblioteca. Os demais desenhos foram gerados com a ferramenta integrada de imagens. Cada variação tem sua própria demonstração: o agachamento sem carga, por exemplo, não é usado para o agachamento com barra. A conferência visual identificou e corrigiu um desenho da flexora unilateral que representava extensão do joelho; a versão final mostra flexão unilateral em pé. A galeria permite a revisão técnica pelo professor antes do lançamento.

Briefs: manequim simplificado cinza, fundo branco, contorno escuro, duas posições de corpo inteiro, setas verdes e músculos principais destacados. No agachamento, quadríceps e glúteos; na elevação lateral com halteres, deltoides com ênfase lateral. Título, legenda e uma dica curta em português.

O seeder associa a imagem ao exercício global apenas quando não há mídia cadastrada, preservando imagens revisadas e exercícios particulares. “Como fazer” agora exibe a imagem responsiva junto do texto e oferece ampliação em um diálogo. O arquivo só é solicitado após abrir a seção. Erros de imagem mantêm as instruções disponíveis. URLs são verificadas para permitir apenas HTTP/HTTPS e caminhos locais.

Fichas e sessões publicadas continuam com snapshots intactos: quando uma imagem já foi registrada, ela é preferida; versões antigas sem imagem podem usar o índice de ilustrações pela correspondência exata do nome normalizado, sem reescrever a prescrição ou o histórico. Exercícios personalizados identificados como tais não recebem esse fallback. Arquivos finalizados de uma versão não devem ser substituídos por uma nova versão de conteúdo; criar `v2` para futuras revisões publicadas.

Galeria local: `/media/exercises/`. Para reconstruir e verificar a cobertura, executar `php scripts/build-exercise-library.php --strict`. O teste de catálogo verifica a cobertura dos 80 itens, o formato dos arquivos e a preservação da mídia anterior.

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

## Base ampliada — 80 exercícios, agrupados

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

