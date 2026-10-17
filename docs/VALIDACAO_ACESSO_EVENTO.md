# Participantes e acesso do evento

Branch da entrega: `codex/event-participant-access`, a partir de `f54da4d` (main).

## Fluxo da equipe

1. Abra Eventos, selecione o evento e localize o participante inscrito.
2. Use **Preparar acesso do aluno**. Confira as sugestões de alunos; o mesmo telefone recebe destaque, sem associação automática.
3. Se a pessoa já é aluna, selecione o cadastro e vincule. Se ainda não for, use **A pessoa ainda não é aluna** e confirme a criação.
4. O novo cadastro de participante não cria plano comercial, assinatura, cobrança nem mensagem automática. Contratos de alunos existentes não são alterados.
5. Uma conta é criada apenas se o aluno ainda não tiver acesso. Copie a mensagem com a senha provisória antes de fechar; a senha não será exibida novamente. A troca no primeiro login é obrigatória.
6. Envie individualmente pelo WhatsApp. Copiar a mensagem não significa enviar. Contas existentes mantêm sua senha; redefinição, se necessária, continua no perfil do aluno.

Inscrição pública permanece independente. Esta entrega parte de participantes já inscritos; não adiciona importação em lote ou cadastro de inscrição pela equipe.

## Regras

- Vínculo único aluno/evento; o mesmo aluno pode participar de eventos diferentes.
- Proibido substituir silenciosamente um aluno já vinculado.
- Ao tentar criar um aluno novo com telefone já existente na organização, solicitar conferência e vínculo explícito.
- Repetir a preparação do mesmo participante não duplica aluno/conta nem redefine senha.
- Somente proprietário/administrador com vínculo ativo pode preparar acesso. Evento, inscrição e aluno precisam pertencer à mesma organização.
- Credenciais transitórias não são incluídas na listagem de participantes e respostas de preparação usam `Cache-Control: no-store, private`.
- Eventos cancelados não permitem preparar novos acessos.

## Publicação

Aplicar a migration `2026_10_17_000001_link_event_registrations_to_students` e gerar os assets com `npm run build`. A migration acrescenta uma referência opcional a aluno, preservando inscrições existentes; exclusão de aluno vinculado é restringida para preservar esse vínculo.

## Verificação

- Suíte local: 85 testes e 1.220 assertions passaram após gerar o manifest do Vite.
- Sete testes novos cobrem cadastro sem efeitos financeiros/mensagens, primeiro login, senha inicial, preservação de conta existente, repetição, correspondência de telefone, permissão, isolamento e duplicidade.
- Build de produção passou.
- No navegador, em cópia isolada do banco: vínculo de aluno existente e criação de participante novo conferidos, com mensagem individual e indicação do vínculo.
- Conferência responsiva do painel de acesso: viewport de 390 × 844, sem rolagem horizontal; reabertura preservou o acesso e mostrou a orientação de redefinição pelo perfil.
- Concorrência em MySQL não foi exercitada com múltiplas conexões. A implementação serializa a operação por organização/evento/inscrição, além da restrição única no banco.
- Produção não foi alterada. Álbum, presente, avaliação via conta e configuração de módulos são entregas seguintes. O menu do aluno continua com a restrição de piloto já existente na main.
