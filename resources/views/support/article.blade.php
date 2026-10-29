@extends('support.layout')
@section('title', $article['title'])
@section('description', $article['summary'])
@section('content')
<div class="subpage-search"><div class="container">@include('support.search')</div></div>
<div class="container article-page">
    <nav class="breadcrumbs" aria-label="Localização"><a href="{{ $base ?: '/' }}">Central de ajuda</a>@include('support.icon', ['icon' => 'chevron'])<a href="{{ $base }}/modulos/{{ $collection['slug'] }}">{{ $collection['title'] }}</a>@include('support.icon', ['icon' => 'chevron'])<span>Guia prático</span></nav>
    <div class="article-layout">
        <article class="article-content">
            <header><span class="article-tag">{{ $collection['title'] }}</span><h1>{{ $article['title'] }}</h1><p class="article-summary">{{ $article['summary'] }}</p><div class="article-meta">@include('support.icon', ['icon' => 'clock']) {{ $article['minutes'] }} min de leitura <span>·</span> Atualizado em {{ \Carbon\Carbon::parse($article['updated'])->format('d/m/Y') }}</div></header>
            @foreach($article['sections'] as $section)
            <section id="passo-{{ $loop->iteration }}" class="guide-section"><h2>{{ $section['title'] }}</h2>@foreach($section['paragraphs'] ?? [] as $paragraph)<p>@include('support.text', ['text' => $paragraph])</p>@endforeach @if(!empty($section['steps']))<ol class="guide-steps">@foreach($section['steps'] as $step)<li>@include('support.text', ['text' => $step])</li>@endforeach</ol>@endif
            @foreach($section['figures'] ?? [] as $figure) @include('support.figure') @endforeach
            </section>
            @endforeach
            @if(!empty($article['note']))<aside class="guide-note">@include('support.icon', ['icon' => 'book'])<div><strong>Vale conferir</strong><p>@include('support.text', ['text' => $article['note']])</p></div></aside>@endif
            <div class="article-end">@include('support.icon', ['icon' => 'check'])<p>Próximo passo: experimente essa rotina no seu WNFit.</p><a href="{{ $applicationUrl }}/entrar">Acessar o sistema @include('support.icon', ['icon' => 'arrow-up'])</a></div>
        </article>
        <aside class="article-sidebar"><nav aria-label="Neste guia"><span class="eyebrow">NESTE GUIA</span>@foreach($article['sections'] as $section)<a href="#passo-{{ $loop->iteration }}">{{ $section['title'] }}</a>@endforeach</nav><div class="sidebar-tip">@include('support.icon', ['icon' => $collection['icon']])<h3>Continue aprendendo</h3><p>Veja os outros guias de {{ $collection['title'] }}.</p><a href="{{ $base }}/modulos/{{ $collection['slug'] }}">Explorar módulo @include('support.icon', ['icon' => 'arrow'])</a></div></aside>
    </div>
    @if(count($related))<section class="related-guides"><div class="section-heading"><h2>Continue por aqui</h2></div><div class="article-list">@foreach($related as $item) @include('support.article-row') @endforeach</div></section>@endif
</div>
@endsection
