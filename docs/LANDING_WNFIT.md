# Landing page WNFit

Objetivo confirmado: levar visitantes ao cadastro existente em `/cadastro`.

## Posicionamento proposto

“Seu negócio em ordem. Seu aluno em movimento.” A comunicação conecta organização da operação e experiência do aluno. Simplicidade, proximidade e constância são uma proposta de posicionamento para validação, não uma declaração institucional previamente aprovada.

## Estrutura e conversão

1. Primeira dobra: público, benefício e botão “Criar minha conta”.
2. Recursos: ficha/anamnese, prescrição, agenda, mensalidades, mensagens e eventos.
3. Portal do aluno: exemplo no celular, com a ilustração real do catálogo.
4. Como funciona: criar espaço, cadastrar alunos, conectar o treino.
5. Posicionamento: simplicidade, proximidade e constância.
6. FAQ: público, acesso, instalação, personalização e limites do financeiro.
7. Chamada final: cadastro; acessos do professor e aluno no rodapé.

Uma ação principal consistente reduz decisões concorrentes. Exemplos do produto ajudam a explicar a oferta antes de pedir o cadastro. As perguntas frequentes respondem dúvidas que podem interromper esse caminho. São hipóteses de conversão a avaliar com dados após o lançamento, sem promessa de aumento garantido.

Direção visual: branco, preto e verde `#a3e635`; Space Grotesk e Plus Jakarta Sans; áreas escuras com diagonais discretas; cards e botões alinhados à interface existente. Não há carrosséis ou rolagem lateral. A rolagem vertical organiza o conteúdo editorial da landing.

Os exemplos têm dados ilustrativos identificados. Não há depoimentos, base de clientes, preços, prazo de teste gratuito ou resultados inventados. O financeiro é apresentado como acompanhamento e registro, não processamento de pagamentos. Avaliações físicas, que ainda não têm um fluxo completo, não são anunciadas como recurso entregue.

Referências: [clareza da página inicial, Nielsen Norman Group](https://www.nngroup.com/articles/top-ten-guidelines-for-homepage-usability/) e [cores e contraste, web.dev](https://web.dev/learn/accessibility/color-contrast).

## Implementação

- `/` entrega `resources/views/landing.blade.php`, com conteúdo HTML servido pelo Laravel, independente do aplicativo Vue e do JavaScript.
- `resources/css/landing.css` é uma entrada própria do Vite, sem carregar o bundle de gestão na landing.
- Title, description, canonical e Open Graph estão no HTML. Canonical e URL de compartilhamento usam o domínio recebido pelo Laravel.
- FAQ usa `details/summary` nativo. Há link para pular ao conteúdo, foco visível, títulos hierárquicos e navegação sem JavaScript.
- O cadastro e os acessos existentes continuam nas mesmas rotas.
- Não foi instalado nenhum rastreador ou serviço externo de analytics. As fontes seguem os provedores já utilizados no produto.

## Validação

Build concluído. Suíte com 77 testes e 756 assertions, incluindo conteúdo público servido sem JavaScript e preservação das rotas de acesso. Navegador conferido em desktop e mobile 320×568 e 390×844, sem overflow horizontal. FAQ aberto e botão principal conferido até a tela de cadastro, sem criação de conta.

Capturas em `storage/app/landing-validation/hero.png`, `desktop.png` e `mobile.png`. O servidor temporário de validação foi encerrado ao final.

## Evolução após validar a primeira versão

- Validar a linguagem institucional e o posicionamento proposto.
- Definir as condições comerciais antes de acrescentar preços ou promessas de gratuidade.
- Quando houver clientes e autorização, acrescentar provas reais: depoimentos e casos de uso.
- Medir visitas, cliques no CTA, início e conclusão de cadastro e primeiro aluno/treino cadastrado. Não confundir clique com conversão ou cadastro com ativação.
- Preservar origem de campanha ao implementar mensuração, sem enviar dados pessoais do aluno.
- Comparar títulos ou demonstrações em testes A/B, uma mudança por vez, com volume suficiente para avaliar o resultado.
