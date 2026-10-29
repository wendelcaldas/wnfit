@extends('support.layout')
@section('content')
<section class="hero">
    <div class="container hero-inner">
        <span class="eyebrow"><span class="status-dot"></span> APRENDA NO SEU RITMO</span>
        <h1>Como podemos <span>ajudar?</span></h1>
        <p>Da primeira matrícula à rotina do estúdio.<br class="mobile-break"> Encontre o próximo passo aqui.</p>
        @include('support.search')
        <div class="search-suggestions"><span>Que tal começar por:</span><a href="{{ $base }}/artigos/cadastrar-aluno">Cadastrar aluno</a><a href="{{ $base }}/artigos/links-aula-experimental">Aula experimental</a><a href="{{ $base }}/artigos/entregar-ficha">Entregar ficha</a></div>
    </div>
    <div class="hero-decoration" aria-hidden="true"><span></span><span></span><span></span></div>
</section>
<div class="container content-area">
@if($query !== '')
    <section class="search-results" aria-labelledby="results-heading">
        <a class="back-link" href="{{ $base ?: '/' }}">← Todos os módulos</a>
        <div class="section-heading"><div><span class="eyebrow">SUA BUSCA</span><h2 id="results-heading">Resultados para “{{ $query }}”</h2></div><span class="count">{{ count($results) }} {{ count($results) === 1 ? 'guia encontrado' : 'guias encontrados' }}</span></div>
        @if(count($results))
        <div class="article-list">@foreach($results as $item) @include('support.article-row') @endforeach</div>
        @else
        <div class="empty-state">@include('support.icon', ['icon' => 'search'])<h3>Ainda não encontramos esse assunto.</h3><p>Tente uma palavra mais simples, como “plano”, “agenda” ou “senha”. Você também pode explorar os módulos abaixo.</p></div>
        @endif
    </section>
@else
    <section class="onboarding" aria-labelledby="onboarding-title">
        <div class="onboarding-intro"><span class="eyebrow">PRIMEIROS PASSOS</span><h2 id="onboarding-title">Seu negócio pronto<br>para começar.</h2><p>Um caminho curto para colocar<br>o WNFit na sua rotina.</p><a href="{{ $base }}/artigos/organizar-seu-negocio">Ver o guia de boas-vindas @include('support.icon', ['icon' => 'arrow'])</a></div>
        <ol class="onboarding-steps">@foreach($onboarding as $slug) @php($item = collect($articles)->firstWhere('slug', $slug))<li><a href="{{ $base }}/artigos/{{ $slug }}"><span class="step-number">0{{ $loop->iteration }}</span><div><strong>{{ ['Configure seus planos', 'Defina os horários', 'Cadastre o primeiro aluno', 'Entregue uma ficha'][$loop->index] }}</strong><span>{{ $item['minutes'] }} min de leitura</span></div>@include('support.icon', ['icon' => 'arrow'])</a></li>@endforeach</ol>
    </section>
@endif
    <section class="modules" aria-labelledby="modules-title">
        <div class="section-heading"><div><span class="eyebrow">ENCONTRE SEU ASSUNTO</span><h2 id="modules-title">Explore por módulo</h2></div><span class="count">{{ count($collections) }} temas · {{ count($articles) }} guias práticos</span></div>
        <div class="module-grid">@foreach($collections as $collection)
            <a class="module-card" href="{{ $base }}/modulos/{{ $collection['slug'] }}"><div class="module-card-top"><span class="module-icon icon-{{ $collection['slug'] }}">@include('support.icon', ['icon' => $collection['icon']])</span>@include('support.icon', ['icon' => 'arrow-up'])</div><h3>{{ $collection['title'] }}</h3><p>{{ $collection['description'] }}</p><span class="module-count">{{ collect($articles)->where('collection', $collection['slug'])->count() }} guias @include('support.icon', ['icon' => 'chevron'])</span></a>
        @endforeach</div>
    </section>
    @if($query === '')
    <section class="quick-guides" aria-labelledby="quick-title"><div class="section-heading"><div><span class="eyebrow">DIRETO AO PONTO</span><h2 id="quick-title">Guias para o dia a dia</h2></div></div><div class="quick-grid">@foreach($popular as $slug) @php($item = collect($articles)->firstWhere('slug', $slug))<a href="{{ $base }}/artigos/{{ $slug }}">@include('support.icon', ['icon' => 'book'])<div><h3>{{ $item['title'] }}</h3><span>{{ $item['minutes'] }} min de leitura</span></div>@include('support.icon', ['icon' => 'chevron'])</a>@endforeach</div></section>
    @endif
</div>
@endsection
