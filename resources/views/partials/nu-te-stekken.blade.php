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
@endphp
<section class="section wrap nu-te-stekken" aria-labelledby="{{ $headingId }}">
  <h2 id="{{ $headingId }}">Nu te stekken — {{ $nuTeStekken['maand_naam'] }}</h2>
  <p class="section-intro">{{ $nuTeStekken['intro'] }}</p>
  <div class="nu-te-stekken-grid">
    @foreach($nuTeStekken['groepen'] as $groep)
      <article class="nu-te-stekken-card">
        <p class="nu-te-stekken-methode"><span class="badge">{{ $methodeLabels[$groep['methode'] ?? ''] ?? ($groep['methode'] ?? '') }}</span></p>
        <h3>{{ $groep['titel'] ?? '' }}</h3>
        @if(!empty($groep['tip']))
          <p>{{ $groep['tip'] }}</p>
        @endif
        <p class="nu-te-stekken-planten">
          @foreach(($groep['planten'] ?? []) as $p)
            @if(!empty($p['stek_url']))
              <a class="nu-te-stekken-plant" href="{{ $p['stek_url'] }}">{{ $p['naam'] ?? '' }}</a>
            @else
              <span class="nu-te-stekken-plant is-plain">{{ $p['naam'] ?? '' }}</span>
            @endif
          @endforeach
        </p>
        @if(!empty($groep['bron']['url']) && !empty($groep['bron']['naam']))
          <p class="nu-te-stekken-bron">Bron: <a href="{{ $groep['bron']['url'] }}" rel="noopener noreferrer" target="_blank">{{ $groep['bron']['naam'] }}</a></p>
        @endif
      </article>
    @endforeach
  </div>
  @if(!empty($nuTeStekken['stektips_index']))
    <p class="muted">Meer soorten: <a href="{{ $nuTeStekken['stektips_index'] }}">alle stektips</a>.</p>
  @else
    <p class="muted">Uitgebreide stekpagina’s per soort: <em>binnenkort</em>.</p>
  @endif
</section>
