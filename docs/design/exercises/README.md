# Produção da biblioteca WNFit

Ferramenta: geração de imagens integrada do Codex. Referências de estilo: os dois protótipos aprovados pelo usuário nesta pasta. Sem cópia de desenhos de terceiros.

## Conjunto de prompts

Cada entrada de `database/data/exercises.php` fornece nome, grupo principal, grupo secundário, equipamento e instrução para o prompt. Foi enviada uma solicitação independente por exercício, com este briefing comum:

> Ilustração educativa WNFit em cartão horizontal 3:2, fundo branco, manequim adulto cinza sem detalhes de rosto, shorts escuros, contornos pretos. Mostrar o mesmo exercício e equipamento em duas posições de corpo inteiro: início à esquerda e execução à direita. Escolher o ângulo que deixe o movimento e os apoios claros. Destacar em verde os principais músculos informados no catálogo e usar setas para indicar o movimento. Título e legendas legíveis em português, sem prescrição de carga. Manter proporções plausíveis, coluna natural, equipamento correto e não cortar apoios ou pés. As referências visuais servem ao estilo, não para copiar movimentos diferentes.

Variações: burpee usa três etapas; posições isométricas não exigem seta de movimento. O primeiro agachamento com barra usou um prompt específico descrevendo apoio na parte superior das costas, pés apoiados e descida controlada. Os protótipos aprovados cobrem agachamento sem carga e elevação lateral com halteres.

Correção da flexora unilateral: prompt específico para máquina flexora unilateral em pé, mão e tronco apoiados, perna de apoio estável, rolo atrás do tornozelo e flexão do joelho para levar o calcanhar para trás. Foi explicitamente excluída a extensão do joelho sentado.

## Arquivos e manutenção

- Originais gerados permanecem no diretório de imagens do Codex; os artefatos usados pelo produto estão no workspace.
- `scripts/prepare-exercise-image.php` converte para WebP de até 1200 px de largura, qualidade 85, sem redesenhar a demonstração.
- `scripts/build-exercise-library.php --strict` monta os índices e falha se houver exercício sem arquivo.
- `public/media/exercises/index.html` oferece busca, páginas e ampliação para conferir todos os desenhos.
- Imagens publicadas ficam em `v1`. Novas revisões de conteúdo devem usar outra versão para preservar referências anteriores.

A conferência dos desenhos é visual; não equivale a uma certificação biomecânica. A galeria facilita a revisão técnica e ajustes pelo professor antes de publicar o módulo.

## Expansão para 200 exercícios

Os 80 arquivos publicados são preservados. Os outros 120 seguem o mesmo modelo, com instruções e equipamento próprios por exercício. A lista e os critérios estão em [EXPANSAO_200.md](EXPANSAO_200.md); os prompts finais e arquivos de origem selecionados ficam em `expansion-generation.json`. A geração usa a ferramenta integrada, sem CLI/API externa.

Para montar pranchas de revisão dos novos exercícios, executar `php scripts/review-exercise-images.php`. Elas ficam em `storage/app/exercise-review`, sem alterar as imagens do produto. A revisão corrigiu representações ambíguas de supinação, supino unilateral, rotação externa, adução na polia e mobilidade de tornozelo, além de uniformizar acessórios na paleta branco/preto/verde.
