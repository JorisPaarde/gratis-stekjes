@php
  $methodeLabels = [
    'stek' => 'Stek',
    'scheut' => 'Scheut',
    'deling' => 'Deling',
    'zaad' => 'Zaad',
    'enten' => 'Enten',
    'afleggen' => 'Afleggen',
  ];
  $headingId = $headingId ?? 'nu-te-stekken-titel';
  $kaarten = $nuTeStekken['uitgelicht'] ?? [];
@endphp
@if(count($kaarten))
<section class="section wrap nu-te-stekken" aria-labelledby="{{ $headingId }}">
  <div class="nu-te-stekken-kop">
    <div>
      <h2 id="{{ $headingId }}">Nu te stekken — {{ $nuTeStekken['maand_naam'] }}</h2>
      <p class="section-intro">{{ $nuTeStekken['intro'] }} Tips zijn algemeen; kijk altijd naar je eigen plant en het weer.</p>
    </div>
    @if(!empty($nuTeStekken['stektips_index']))
      <a class="nu-te-stekken-alle" href="{{ $nuTeStekken['stektips_index'] }}">Alle stektips <span aria-hidden="true">→</span></a>
    @endif
  </div>
  <ul class="stek-kaarten">
    @foreach($kaarten as $kaart)
      @php($img = $kaart['afbeelding'])
      <li class="stek-kaart">
        <a class="stek-kaart-link" href="{{ $kaart['url'] }}">
          <img
            class="stek-kaart-foto{{ !empty($img['is_fallback']) ? ' is-illustratie' : '' }}"
            src="{{ $img['url'] }}"
            alt="{{ $img['alt'] }}"
            width="400"
            height="300"
            loading="lazy"
            decoding="async"
          >
          <span class="stek-kaart-body">
            <span class="badge">{{ $methodeLabels[$kaart['methode']] ?? ucfirst($kaart['methode']) }}</span>
            <span class="stek-kaart-naam">{{ $kaart['naam'] }}</span>
          </span>
        </a>
        @if(empty($img['is_fallback']) && !empty($img['auteur']))
          <p class="stek-kaart-credit">
            Foto:
            @if(!empty($img['bron_url']))<a href="{{ $img['bron_url'] }}" rel="noopener noreferrer">{{ $img['auteur'] }}</a>@else{{ $img['auteur'] }}@endif
            ·
            @if(!empty($img['licentie_url']))<a href="{{ $img['licentie_url'] }}" rel="noopener noreferrer license">{{ $img['licentie'] }}</a>@else{{ $img['licentie'] }}@endif
          </p>
        @endif
      </li>
    @endforeach
  </ul>
</section>
@endif
