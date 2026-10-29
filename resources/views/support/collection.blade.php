@extends('support.layout')
@section('title', $collection['title'])
@section('description', $collection['description'])
@section('content')
<div class="subpage-search"><div class="container">@include('support.search')</div></div>
<div class="container collection-page">
    <nav class="breadcrumbs" aria-label="Localização"><a href="{{ $base ?: '/' }}">Central de ajuda</a>@include('support.icon', ['icon' => 'chevron'])<span>{{ $collection['title'] }}</span></nav>
    <header class="collection-heading"><span class="module-icon icon-{{ $collection['slug'] }}">@include('support.icon', ['icon' => $collection['icon']])</span><h1>{{ $collection['title'] }}</h1><p>{{ $collection['description'] }}</p><span class="reading-time">{{ count($collectionArticles) }} guias · Tutoriais passo a passo</span></header>
    <div class="article-list">@foreach($collectionArticles as $item) @include('support.article-row') @endforeach</div>
    <a class="back-link collection-back" href="{{ $base ?: '/' }}">← Explorar outros módulos</a>
</div>
@endsection
