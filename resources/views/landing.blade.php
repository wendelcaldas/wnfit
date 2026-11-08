@php
    $whatsapp = 'https://wa.me/5571999230809?text='.rawurlencode('Olá! Quero conhecer o WNFit.');
    $img = fn (string $name) => asset('images/landing/'.$name.'.webp');
@endphp
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>WNFit — Alunos, treinos e mensalidades em um lugar só</title>
    <meta name="description" content="Sistema de gestão para personal trainers, studios e academias: alunos, fichas de treino, agenda com reservas e mensalidades. O aluno acessa o treino pelo celular, sem instalar nada.">
    <meta name="theme-color" content="#0D3B2E">
    <link rel="canonical" href="{{ route('site') }}">
    <meta property="og:type" content="website">
    <meta property="og:locale" content="pt_BR">
    <meta property="og:site_name" content="WNFit">
    <meta property="og:title" content="WNFit — Alunos, treinos e mensalidades em um lugar só">
    <meta property="og:description" content="Gestão para personal trainers, studios e academias. O treino no celular do aluno, sem instalar nada.">
    <meta property="og:url" content="{{ route('site') }}">
    <meta property="og:image" content="{{ $img('painel') }}">
    <link rel="icon" type="image/svg+xml" href="{{ asset('icons/wnfit.svg') }}">
    <link rel="apple-touch-icon" href="{{ asset('icons/wnfit-180.png') }}">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&family=Space+Grotesk:wght@500;600;700&display=swap" rel="stylesheet">
    <link rel="preload" as="image" href="{{ $img('painel') }}" type="image/webp">
    @vite('resources/css/landing.css')
</head>
<body class="wn-landing">
<svg width="0" height="0" style="position:absolute" aria-hidden="true" focusable="false">
    <symbol id="wn-symbol" viewBox="85 85 850 425">
        <g transform="translate(32 18)">
            <path fill="var(--sym-a, #A3E635)" d="M65 74 H326 Q337 74 344 84 L375 127 Q381 135 375 144 L247 315 Q241 323 235 315 L65 89 Q55 74 65 74 Z"/>
            <path fill="var(--sym-a, #A3E635)" d="M497 74 H659 Q668 74 663 82 L367 477 Q359 489 351 477 L280 382 Q273 372 281 362 L466 88 Q476 74 497 74 Z"/>
            <path fill="var(--sym-b, #F5F6F4)" d="M744 74 H891 Q900 74 894 84 L704 360 Q698 369 687 369 H546 Q535 369 543 357 L725 87 Q734 74 744 74 Z"/>
        </g>
    </symbol>
</svg>
<a class="lp-skip" href="#conteudo">Ir para o conteúdo</a>

<header class="lp-header">
    <div class="lp-wrap lp-header-row">
        <a class="lp-brand" href="{{ route('site') }}" aria-label="WNFit, início">
            <svg class="lp-brand-mark" aria-hidden="true"><use href="#wn-symbol"/></svg>
            <span>WNFit</span>
        </a>
        <nav class="lp-nav" aria-label="Principal">
            <a href="#produto">Produto</a>
            <a href="#para-quem">Para quem</a>
            <a href="#aluno">Seu aluno</a>
            <a href="#duvidas">Dúvidas</a>
        </nav>
        <div class="lp-header-actions">
            <a class="lp-header-login" href="/entrar">Entrar</a>
            <a class="lp-btn lp-btn-lime lp-btn-sm" href="/cadastro">Criar conta grátis</a>
        </div>
    </div>
</header>

<main id="conteudo">
    <section class="lp-hero" aria-labelledby="hero-titulo">
        <svg class="lp-hero-bg" aria-hidden="true" style="--sym-b:#A3E635"><use href="#wn-symbol"/></svg>
        <div class="lp-wrap lp-hero-inner">
            <div class="lp-hero-copy">
                <h1 id="hero-titulo">Seus alunos, treinos e mensalidades em <span>um lugar só.</span></h1>
                <p class="lp-hero-lead">O WNFit organiza a rotina de personal trainers e studios — e coloca o treino no celular do aluno. Sem planilha, sem PDF perdido, sem procurar mensagem no WhatsApp.</p>
                <div class="lp-actions">
                    <a class="lp-btn lp-btn-lime" href="/cadastro">Criar conta grátis <span aria-hidden="true">→</span></a>
                    <a class="lp-btn lp-btn-ghost" href="#produto">Ver o sistema por dentro</a>
                </div>
                <p class="lp-hero-note">Cadastro grátis · Funciona no navegador do computador e do celular</p>
            </div>
            <div class="lp-showcase">
                <div class="lp-showcase-browser">
                    <figure class="lp-browser">
                        <div class="lp-browser-dots" aria-hidden="true"><i></i><i></i><i></i></div>
                        <img src="{{ $img('painel') }}" width="1600" height="1000" fetchpriority="high" alt="Painel do WNFit com alunos ativos, aulas do dia, calendário e alunos recentes (dados de demonstração)">
                    </figure>
                </div>
                <figure class="lp-phone lp-showcase-phone">
                    <div class="lp-phone-screen"><img src="{{ $img('aluno-treino') }}" width="727" height="1570" alt="Treino do aluno no celular, com séries, carga, descanso e ilustração de como fazer"></div>
                </figure>
            </div>
        </div>
    </section>

    <section class="lp-benefits" aria-label="O que muda na sua rotina">
        <div class="lp-wrap lp-grid-4">
            <div class="lp-benefit">
                <h2>Treino pronto em minutos</h2>
                <p>Monte a partir de um catálogo ilustrado, com orientação de execução, e acrescente seus próprios exercícios. Seu método, sem começar do zero.</p>
            </div>
            <div class="lp-benefit">
                <h2>Abre no celular do aluno, sem baixar nada</h2>
                <p>Ele toca no link e já vê o treino do dia. Sem loja, sem ocupar memória, sem atualização. Dá para salvar na tela inicial como um app.</p>
            </div>
            <div class="lp-benefit">
                <h2>O aluno marca sozinho</h2>
                <p>Um link de reservas mostra só os seus horários livres. Menos “tem horário amanhã?” no seu WhatsApp.</p>
            </div>
            <div class="lp-benefit">
                <h2>Cobrar sem constrangimento</h2>
                <p>O lembrete da mensalidade sai pronto e abre direto no WhatsApp. Você só confere e envia.</p>
            </div>
        </div>
    </section>

    <section class="lp-compare-section" aria-labelledby="compare-titulo">
        <div class="lp-wrap">
            <h2 class="lp-h2" id="compare-titulo">O mesmo dia de trabalho. Com e sem organização.</h2>
            <div class="lp-compare" role="table" aria-label="Rotina sem sistema e com o WNFit">
                <div class="lp-compare-row lp-compare-head" role="row">
                    <div role="columnheader">Na rotina</div>
                    <div role="columnheader">Sem sistema</div>
                    <div role="columnheader">Com o WNFit</div>
                </div>
                @foreach ([
                    ['Horários', 'Agenda, WhatsApp, papel e memória.', 'A semana inteira numa tela, e o aluno reserva pelo link.'],
                    ['Ficha de treino', 'Um PDF que o aluno não acha na hora do treino.', 'O treino no celular dele, exercício por exercício.'],
                    ['Mensalidades', 'O atraso aparece quando o aluno já sumiu.', 'Vencimentos à vista e o lembrete pronto para enviar.'],
                    ['Histórico', 'Rolar a conversa até o mês passado.', 'Ficha do aluno com plano, treinos e avaliações físicas.'],
                ] as [$topic, $without, $with])
                    <div class="lp-compare-row" role="row">
                        <div role="rowheader">{{ $topic }}</div>
                        <div role="cell" class="lp-compare-without"><span class="lp-compare-label">Sem sistema: </span>{{ $without }}</div>
                        <div role="cell" class="lp-compare-with"><span class="lp-compare-label">Com o WNFit: </span>{{ $with }}</div>
                    </div>
                @endforeach
            </div>
        </div>
    </section>

    <section class="lp-product" id="produto" aria-labelledby="produto-titulo">
        <div class="lp-wrap lp-chapters">
            <h2 class="lp-h2" id="produto-titulo">O sistema por dentro. Telas reais, não ilustrações.</h2>

            <div class="lp-chapter">
                <div class="lp-chapter-copy">
                    <p class="lp-kicker">01 · Alunos</p>
                    <h3>Cada aluno com plano, vencimento e status.</h3>
                    <ul class="lp-bullets">
                        <li>Quem vence em breve e quem está inadimplente aparece na lista, sem fazer conta.</li>
                        <li>Filtros por modalidade, status, plano e professor.</li>
                        <li>Ficha com dados, treino atual, avaliações e financeiro.</li>
                    </ul>
                </div>
                <figure class="lp-shot lp-shot-right">
                    <img src="{{ $img('alunos') }}" width="1600" height="1000" loading="lazy" alt="Tela de alunos com indicadores e lista com plano, status e vencimento (dados de demonstração)">
                </figure>
            </div>

            <div class="lp-chapter lp-chapter-flip">
                <div class="lp-chapter-copy">
                    <p class="lp-kicker">02 · Treinos</p>
                    <h3>Monte a ficha uma vez. Publique para o aluno.</h3>
                    <ul class="lp-bullets">
                        <li>Divisões, séries, repetições, carga e descanso por exercício.</li>
                        <li>Biblioteca de modelos para reaproveitar e personalizar.</li>
                        <li>Prazo da ficha para lembrar a hora de renovar.</li>
                    </ul>
                </div>
                <figure class="lp-shot lp-shot-left">
                    <img src="{{ $img('montador') }}" width="1600" height="1000" loading="lazy" alt="Montador de treino com divisões A, B e C e prescrição de exercícios (dados de demonstração)">
                </figure>
            </div>

            <div class="lp-chapter">
                <div class="lp-chapter-copy">
                    <p class="lp-kicker">03 · Agenda e reservas</p>
                    <h3>A semana numa tela. O aluno reserva sozinho.</h3>
                    <ul class="lp-bullets">
                        <li>Turmas, atendimentos individuais e aulas que se repetem.</li>
                        <li>Horário de funcionamento e capacidade por aula.</li>
                        <li>Link público de reserva: aparecem só os horários livres.</li>
                    </ul>
                </div>
                <div class="lp-shot-stack">
                    <figure class="lp-shot lp-shot-right">
                        <img src="{{ $img('agenda') }}" width="1600" height="1000" loading="lazy" alt="Agenda semanal com turmas e atendimentos de segunda a domingo (dados de demonstração)">
                    </figure>
                    <figure class="lp-phone lp-shot-phone">
                        <img src="{{ $img('reserva') }}" width="390" height="844" loading="lazy" alt="Página pública de reserva no celular com horários disponíveis">
                    </figure>
                </div>
            </div>

            <div class="lp-chapter lp-chapter-flip">
                <div class="lp-chapter-copy">
                    <p class="lp-kicker">04 · Financeiro</p>
                    <h3>Saiba quem pagou sem abrir planilha.</h3>
                    <ul class="lp-bullets">
                        <li>Previsto, recebido, em aberto e vencido no mês.</li>
                        <li>Lembrete de cobrança pronto para abrir no WhatsApp.</li>
                        <li>Registre o pagamento por Pix, cartão ou dinheiro.</li>
                    </ul>
                    <p class="lp-fineprint">O WNFit não processa pagamentos: o dinheiro continua entrando pelo seu meio atual.</p>
                </div>
                <figure class="lp-shot lp-shot-left">
                    <img src="{{ $img('financeiro') }}" width="1600" height="1000" loading="lazy" alt="Financeiro do mês com cobranças vencidas e a vencer e botões para preparar mensagem e registrar pagamento (dados de demonstração)">
                </figure>
            </div>
        </div>
    </section>

    <section class="lp-audience" id="para-quem" aria-labelledby="para-quem-titulo">
        <div class="lp-wrap">
            <h2 class="lp-h2" id="para-quem-titulo">Feito para quem vive de acompanhar gente treinando.</h2>
            <div class="lp-grid-3">
                <article class="lp-card">
                    <h3>Personal trainer</h3>
                    <p>Você atende sozinho e precisa de tempo para treinar gente, não para organizar planilha. Fichas, horários e mensalidades no mesmo lugar.</p>
                </article>
                <article class="lp-card">
                    <h3>Studio e box</h3>
                    <p>Turmas com capacidade, aulas que se repetem e reserva online. A equipe vê a mesma agenda.</p>
                </article>
                <article class="lp-card">
                    <h3>Academia</h3>
                    <p>Unidades, professores e alunos na mesma conta, com acesso por perfil.</p>
                </article>
            </div>
        </div>
    </section>

    <section class="lp-student" id="aluno" aria-labelledby="aluno-titulo">
        <div class="lp-wrap lp-student-inner">
            <div class="lp-student-copy">
                <h2 class="lp-h2" id="aluno-titulo">O que o seu aluno recebe: <span>o treino na mão.</span></h2>
                <p>Você cria o acesso. Ele entra pelo navegador do celular, troca a senha no primeiro login e encontra a ficha do dia, com a ilustração de cada exercício.</p>
                <a class="lp-link-lime" href="/aluno/entrar">Já é aluno? Acesse seu treino <span aria-hidden="true">→</span></a>
            </div>
            <div class="lp-phones">
                <figure class="lp-phone lp-phones-side">
                    <img src="{{ $img('aluno-login') }}" width="390" height="844" loading="lazy" alt="Tela de login do aluno">
                    <figcaption>Acesso próprio</figcaption>
                </figure>
                <figure class="lp-phone lp-phones-main">
                    <div class="lp-phone-screen"><img src="{{ $img('aluno-treino') }}" width="727" height="1570" loading="lazy" alt="Ficha de treino do aluno com séries, carga e descanso"></div>
                    <figcaption>A ficha do dia</figcaption>
                </figure>
                <figure class="lp-phone lp-phones-side">
                    <img src="{{ $img('reserva') }}" width="390" height="844" loading="lazy" alt="Reserva de horário pelo celular">
                    <figcaption>Reserva de horário</figcaption>
                </figure>
            </div>
        </div>
    </section>

    <section class="lp-honest" aria-labelledby="honest-titulo">
        <div class="lp-wrap">
            <h2 class="lp-h2" id="honest-titulo">O que o WNFit faz hoje. E o que ainda não faz.</h2>
            <div class="lp-honest-grid">
                <div class="lp-honest-yes">
                    <h3>Já faz</h3>
                    <ul>
                        <li>Cadastro de alunos, planos e mensalidades</li>
                        <li>Montagem e publicação de fichas de treino</li>
                        <li>Portal do aluno no navegador</li>
                        <li>Agenda, turmas e link público de reservas</li>
                        <li>Avaliação física e histórico na ficha</li>
                        <li>Lembrete de cobrança pronto para o WhatsApp</li>
                    </ul>
                </div>
                <div class="lp-honest-no">
                    <h3>Ainda não faz</h3>
                    <ul>
                        <li>Receber pagamentos (Pix e cartão continuam no seu meio atual)</li>
                        <li>App nas lojas: o aluno usa pelo link e pode salvar na tela inicial</li>
                    </ul>
                    <p>Preferimos dizer antes do que você descobrir depois.</p>
                </div>
            </div>
        </div>
    </section>

    <section class="lp-steps-section" id="como-funciona" aria-labelledby="passos-titulo">
        <div class="lp-wrap">
            <h2 class="lp-h2" id="passos-titulo">Começar leva três passos.</h2>
            <ol class="lp-steps">
                <li><span class="lp-step-n" aria-hidden="true">1</span><h3>Crie sua conta</h3><p>Nome, e-mail e o tipo do seu negócio. Um checklist mostra os primeiros passos.</p></li>
                <li><span class="lp-step-n" aria-hidden="true">2</span><h3>Cadastre seus alunos</h3><p>Plano, vencimento e o treino de cada um.</p></li>
                <li><span class="lp-step-n" aria-hidden="true">3</span><h3>Libere o acesso</h3><p>O aluno recebe o login e já encontra a ficha no celular.</p></li>
            </ol>
        </div>
    </section>

    <section class="lp-faq" id="duvidas" aria-labelledby="duvidas-titulo">
        <div class="lp-wrap lp-faq-inner">
            <h2 class="lp-h2" id="duvidas-titulo">Dúvidas antes de começar.</h2>
            <div class="lp-faq-list">
                @foreach ([
                    ['Quanto custa?', 'O cadastro é grátis durante o lançamento.'],
                    ['Preciso instalar algum aplicativo?', 'Não, e isso facilita a vida do aluno: ele abre pelo link no celular, sem loja e sem ocupar memória, e pode salvar o WNFit na tela inicial. Você usa no computador ou no celular.'],
                    ['Meu aluno consegue usar sozinho?', 'Sim. Ele recebe o acesso criado por você, troca a senha no primeiro login e vê o treino do dia.'],
                    ['O WNFit recebe os pagamentos dos alunos?', 'Ainda não. Você acompanha vencimentos e registra os pagamentos; o recebimento continua no seu meio atual.'],
                    ['Posso usar os exercícios do catálogo e criar os meus?', 'Sim. O catálogo já vem com exercícios ilustrados e orientação de execução, e você cadastra os seus, com seus nomes e variações.'],
                ] as [$question, $answer])
                    <details>
                        <summary>{{ $question }}<span class="lp-faq-plus" aria-hidden="true">+</span></summary>
                        <p>{{ $answer }}</p>
                    </details>
                @endforeach
            </div>
        </div>
    </section>

    <section class="lp-final" aria-labelledby="final-titulo">
        <svg class="lp-final-bg" aria-hidden="true" style="--sym-a:#0D3B2E;--sym-b:#0D3B2E"><use href="#wn-symbol"/></svg>
        <div class="lp-wrap lp-final-inner">
            <h2 id="final-titulo">Gestão que move resultados.</h2>
            <p>Crie sua conta e cadastre o primeiro aluno hoje.</p>
            <div class="lp-actions">
                <a class="lp-btn lp-btn-ink" href="/cadastro">Criar conta grátis <span aria-hidden="true">→</span></a>
                <a class="lp-btn lp-btn-outline" href="{{ $whatsapp }}" target="_blank" rel="noopener">Falar no WhatsApp</a>
            </div>
        </div>
    </section>
</main>

<footer class="lp-footer">
    <div class="lp-wrap lp-footer-top">
        <div class="lp-footer-brand">
            <a class="lp-brand" href="{{ route('site') }}" aria-label="WNFit, início">
                <svg class="lp-brand-mark" aria-hidden="true"><use href="#wn-symbol"/></svg>
                <span>WNFit</span>
            </a>
            <p>Gestão fitness para personal trainers, studios e academias.</p>
        </div>
        <nav class="lp-footer-nav" aria-label="Rodapé">
            <div>
                <p>Acesso</p>
                <a href="/entrar">Entrar como professor</a>
                <a href="/aluno/entrar">Entrar como aluno</a>
                <a href="/cadastro">Criar conta</a>
            </div>
            <div>
                <p>Ajuda</p>
                <a href="/ajuda">Central de ajuda</a>
                <a href="https://instagram.com/wnfit.app" target="_blank" rel="noopener">Instagram @wnfit.app</a>
                <a href="{{ $whatsapp }}" target="_blank" rel="noopener">WhatsApp</a>
            </div>
        </nav>
    </div>
    <div class="lp-wrap lp-footer-bottom">
        <span>© {{ date('Y') }} WNFit</span>
    </div>
</footer>
</body>
</html>
