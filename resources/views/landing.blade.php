<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>WNFit — Gestão que aproxima. Treino que move.</title>
    <meta name="description" content="Organize alunos, treinos, agenda e mensalidades com o WNFit. Uma gestão conectada à experiência do aluno, para personal trainers, estúdios e academias.">
    <meta name="theme-color" content="#151715">
    <link rel="canonical" href="{{ route('site') }}">
    <meta property="og:type" content="website">
    <meta property="og:locale" content="pt_BR">
    <meta property="og:site_name" content="WNFit">
    <meta property="og:title" content="WNFit — Gestão que aproxima. Treino que move.">
    <meta property="og:description" content="Seu espaço de gestão. O treino do seu aluno. Tudo mais perto.">
    <meta property="og:url" content="{{ route('site') }}">
    <link rel="icon" type="image/svg+xml" href="{{ asset('icons/wnfit.svg') }}">
    <link rel="apple-touch-icon" href="{{ asset('icons/wnfit-180.png') }}">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&family=Space+Grotesk:wght@500;700&display=swap" rel="stylesheet">
    @vite('resources/css/landing.css')
</head>
<body class="wn-landing">
<a class="lp-skip" href="#conteudo">Ir para o conteúdo</a>
<header class="lp-header lp-container">
    <a class="lp-logo" href="{{ route('site') }}" aria-label="WNFit, início"><span>W</span><strong>WNFit</strong></a>
    <nav class="lp-nav" aria-label="Navegação principal">
        <a href="#recursos">Recursos</a><a href="#como-funciona">Como funciona</a><a href="#duvidas">Dúvidas</a>
    </nav>
    <div class="lp-header-actions"><a href="/entrar">Entrar</a><a class="lp-button lp-primary" href="/cadastro">Criar conta <span aria-hidden="true">↗</span></a></div>
</header>
<main id="conteudo">
    <section class="lp-hero">
        <div class="lp-container lp-hero-grid">
            <div class="lp-hero-copy">
                <p class="lp-eyebrow"><span class="lp-dot" aria-hidden="true"></span> Gestão que move resultados</p>
                <h1>Seu negócio<br>em ordem.<br><em>Seu aluno<br>em movimento.</em></h1>
                <p class="lp-lead">Alunos, treinos, agenda e mensalidades no mesmo lugar. Mais clareza na sua rotina, mais cuidado em cada treino.</p>
                <div class="lp-actions"><a class="lp-button lp-primary" href="/cadastro">Criar minha conta <span aria-hidden="true">↗</span></a><a class="lp-text-link" href="#como-funciona">Conhecer o WNFit <span aria-hidden="true">↓</span></a></div>
                <p class="lp-for">Para personal trainers, estúdios e academias.</p>
            </div>
            <figure class="lp-product-preview">
                <div class="lp-demo-dashboard">
                    <div class="lp-demo-bar"><span class="lp-mini-brand">W</span><strong>Seu espaço de gestão</strong><span class="lp-demo-avatar">PT</span></div>
                    <div class="lp-demo-body">
                        <p class="lp-eyebrow">Visão geral</p><h2>Seu estúdio em movimento.</h2>
                        <div class="lp-demo-banner"><span>Hoje é dia de fazer acontecer.</span><span aria-hidden="true">↗</span></div>
                        <div class="lp-demo-stats"><div><span>Alunos ativos</span><strong>24</strong></div><div><span>Aulas hoje</span><strong>06</strong></div><div><span>Treinos ativos</span><strong>18</strong></div></div>
                        <div class="lp-demo-list"><h3>Próximas aulas <span>Hoje</span></h3><div><time>08:00</time><p><strong>Treino funcional</strong><small>Turma da manhã</small></p><span class="lp-pill">Agendado</span></div><div><time>09:30</time><p><strong>Treino individual</strong><small>Acompanhamento próximo</small></p><span class="lp-pill">Agendado</span></div></div>
                        <div class="lp-demo-footer"><span>Alunos</span><span class="lp-selected">Treinos</span><span>Agenda</span><span>Financeiro</span></div>
                    </div>
                </div>
                <figcaption>Uma visão do produto, com dados ilustrativos.</figcaption>
            </figure>
        </div>
        <div class="lp-container lp-hero-bottom"><span>UMA ROTINA MAIS LEVE.</span><span>UMA EXPERIÊNCIA MAIS PRÓXIMA.</span><span>UM PASSO DE CADA VEZ.</span></div>
    </section>

    <section class="lp-section lp-container" id="recursos" aria-labelledby="recursos-titulo">
        <div class="lp-section-heading"><div><p class="lp-eyebrow">Tudo conectado</p><h2 id="recursos-titulo">Menos pontas soltas.<br>Mais espaço para cuidar.</h2></div><p>Da matrícula ao próximo treino, organize o que faz parte do seu dia sem perder de vista quem importa: seu aluno.</p></div>
        <div class="lp-feature-grid">
            @foreach([
                ['01', 'Alunos, de perto.', 'Cadastro, perfil, anamnese e histórico de treinos reunidos na ficha de cada aluno.'],
                ['02', 'Treinos com direção.', 'Monte rotinas, escolha exercícios do catálogo e personalize a prescrição para cada aluno.'],
                ['03', 'A rotina na agenda.', 'Visualize o dia ou a semana e organize aulas, horários e participantes.'],
                ['04', 'Mensalidades à vista.', 'Acompanhe vencimentos, pendências e pagamentos registrados em uma visão financeira.'],
                ['05', 'Conversas que continuam.', 'Prepare mensagens de boas-vindas e cobranças para o WhatsApp, com histórico na ficha.'],
                ['06', 'Encontros que conectam.', 'Crie eventos com página de inscrição, confirmação de presença e avaliação dos participantes.'],
            ] as [$number, $title, $description])
                <article class="lp-feature"><span class="lp-feature-number">{{ $number }}</span><h3>{{ $title }}</h3><p>{{ $description }}</p></article>
            @endforeach
        </div>
    </section>

    <section class="lp-student-section" aria-labelledby="aluno-titulo">
        <div class="lp-container lp-student-grid">
            <div class="lp-student-copy"><p class="lp-eyebrow">Do seu planejamento ao treino dele</p><h2 id="aluno-titulo">A experiência<br>não termina<br><em>na sua tela.</em></h2><p>Seu aluno acessa a própria ficha de treino pelo celular. Uma experiência clara, pensada para acompanhar cada exercício.</p><ul class="lp-checklist"><li>Acesso individual para cada aluno</li><li>Séries, repetições e intervalos organizados</li><li>Instruções e ilustrações em “Como fazer”</li><li>Registro da sessão e acompanhamento do treino</li></ul><a class="lp-button lp-primary" href="/cadastro">Começar com meus alunos <span aria-hidden="true">↗</span></a><a class="lp-student-login" href="/aluno/entrar">Já é aluno? Acesse seus treinos →</a></div>
            <figure class="lp-phone-preview"><div class="lp-phone"><div class="lp-phone-top"><span class="lp-mini-brand">W</span><strong>WNFit</strong><span class="lp-demo-avatar">AL</span></div><div class="lp-phone-body"><p class="lp-eyebrow">Seu próximo passo</p><h3>Vamos treinar?</h3><div class="lp-phone-plan"><small>SEU TREINO</small><strong>Força e constância</strong><span>Treino A · Peito e tríceps</span></div><div class="lp-phone-progress"><strong>Exercício 1 de 5</strong><span>Hoje, no seu ritmo.</span></div><div class="lp-exercise"><h4><span>01</span> Supino reto</h4><div class="lp-exercise-stats"><div><strong>3</strong><span>Séries</span></div><div><strong>12</strong><span>Repetições</span></div><div><strong>60s</strong><span>Intervalo</span></div></div><p class="lp-how-label">Como fazer</p><img src="{{ asset('media/exercises/v1/supino-reto.webp') }}" alt="Ilustração de execução do supino reto com destaque muscular" width="240" height="150" loading="lazy"><p class="lp-exercise-caption">Orientações junto ao exercício.</p></div><div class="lp-demo-cta">Seu treino, passo a passo <span aria-hidden="true">→</span></div></div></div><figcaption>Exemplo ilustrativo da experiência do aluno.</figcaption></figure>
        </div>
    </section>

    <section class="lp-section lp-container" id="como-funciona" aria-labelledby="como-titulo">
        <div class="lp-section-heading"><div><p class="lp-eyebrow">Como funciona</p><h2 id="como-titulo">Seu próximo capítulo<br>começa em três passos.</h2></div><a class="lp-button lp-secondary" href="/cadastro">Criar minha conta <span aria-hidden="true">↗</span></a></div>
        <ol class="lp-steps"><li><span>01 /</span><h3>Crie seu espaço.</h3><p>Cadastre sua conta e os dados da sua empresa para começar a organizar a operação.</p></li><li><span>02 /</span><h3>Traga seus alunos.</h3><p>Cadastre alunos, defina planos e monte os treinos que fazem sentido para cada pessoa.</p></li><li><span>03 /</span><h3>Conecte a experiência.</h3><p>Disponibilize o acesso do aluno e acompanhe sua rotina de treinos, agenda e mensalidades.</p></li></ol>
    </section>

    <section class="lp-values lp-container" aria-labelledby="valores-titulo"><div><p class="lp-eyebrow">O que move o WNFit</p><h2 id="valores-titulo">Tecnologia a serviço<br>de quem cuida.</h2></div><div class="lp-value-list"><article><h3>Simplicidade na rotina.</h3><p>Informações claras e caminhos diretos para organizar o dia.</p></article><article><h3>Proximidade em cada etapa.</h3><p>O trabalho do professor e a experiência do aluno conectados.</p></article><article><h3>Constância no movimento.</h3><p>Planejar, treinar e acompanhar, um passo de cada vez.</p></article></div></section>

    <section class="lp-section lp-container lp-faq" id="duvidas" aria-labelledby="duvidas-titulo"><div><p class="lp-eyebrow">Antes de começar</p><h2 id="duvidas-titulo">Dúvidas comuns.<br>Respostas diretas.</h2></div><div>
        @foreach([
            ['Para quem é o WNFit?', 'Para personal trainers, estúdios e academias que querem organizar alunos, prescrição de treinos, agenda e mensalidades, com um espaço de acesso para o aluno.'],
            ['O aluno consegue acessar pelo celular?', 'Sim. O aluno entra pelo navegador com o acesso criado pelo professor. No primeiro acesso, troca a senha inicial e passa a consultar seus treinos e registrar as sessões.'],
            ['Preciso instalar um aplicativo para começar?', 'Você pode começar pelo navegador, no computador ou no celular. A experiência foi pensada para se adaptar a diferentes tamanhos de tela.'],
            ['Posso personalizar os treinos?', 'Sim. Você pode montar rotinas com exercícios do catálogo, definir séries, repetições e intervalos e atribuir uma prescrição a cada aluno.'],
            ['O WNFit recebe pagamentos automaticamente?', 'Nesta versão, você acompanha cobranças e registra pagamentos. O controle financeiro não equivale a um serviço de recebimento ou processamento de pagamentos.'],
        ] as [$question, $answer])
            <details><summary>{{ $question }}<span aria-hidden="true">+</span></summary><p>{{ $answer }}</p></details>
        @endforeach
    </div></section>

    <section class="lp-final lp-container"><p class="lp-eyebrow">Cada aluno. Um novo resultado.</p><h2>Seu espaço de gestão.<br>Seu próximo movimento.</h2><p>Comece a organizar sua rotina com o WNFit.</p><a class="lp-button lp-dark-button" href="/cadastro">Criar minha conta <span aria-hidden="true">↗</span></a></section>
</main>
<footer class="lp-footer lp-container"><div><a class="lp-logo" href="{{ route('site') }}"><span>W</span><strong>WNFit</strong></a><p>Gestão que aproxima. Treino que move.</p></div><nav aria-label="Acessos WNFit"><a href="/entrar">Acesso do professor</a><a href="/aluno/entrar">Acesso do aluno</a><a href="/cadastro">Criar conta</a></nav><small>© {{ now()->year }} WNFit</small></footer>
</body>
</html>
