<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">

        <title>WNFit</title>
        <meta name="application-name" content="WNFit">
        <meta name="apple-mobile-web-app-title" content="WNFit">
        <meta name="theme-color" content="#151715">
        <link rel="icon" type="image/svg+xml" href="{{ asset('icons/wnfit.svg') }}">
        <link rel="icon" type="image/png" sizes="32x32" href="{{ asset('icons/wnfit-32.png') }}">
        <link rel="apple-touch-icon" sizes="180x180" href="{{ asset('icons/wnfit-180.png') }}">
        <link rel="manifest" href="{{ asset('site.webmanifest') }}">
        <meta
            name="description"
            content="WNFIT e a plataforma SaaS para gestao inteligente de academias, studios e personal trainers."
        >
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <link rel="preconnect" href="https://fonts.googleapis.com">
        <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
        <link
            href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&family=Space+Grotesk:wght@500;700&display=swap"
            rel="stylesheet"
        >
        @unless(request()->is('evento/*', 'aluno', 'aluno/*'))
        <script src="https://mcp.figma.com/mcp/html-to-design/capture.js" async></script>
        @endunless

        @if (file_exists(public_path('build/manifest.json')) || file_exists(public_path('hot')))
            @vite(['resources/css/app.css', 'resources/js/app.js'])
        @endif
    </head>
    <body class="antialiased">
        <div id="app"></div>
    </body>
</html>
