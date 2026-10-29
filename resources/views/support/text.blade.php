@php
    $parts = preg_split('/(\[\[[a-z0-9-]+\|[^\]\r\n]+\]\])/', $text, -1, PREG_SPLIT_DELIM_CAPTURE);
@endphp
@foreach($parts as $part)@if(preg_match('/^\[\[([a-z0-9-]+)\|([^\]\r\n]+)\]\]$/', $part, $match) && collect($articles)->contains('slug', $match[1]))<a class="guide-inline-link" href="{{ $base }}/artigos/{{ $match[1] }}">{{ $match[2] }}</a>@else{{ $part }}@endif
@endforeach
