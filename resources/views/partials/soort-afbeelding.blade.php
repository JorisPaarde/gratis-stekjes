@php
  /** @var array $afbeelding */
  $isFallback = (bool) ($afbeelding['is_fallback'] ?? true);
@endphp
<figure class="soort-afbeelding{{ $isFallback ? ' soort-afbeelding--fallback' : '' }}">
  <img
    src="{{ $afbeelding['url'] }}"
    alt="{{ $afbeelding['alt'] }}"
    width="640"
    height="480"
    loading="lazy"
    decoding="async"
  >
  <figcaption class="soort-afbeelding-credit">
    @if($isFallback)
      <span>Eigen illustratie · {{ $afbeelding['licentie'] }}</span>
    @else
      <span>
        Foto:
        @if(!empty($afbeelding['bron_url']))
          <a href="{{ $afbeelding['bron_url'] }}" rel="noopener noreferrer">{{ $afbeelding['auteur'] }}</a>
        @else
          {{ $afbeelding['auteur'] }}
        @endif
        ·
        @if(!empty($afbeelding['licentie_url']))
          <a href="{{ $afbeelding['licentie_url'] }}" rel="noopener noreferrer license">{{ $afbeelding['licentie'] }}</a>
        @else
          {{ $afbeelding['licentie'] }}
        @endif
        · via Wikimedia Commons
      </span>
    @endif
  </figcaption>
</figure>
