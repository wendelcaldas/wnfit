# Validação — portal do aluno e módulo de treinos

Esta entrega está na branch `codex/student-training-experience`. A demonstração usa um banco SQLite separado (`storage/app/portal-validation.sqlite`), sem alterar o banco configurado no `.env`. Não é necessário enviar mensagens nem configurar Twilio.

## Abrir a demonstração

O servidor local usa `http://127.0.0.1:8012`.

| Perfil de demonstração | Entrada | Usuário | Senha |
| --- | --- | --- | --- |
| Professor | `/entrar` | `professor@demo.wnfit.test` | `ProfessorDemo2026!` |
| Aluno com ficha | `/aluno/entrar` | `wendel.demo` | `TreinoDemo2026!` |
| Primeiro acesso | `/aluno/entrar` | `primeiro.demo` | `TreinoDemo2026!` |
| Aluno sem ficha | `/aluno/entrar` | `semficha.demo` | `TreinoDemo2026!` |

São contas fictícias exclusivas do banco de demonstração. Depois de trocar a senha de `primeiro.demo`, use a senha escolhida ou redefina pelo professor. Aluno e gestão alternam a sessão do navegador: ao entrar em um perfil, o outro sai. Use navegadores separados se quiser comparar os dois ao mesmo tempo.

Se precisar reiniciar, em um terminal PowerShell na raiz do projeto:

```powershell
./scripts/start-student-preview.ps1
```

Em outro terminal, inicie o frontend caso o Vite não esteja rodando:

```powershell
npm run dev -- --host 127.0.0.1
```

## Roteiro de validação

1. **Criar acesso:** na gestão, cadastre um aluno. Confira a sugestão editável de usuário. Ao salvar, copie os dados mostrados em “Acesso do aluno”. A senha provisória aparece apenas nessa entrega e não fica armazenada no navegador.
2. **Aluno existente:** abra um aluno sem conta, entre na aba “Acesso do aluno” e use “Criar acesso”. Para recuperar uma conta, gere uma nova senha provisória; a anterior e as sessões antigas deixam de funcionar.
3. **Primeiro acesso:** entre como `primeiro.demo`. O portal deve exigir uma nova senha antes de liberar o início ou o treino. Teste confirmação diferente, senha curta e senha provisória incorreta.
4. **Montagem:** em Treinos, crie ou duplique um programa. Salve um rascunho com um dia vazio, adicione exercícios pelo catálogo, configure séries/repetições/carga/descanso, reordene exercícios e duplique dias. Consulte “Visão do aluno”. Um programa com dia vazio não pode ser publicado.
5. **Publicação:** escolha um aluno em “Entregar para” e publique. Também é possível atribuir um modelo ativo pela biblioteca ou pelo perfil do aluno. A vigência começa hoje e termina conforme a duração em semanas.
6. **Personalização:** no perfil, em Treinos, clique em “Personalizar nova versão”. A cópia parte da ficha recebida pelo aluno. Publicar a nova versão substitui a vigente e guarda a anterior no histórico.
7. **Uso no celular:** entre como `wendel.demo`, abra a ficha e escolha A/B/C. Inicie o treino, registre carga e repetições, marque uma série e confira o descanso. Atualize a página depois de aparecer “Progresso salvo”: o registro deve continuar lá.
8. **Conclusão:** finalize o treino. Se houver séries pendentes, o portal pede confirmação. Uma sessão com pelo menos uma série concluída entra no histórico e no resumo semanal. “Encerrar sem concluir” preserva os registros sem contar a sessão como concluída.
9. **Acompanhamento:** volte à gestão e abra Treinos no perfil de Wendel. Confira as sessões e séries realizadas e as versões anteriores da ficha.
10. **Preservação:** altere ou arquive o modelo na biblioteca. A ficha já entregue e as sessões anteriores devem manter a prescrição original. Para atualizar um aluno, publique uma nova versão explicitamente.

## Regras e limites desta versão

- O acesso do aluno usa usuário único no sistema, senha provisória aleatória e troca obrigatória. Não depende de e-mail.
- A recuperação é assistida pela equipe. O portal explica onde pedir uma nova senha.
- O aluno acessa somente sua ficha e suas sessões. Não tem acesso aos endpoints da gestão.
- Há uma sessão em andamento por aluno. Um segundo pedido de início retoma a mesma sessão.
- As alterações são salvas online. Quando o salvamento falha, a página avisa e permite tentar novamente; alterações ainda não salvas não são garantidas ao fechar o navegador.
- Uma edição concorrente do programa ou da sessão é detectada, evitando sobrescrever alterações de outra tela.
- Cada publicação guarda sua prescrição. Dados de séries não dependem de futuras mudanças no catálogo ou no modelo.
- Vídeos são exibidos como links quando o exercício já possui uma URL válida. Esta entrega não adiciona uma coleção de vídeos ou imagens.
- Modelos podem ser duplicados, arquivados e restaurados como rascunho. As fichas publicadas são preservadas.
- A migração congela os vínculos de treinos existentes. Contas de alunos antigos são criadas pela ação “Criar acesso”, evitando distribuir senhas sem a participação da equipe.

## Verificação técnica

```powershell
php artisan test
npm run build
php vendor/bin/pint --dirty --test
```

Os testes cobrem criação de contas, senha inicial e redefinição, isolamento entre alunos e academias, fichas preservadas, edição e personalização, retomada e conclusão de sessões, conflito de edições, migração de vínculos antigos e busca paginada do catálogo.

Para um ambiente real, aplicar a migração com `php artisan migrate --force` e gerar o frontend com `npm run build` após a validação. O script de demonstração não altera a configuração do ambiente real.
