<form class="search-box" action="{{ $base ?: '/' }}" method="get" role="search">
    @include('support.icon', ['icon' => 'search'])
    <label class="sr-only" for="help-search">Buscar na central de ajuda</label>
    <input id="help-search" type="search" name="q" value="{{ $query ?? '' }}" maxlength="120" placeholder="Busque por alunos, treinos, cobranças..." autocomplete="off">
    <button type="submit">Buscar @include('support.icon', ['icon' => 'arrow'])</button>
</form>
