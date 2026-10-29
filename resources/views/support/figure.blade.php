<figure class="guide-figure">
    <a href="{{ asset($figure['src']) }}" target="_blank" rel="noopener" aria-label="Ampliar imagem: {{ $figure['alt'] }} (abre em outra aba)">
        <img src="{{ asset($figure['src']) }}" alt="{{ $figure['alt'] }}" width="{{ $figure['width'] }}" height="{{ $figure['height'] }}" loading="lazy" decoding="async">
        <span class="figure-zoom">@include('support.icon', ['icon' => 'arrow-up']) Ampliar imagem</span>
    </a>
    <figcaption><strong>{{ $figure['caption'] }}</strong><span>Interface do WNFit com dados de demonstração.</span></figcaption>
</figure>
