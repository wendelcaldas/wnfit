# Expansão Team Peu: 200 para 275 exercícios

Lote de 75 exercícios aprovado em 27/10/2026. Fonte: `database/data/exercises-team-peu.json`, carregada por `database/data/exercises.php`. O formato existente é preservado: nome, grupo principal, grupo secundário, equipamento, categoria, nível e instrução.

## Escopo

O lote acrescenta 8 exercícios de pernas, 5 de posterior de coxa, 4 de glúteos, 4 de panturrilhas, 4 de peitoral, 6 de costas, 8 de ombros, 8 de bíceps, 6 de tríceps, 6 de antebraços/pegada, 10 de abdômen/estabilidade e 6 de potência. Os nomes de cadastro seguem a convenção da base existente. Não há alteração da busca, dos filtros, do modelo ou das fichas publicadas.

As escolhas de equipamento tornam nomes genéricos reproduzíveis: agachamento com caixa sem carga, Nordic assistido com elástico, panturrilha donkey na máquina, remada invertida na barra, seal row com barra, rosca spider com halteres e woodchopper em diagonal da polia alta para baixo. Elevação pélvica unilateral com costas no banco é distinta da ponte unilateral no chão já existente.

## Imagens

Uma geração integrada por exercício, usando `elevacao-lateral-prototipo.png` apenas como referência visual. Cartão horizontal 3:2, fundo branco, manequim cinza sem rosto, roupa escura, contornos escuros e músculos principais em verde. Duas posições com equipamento visível, título, legendas em português, dica curta e marca WNFit. Sustentações estáticas mostram preparação e sustentação, sem setas de repetição.

Conversão com `scripts/prepare-exercise-image.php` para WebP de até 1200 px, acrescentando novos arquivos em `public/media/exercises/v1`. Nenhuma imagem anterior deve ser substituída. O histórico de geração fica em `team-peu-generation.json`.

## Integração e verificação

Após completar e revisar as 75 imagens, reconstruir o índice com `php scripts/build-exercise-library.php --strict`. Executar o teste de catálogo, a suíte da aplicação e o build. O seeder existente preserva IDs, metadados revisados, registros desativados, exercícios particulares e snapshots de fichas.

Aplicação local e publicação devem ser registradas abaixo apenas depois de verificadas. A revisão visual de ilustrações não substitui a orientação presencial do professor para movimentos técnicos.

Referências técnicas para os movimentos menos usuais: [JM press demonstrado pelo criador, EliteFTS](https://elitefts.com/blogs/training/how-to-perform-the-jm-press-a-step-by-step-guide-from-the-inventor) e [Tate press, Catalyst Athletics](https://www.catalystathletics.com/exercise/909/Tate-Press/). As instruções são sínteses próprias; nenhuma imagem dessas fontes foi reutilizada.

## Estado

- 75 registros novos, totalizando 275 nomes e slugs únicos, com instruções e ilustrações próprias.
- 75 WebP novos, aproximadamente 4,14 MB; índice estrito com 275 imagens disponíveis e nenhuma ausente.
- Revisão visual dos 75 exercícios em nove pranchas. Corrigidos bancos declinados, geometria das polias, técnica e enquadramento do JM press. Sustentações mantêm legendas próprias e não sugerem repetições.
- Prompts finais, caminhos dos originais e hashes SHA-256 de cada PNG e WebP registrados em `team-peu-generation.json`. Originais preservados no diretório de imagens geradas do Codex; arquivos finais no projeto.
- Nenhuma das 200 entradas anteriores do índice foi modificada. Nenhum WebP anterior foi substituído.
- 132 testes passaram, com 1.972 verificações; build Vite e Pint concluídos sem erros.
- Seeder aplicado ao SQLite local configurado, depois de backup consistente em `storage/app/backups/`: 200 para 275 exercícios globais, nenhum sem instrução ou imagem.
- Publicação em produção autorizada em 27/10/2026. `scripts/pre-deploy.sh`, configurado em `railway.json`, executa migrations, aplica o seeder idempotente do catálogo e verifica a presença e completude dos registros com `scripts/verify-exercise-catalog.php`. O deploy só continua após essa verificação. O estado final deve ser conferido no Railway e na biblioteca publicada.
