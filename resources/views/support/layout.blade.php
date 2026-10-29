<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', 'Central de ajuda') · WNFit</title>
    <meta name="description" content="@yield('description', 'Guias práticos para organizar alunos, treinos, finanças e agenda no WNFit.')">
    <meta name="theme-color" content="#f7f8f4">
    <link rel="icon" type="image/svg+xml" href="{{ asset('icons/wnfit.svg') }}">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    @vite('resources/css/support.css')
</head>
<body>
    <a href="#main" class="skip-link">Pular para o conteúdo</a>
    <header class="site-header">
        <div class="container header-inner">
            <a class="brand" href="{{ $base ?: '/' }}" aria-label="WNFit — Central de ajuda"><span class="brand-mark">W</span><strong>WNFit</strong><span class="brand-divider"></span><span class="brand-caption">Central de ajuda</span></a>
            <nav aria-label="Navegação principal"><a class="nav-home" href="{{ $base ?: '/' }}">Explorar guias</a><a class="system-link" href="{{ $applicationUrl }}/entrar">Acessar WNFit @include('support.icon', ['icon' => 'arrow-up'])</a></nav>
        </div>
    </header>
    <main id="main">@yield('content')</main>
    <footer class="site-footer container"><a class="brand footer-brand" href="{{ $base ?: '/' }}"><span class="brand-mark">W</span><strong>WNFit</strong></a><p>Conhecimento para uma gestão mais simples.</p><a href="{{ $applicationUrl }}/entrar">Voltar ao sistema @include('support.icon', ['icon' => 'arrow-up'])</a></footer>
</body>
</html>
