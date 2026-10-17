# Plano de entrega — WNFit + Team Peu

Planejamento em 17/10/2026, sábado. Evento em 18/10/2026, domingo. Horários de referência: Bahia.

## Objetivo de hoje

Entregar uma experiência no portal do aluno: acesso, boas-vindas, presente, página do evento, álbum colaborativo e avaliação. Reservar o último bloco de trabalho para testar e conferir a publicação. Os blocos abaixo são ordem de execução; não são promessa de duração.

## Decisões atualizadas por Wendel em 17/10

- Evento de amanhã: somente nécessaire, uma para cada um dos 20 alunos. Não há bônus nesta edição.
- O gestor deve poder configurar cada evento e ativar recursos como raspadinha, álbum compartilhado e pesquisa de satisfação. A experiência não deve ficar fixa no Team Peu ou em um username.
- WhatsApp Business em preparação por Wendel.
- Nécessaires já chegaram, ainda sem identificação. Wendel irá pessoalmente à gráfica avaliar a aplicação da marca.
- Estas decisões substituem hipóteses anteriores de bônus e atualizam as pendências operacionais abaixo.

## Estrutura configurável por evento

- Reaproveitar a página de gestão de eventos. Cada evento tem sua página para os alunos participantes, com conteúdo e módulos próprios.
- Primeira versão: três módulos conhecidos, com ativação e edição pelo gestor: presente/raspadinha, álbum compartilhado e pesquisa de satisfação. Não é necessário um construtor livre de páginas para entregar essa configuração.
- Presente: editar nome, descrição, mensagem, quantidade disponível e orientação de retirada. Amanhã será nécessaire para 20 participantes; cada participante elegível terá direito a uma unidade, sem resultado aleatório. A raspagem é a apresentação do presente garantido.
- Álbum: ativar/desativar e configurar orientações de envio e aprovação pela equipe.
- Pesquisa: ativar/desativar, editar chamada e mensagem e liberar após encerramento. Começar com nota de 1 a 5 e comentário opcional; questionários livres ficam para evolução.
- Conteúdo, permissões e estado pertencem ao evento. Aluno vê somente eventos vinculados a ele e módulos disponíveis; regras também são verificadas nas APIs.
- Configurações novas não podem alterar presentes já atribuídos/entregues nem apagar fotos ou respostas existentes. Desativar módulo controla disponibilidade, preservando registros.
- Aceite estrutural: criar dois eventos com configurações diferentes; mudar título/presente/ativação em um não altera o outro. Gestor de outra organização não pode editar ou acessar seus dados.
- Distribuição aleatória de bônus permanece evolução futura. O modelo deve permitir sua adição sem exigir implementar um motor de sorteio hoje.

## Contexto recuperado

- ChatGPT web: [Status do Trello WNFit](https://chatgpt.com/c/6ad0d5bc-0c64-83e9-8f76-039c7757de82), [Desenvolver evento WNFit](https://chatgpt.com/c/6ad24c8c-56a4-83e9-b644-fbb0133834d9) e [Manutenção do WNFit](https://chatgpt.com/c/6ad25124-1a64-83e9-8999-1045b8a861f8).
- Aproximadamente 20 participantes; 20 nécessaires compradas. Todos recebem nécessaire e frase; alguns podem receber bônus.
- Todos terão conta de aluno, reaproveitando cadastros existentes. Inscrição pública não cria conta de aluno.
- Acessos enviados individualmente e manualmente pelo WhatsApp Business WNFit.
- Janela de até 30 minutos para ativação. Apresentação, propósito, acesso guiado, dinâmica e demonstração do treino.
- Houve protótipos de seis telas; nesta recuperação obtivemos a descrição textual, não os arquivos visuais.
- Quantidades 2 do bônus A e 4 do bônus B eram exemplos. Consultoria Team Peu também era hipótese, não prêmio confirmado.
- Recomendação anterior, ainda tratada como proposta: raspadinha antes da avaliação; avaliação depois da aula, sem condicionar prêmio à nota.
- [Operação do evento](https://trello.com/c/JP5LumSI) está em desenvolvimento; [ativação e materiais](https://trello.com/c/gSeo4QWg) permanece em próximas demandas. Leitura do Trello em 17/10; nenhuma atualização externa foi feita nesta organização.

## Diagnóstico por evidência

Inspeção de código, não homologação de produção. Checkout: `codex/student-physical-assessment`, HEAD `b626788`. Após fetch, `origin/main` está em `f54da4d`, um commit adiante. Não foi feito merge ou troca de branch.

| Parte | Situação | Evidência / consequência |
|---|---|---|
| Evento público, inscrição, recuperação, check-in e gestão | Implementados; validar evento real | `EventController`, `PublicEventController`, `Event`, `EventRegistration`, `EventsView`, `PublicEventView`, `EventTest` |
| Conta, senha provisória, troca inicial, treino e histórico | Implementados; validar jornada | `StudentAccessService`, portal e `StudentPortalTest` |
| Menu Evento do aluno | Na main remota; tela provisória | PR #3, commit `f54da4d`: `StudentEventView` mostra “Novidades em breve”; `canAccessEvents` restrito a `wendel` |
| Vínculo inscrição → aluno | Ausente no modelo atual | `event_registrations` não tem referência a aluno; autenticação pública usa cookie/código independente |
| Cadastro sem cobrança para participante | Precisa de ajuste ou caminho explícito | `StudentController::store` cria plano/assinatura; não usar o cadastro normal indiscriminadamente |
| Raspadinha, resultado e retirada | Ausentes no código analisado | Não foram encontrados modelos, rotas ou serviço de premiação |
| Álbum e envio de fotos do evento | Ausentes no código analisado | Foto de perfil já existe, mas não equivale a álbum colaborativo |
| Avaliação do evento | Parcial para nova jornada | Já existe nota 1–5 e comentário, após encerramento e presença; acesso atual é pelo cookie público |
| Avaliação física | Trabalho local em andamento | Controller/model/migrations/componentes/testes locais ainda sem commit; preservar e tratar separadamente |
| Persistência de fotos em produção | Dependência a verificar | `RAILWAY.md` registra disco efêmero e necessidade de storage externo; configuração S3 existe, provisionamento não foi confirmado |

## Sequência de trabalho

### 0. Preparar a base

- Preservar as alterações de avaliação física; implementar o evento em branch/checkout isolado a partir de `origin/main`.
- Confirmar versão efetivamente publicada e manter teste inicial restrito ao perfil de Wendel.
- Conferir evento real: data, local, horários, capacidade e abertura de check-in.
- Definir armazenamento persistente antes de publicar o álbum.
- Aceite: base atualizada, alterações locais preservadas, ambiente de teste identificado e dependências externas listadas.

### 1. P0 — Participantes e acesso

- Permitir vínculo explícito entre inscrição e aluno da mesma organização; revisar possíveis duplicados por telefone/nome, sem associação ambígua automática.
- Criar somente alunos faltantes por caminho de participante de evento, sem assinatura/mensalidade involuntária; preservar contratos de alunos existentes.
- Preparar acesso individual, troca de senha inicial e lista de conferência. Não redefinir senha de aluno existente sem necessidade.
- Autorizar APIs pelo vínculo aluno/evento/organização. O menu acompanha a permissão; esconder a rota no frontend não basta.
- Aceite: aluno existente e novo entram, encontram o evento e não geram cobranças indevidas; usuário externo não acessa dados.

### 2. P0 — Página do evento e boas-vindas

- Adicionar à gestão os controles dos três módulos e suas configurações, conforme a estrutura acima.
- Substituir tela provisória por apresentação WNFit + Team Peu, orientações e ações: Meu presente, Álbum, Meu treino e Avaliação.
- Boas-vindas na primeira entrada, com saída e retomada pela página. Troca obrigatória de senha vem antes.
- Reaproveitar check-in e estado do evento; fornecer avaliação via sessão do aluno vinculado sem exigir o código público novamente.
- Aceite: jornada completa no celular, retorno após fechar navegador, estados de vazio/erro e navegação para treino funcionando.

### 3. P0 — Presente e controle de entrega

- Configurar nécessaire para os 20 alunos, frase e orientação de retirada, editáveis pelo gestor em cada evento. Sem bônus ou sorteio nesta edição.
- Atribuir o presente no servidor para participantes elegíveis; definir tratamento de ausência/novos participantes antes de ativar, respeitando 20 unidades.
- Persistir um resultado por aluno/evento. A raspagem apenas revela; atualização, outro dispositivo ou chamadas simultâneas não mudam o resultado nem excedem o estoque.
- Disponibilizar revelação por botão como alternativa ao gesto de raspar.
- Equipe consulta resultado e confirma retirada uma única vez, com data e responsável. Repetição não duplica entrega.
- Aceite: presente garantido para os 20, configurações independentes por evento, retomada, concorrência, isolamento e entrega repetida cobertos.
- Bônus e distribuição aleatória ficam como evolução futura, sem dependência comercial para amanhã.

### 4. P0 — Álbum colaborativo

- Álbum por evento; aluno autorizado pode escolher foto da galeria ou câmera conforme suporte do aparelho.
- Prévia, progresso, erro e nova tentativa; comprimir/redimensionar e limitar tamanho/tipos. Conferir fotos típicas de iPhone e Android.
- Proposta inicial: envio pendente de aprovação; equipe aprova/remove, participante vê o estado do próprio envio. Confirmar quem fará a moderação no dia.
- Registrar autorização de compartilhamento de imagem separadamente do aceite de inscrição já existente.
- Guardar arquivos em storage persistente, controlar acesso, paginar e usar miniaturas para a rede móvel.
- Aceite: dois participantes enviam e veem fotos aprovadas; pendentes não aparecem para todos; arquivo permanece após novo deploy; outro evento/organização não acessa o álbum.

### 5. P0 — Avaliação, ensaio e publicação

- Reusar avaliação existente: estrelas de 1 a 5, comentário opcional, presença confirmada e liberação pelo encerramento manual.
- Evitar avaliações duplicadas entre acesso público e conta vinculada; manter um registro por participante/evento.
- Testar login → troca de senha → boas-vindas → presente → álbum → treino → encerramento → avaliação.
- Cobrir casos críticos em testes PHP; executar `php artisan test` e `npm run build` na branch final. Conferir iPhone e Android e abertura pelo ícone se já instalado.
- Preparar migrations, configuração de storage, cópia de segurança e recuperação. Conferir deploy no Railway e testar URLs reais antes de distribuir acessos.
- Aceite: piloto com Wendel aprovado; depois liberar por participação no evento, removendo a dependência do username; conferir um segundo perfil e a operação da equipe.

## Operação em paralelo — Wendel + Peu

- [ ] Confirmar lista final, novos participantes e ausentes; garantir quantidade de nécessaires.
- [x] Definir presente desta edição: uma nécessaire para cada um dos 20 alunos, sem bônus.
- [ ] Definir regra de reserva e retirada, incluindo ausência ou participantes adicionais.
- [ ] Confirmar WhatsApp Business funcionando; preparar texto com link de aluno, usuário e senha temporária somente quando necessária.
- [ ] Confirmar personalização na gráfica ou alternativa com tag/cartão.
- [ ] Testar QR impresso com URL de produção; distinguir acesso do aluno de pré-inscrição pública.
- [ ] Definir apoio no primeiro acesso, fotógrafo e responsável por aprovar fotos/entregar brindes.
- [ ] Conferir internet e alternativa com lista de presença/retirada da equipe; registrar depois quando a conexão voltar.
- [ ] Garantir um treino publicado para demonstração; não atribuir prescrição pessoal indiscriminadamente.

Proposta de roteiro: 3 min de apresentação, 2 min de propósito, 5 min de acesso, 4 min de presente, 4 min de treino/fotos, 2 min de convite ao feedback. Restam até 10 min para apoio. Avaliação acontece após a aula/encerramento.

## Se o prazo apertar

Preservar acesso e página funcional primeiro. Reduzir animação da raspadinha, efeitos visuais e variedade de frases antes de cortar funções. Se o álbum não tiver persistência validada, usar temporariamente um álbum da organização com endereço persistente e coleta assistida; registrar que envio pelo aluno continua pendente. Manter somente a nécessaire garantida, conforme decisão desta edição. Essas alternativas são contingências propostas, não equivalem à entrega integral desejada.

## Planos anteriores preservados

1. Após o evento: consolidar presentes, acessos, avaliações, dificuldades e fotos; revisar prioridades com dados reais ([cartão](https://trello.com/c/3pjX5OZu)).
2. Finalizar e homologar avaliação física local; o Trello ainda a descreve como protótipo, portanto está desatualizado frente ao checkout.
3. Validar financeiro manual: assinatura, competências, baixa repetida, pausa e totais. Corrigir apenas bloqueadores relacionados ao evento hoje.
4. Retomar planos comerciais e onboarding aberto com período de teste, decisão registrada em 08/10 ([cartão](https://trello.com/c/Po0XOCur)); duração e regras continuam a fechar.
5. Orientação de instalação PWA após bloqueadores; instrução simples pode acompanhar a mensagem sem ampliar escopo hoje.
6. Unidades, gateway, débito automático, WhatsApp automático, IA para treinos e gamificação ficam no backlog.

O bloco antigo “Próximo Plano Sugerido” do KANBAN contém automações/Twilio anteriores à decisão de operação manual. Não deve comandar as prioridades de hoje.

## Estado deste planejamento

As seis entregas do plano revisado foram implementadas em branches encadeadas: inscrição unificada, experiência configurável, presentes, álbum, pesquisa e financeiro de eventos. Validação final: 103 testes, 1.382 assertions, build e conferência no navegador. O documento vigente é [Eventos](EVENTOS.md), com regras, branches e publicação. Produção não foi alterada; configuração do armazenamento persistente e validação do piloto em produção permanecem pendentes.
