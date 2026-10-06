# Expansão do catálogo para 200 exercícios

Os 80 nomes e arquivos anteriores são preservados. A fonte dos 120 novos registros é `database/data/exercises-expansion.json`, carregada por `database/data/exercises.php`.

Distribuição dos novos registros: 60 variações de musculação, 25 alternativas com peso corporal/elásticos/suspensão, 20 de mobilidade/alongamento e 15 de cardio/condicionamento. Os níveis distinguem alternativas iniciais, movimentos intermediários e a roda abdominal avançada. Categorias e equipamentos seguem os filtros existentes.

## Padrão das demonstrações

Geração pela ferramenta integrada de imagens, uma chamada por exercício. Referência de estilo: `docs/design/exercises/elevacao-lateral-prototipo.png`. Cartão horizontal 3:2, fundo branco, manequim cinza sem rosto, roupa escura, contorno preto, destaque verde na região muscular principal. Duas posições completas com equipamento e apoios visíveis. Título em português, legendas de início e execução, dica curta e marca WNFit. A referência determina o estilo; cada movimento tem sua própria demonstração.

Movimentos estáticos mostram preparação e sustentação, sem setas sugerindo repetições. Alongamentos destacam a região trabalhada, sem prometer ganho de força. A imagem não prescreve carga, séries ou tempo; essas decisões permanecem na ficha do professor.

Originais gerados são mantidos no diretório do Codex. A ferramenta `scripts/prepare-exercise-image.php` converte e reduz para WebP de até 1200 px, sem redesenhar o movimento. O índice é reconstruído com `php scripts/build-exercise-library.php --strict` e consumido no componente “Como fazer”. Novos arquivos são acrescentados em `v1`; arquivos já publicados não são substituídos.

## Dados e aplicação

O seeder preserva IDs, exercícios particulares, instruções revisadas, imagens existentes e registros desativados. Aplicar com `php artisan db:seed --class=ExerciseSeeder`. Isso acrescenta as entradas ausentes e associa a mídia disponível, sem reescrever fichas ou sessões publicadas.

Galeria de revisão: `/media/exercises/`. A conferência visual verifica correspondência do nome, equipamento, movimento, apoios e destaque muscular; a validação técnica pelo professor continua disponível nessa galeria.

Referência para a organização por região corporal, equipamento e experiência: [biblioteca de exercícios da ACE](https://www.acefitness.org/resources/everyone/exercise-library/). Os textos e ilustrações WNFit são próprios; nenhuma foto ou descrição integral dessa biblioteca foi copiada.

## Validação concluída

- 200 nomes e slugs únicos; 120 imagens novas em WebP, aproximadamente 6,2 MB no total.
- Índice estrito: 200 imagens disponíveis, nenhuma ausente.
- Revisão visual em dez pranchas, com correções de movimento, pegada, apoios e paleta.
- 77 testes passaram, com 1.133 verificações; build da aplicação concluído.
- Seeder aplicado aos dois bancos SQLite locais, após backup: 200 exercícios globais em cada banco, nenhum sem imagem. A produção não foi alterada.
- Prompts e hashes dos arquivos finais: `expansion-generation.json`. Os servidores locais permanecem parados.
