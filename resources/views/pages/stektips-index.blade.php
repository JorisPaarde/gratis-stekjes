@extends('layouts.site')

@section('title', $title)
@section('meta_description', $metaDescription)
@section('canonical', $canonical)

@push('head')
  @vite(['resources/css/stektips.css', 'resources/css/nu-te-stekken.css'])
@endpush

@section('content')
<section class="section wrap stektips-page">
  <h1 class="stektips-titel">{{ $title }}</h1>
  <p class="section-intro">Zelf nieuwe planten kweken: stekken, zaaien, scheuren en snoeien. Zoek je plant op en lees hoe en wanneer het lukt.</p>

  @php
    $hasExtraFilters = collect($filters)->except('q')->filter(fn ($v) => $v !== '' && $v !== null)->isNotEmpty();
    $pillarKort = [
      'stekken' => 'De manieren van stekken, in water of grond, en het beste moment.',
      'snoeien' => 'Wanneer je snoeit, en hoe je van snoeisel stekjes maakt.',
      'natuurlijk-bewortelen' => 'Wortels zonder stekpoeder, bijvoorbeeld met wilgenwater.',
      'permacultuur-gilde-voedselbos' => 'Planten in lagen, en vermeerderen wat lokaal goed groeit.',
      'vermeerderen' => 'Scheuren, afleggen en enten, als stekken niet de beste route is.',
    ];
  @endphp

  <form class="stektips-zoek" method="get" action="{{ route('stektips.index') }}" role="search">
    <label for="stektips-q">Welke plant wil je stekken?</label>
    <div class="stektips-zoek-veld">
      <input id="stektips-q" type="search" name="q" value="{{ $filters['q'] }}" maxlength="120" placeholder="Bijvoorbeeld monstera, vijg of wilgenwater" autocomplete="off">
      <button class="btn" type="submit"><x-icon name="zoeken" :size="18" /> Zoek</button>
    </div>
    <details class="stektips-meer-filters" @if($hasExtraFilters) open @endif>
      <summary>Meer filters</summary>
      <div class="stektips-filter-grid">
        <label for="stektips-categorie">Onderwerp
          <select id="stektips-categorie" name="categorie">
            <option value="">Alle onderwerpen</option>
            @foreach($categorieen as $cat)
              <option value="{{ $cat['slug'] }}" @selected($filters['categorie'] === $cat['slug'])>{{ $cat['naam'] }}</option>
            @endforeach
          </select>
        </label>
        <label for="stektips-seizoen">Seizoen
          <select id="stektips-seizoen" name="seizoen">
            <option value="">Alle seizoenen</option>
            @foreach($seizoenen as $slug => $label)
              <option value="{{ $slug }}" @selected($filters['seizoen'] === $slug)>{{ $label }}</option>
            @endforeach
          </select>
        </label>
        <label for="stektips-maand">Maand
          <select id="stektips-maand" name="maand">
            <option value="">Alle maanden</option>
            @foreach($maanden as $nr => $label)
              <option value="{{ $nr }}" @selected((string) $filters['maand'] === (string) $nr)>{{ $label }}</option>
            @endforeach
          </select>
        </label>
        <label for="stektips-locatie">Binnen of buiten
          <select id="stektips-locatie" name="locatie">
            <option value="">Overal</option>
            @foreach($locaties as $slug => $label)
              <option value="{{ $slug }}" @selected($filters['locatie'] === $slug)>{{ $label }}</option>
            @endforeach
          </select>
        </label>
        <label for="stektips-soort">Soort
          <select id="stektips-soort" name="soort">
            <option value="">Alle soorten</option>
            @foreach($alleSoorten as $s)
              <option value="{{ $s['slug'] }}" @selected($filters['soort'] === $s['slug'])>{{ $s['naam'] }}</option>
            @endforeach
          </select>
        </label>
        <div class="stektips-filter-actions">
          <button class="btn btn-secondary" type="submit">Filters toepassen</button>
        </div>
      </div>
    </details>
    @if($hasActiveFilters)
      <p class="stektips-wis"><a href="{{ route('stektips.index') }}">Wis zoekopdracht en filters</a></p>
    @endif
  </form>

  @if(!empty($pillars))
    <nav class="stektips-pillars" aria-label="Onderwerpen">
      <ul class="stektips-pillar-list">
        @foreach($pillars as $pillar)
          <li>
            <a class="stektips-pillar-card" href="{{ route('stektips.artikel', ['slug' => $pillar['slug']]) }}">
              <span class="stektips-pillar-titel">{{ $pillar['titel'] }}</span>
              <span class="stektips-pillar-tekst">{{ $pillarKort[$pillar['slug']] ?? \Illuminate\Support\Str::before((string) ($pillar['samenvatting'] ?? ''), '. ').'.' }}</span>
            </a>
          </li>
        @endforeach
      </ul>
    </nav>
  @endif

  @if(!empty($beginners))
    <section class="stektips-beginners" aria-labelledby="beginners-titel">
      <h2 id="beginners-titel">Makkelijk om mee te beginnen</h2>
      <ul class="stek-kaarten">
        @foreach($beginners as $b)
          <li class="stek-kaart">
            <a class="stek-kaart-link" href="{{ $b['url'] }}">
              <img class="stek-kaart-foto" src="{{ $b['afbeelding']['url'] }}" alt="{{ $b['afbeelding']['alt'] }}" width="400" height="300" loading="lazy" decoding="async">
              <span class="stek-kaart-body">
                <span class="badge">Makkelijk</span>
                <span class="stek-kaart-naam">{{ $b['naam'] }}</span>
              </span>
            </a>
            @if(!empty($b['afbeelding']['auteur']))
              <p class="stek-kaart-credit">Foto: {{ $b['afbeelding']['auteur'] }} · {{ $b['afbeelding']['licentie'] }}</p>
            @endif
          </li>
        @endforeach
      </ul>
    </section>
  @endif

  <h2 class="stektips-alle-titel">{{ $hasActiveFilters ? 'Resultaten' : 'Alle stektips en artikelen' }}</h2>
  <p class="muted stektips-resultaat" role="status">
    {{ $totalCount }} {{ $totalCount === 1 ? 'resultaat' : 'resultaten' }}
    ({{ $artikelenCount }} {{ $artikelenCount === 1 ? 'artikel' : 'artikelen' }},
    {{ $soortenCount }} {{ $soortenCount === 1 ? 'soort' : 'soorten' }}).
  </p>

  @if($totalCount === 0)
    <p class="empty-state">Niets gevonden met deze keuzes. <a href="{{ route('stektips.index') }}">Toon alles</a></p>
  @else
    @php
      $zichtbaar = $hasActiveFilters ? $entries : array_slice($entries, 0, 24);
      $rest = $hasActiveFilters ? [] : array_slice($entries, 24);
    @endphp
    <div class="grid-cards stektips-entries">
      @foreach($zichtbaar as $entry)
        <a class="soft-card stektips-entry stektips-entry--{{ $entry['type'] }}" href="{{ $entry['url'] }}">
          @if(!empty($entry['afbeelding']['url']))
            <img
              class="stektips-entry-img"
              src="{{ $entry['afbeelding']['url'] }}"
              alt="{{ $entry['afbeelding']['alt'] }}"
              width="320"
              height="200"
              loading="lazy"
              decoding="async"
            >
          @endif
          <h2 class="stektips-entry-title">{{ $entry['titel'] }}</h2>
          @if(!empty($entry['badges']))
            <p class="stektips-entry-badges">
              @foreach($entry['badges'] as $badge)
                <span class="stektips-badge">{{ $badge }}</span>
              @endforeach
            </p>
          @endif
          @if($entry['meta'] !== '')
            <p>{{ \Illuminate\Support\Str::limit($entry['meta'], 140) }}</p>
          @endif
          @if(!empty($entry['afbeelding']) && empty($entry['afbeelding']['is_fallback']))
            <p class="stektips-entry-credit muted">
              {{ $entry['afbeelding']['auteur'] }} · {{ $entry['afbeelding']['licentie'] }}
            </p>
          @endif
        </a>
      @endforeach
    </div>
    @if(count($rest))
      <details class="stektips-rest">
        <summary class="btn btn-secondary">Toon alle {{ $totalCount }} stektips en artikelen</summary>
        <div class="grid-cards stektips-entries">
          @foreach($rest as $entry)
        <a class="soft-card stektips-entry stektips-entry--{{ $entry['type'] }}" href="{{ $entry['url'] }}">
          @if(!empty($entry['afbeelding']['url']))
            <img
              class="stektips-entry-img"
              src="{{ $entry['afbeelding']['url'] }}"
              alt="{{ $entry['afbeelding']['alt'] }}"
              width="320"
              height="200"
              loading="lazy"
              decoding="async"
            >
          @endif
          <h2 class="stektips-entry-title">{{ $entry['titel'] }}</h2>
          @if(!empty($entry['badges']))
            <p class="stektips-entry-badges">
              @foreach($entry['badges'] as $badge)
                <span class="stektips-badge">{{ $badge }}</span>
              @endforeach
            </p>
          @endif
          @if($entry['meta'] !== '')
            <p>{{ \Illuminate\Support\Str::limit($entry['meta'], 140) }}</p>
          @endif
          @if(!empty($entry['afbeelding']) && empty($entry['afbeelding']['is_fallback']))
            <p class="stektips-entry-credit muted">
              {{ $entry['afbeelding']['auteur'] }} · {{ $entry['afbeelding']['licentie'] }}
            </p>
          @endif
        </a>
          @endforeach
        </div>
      </details>
    @endif
  @endif

  @if(!empty($faq))
    <section class="stektips-faq" id="veelgestelde-vragen" aria-labelledby="faq-titel">
      <h2 id="faq-titel">Veelgestelde vragen over stekken</h2>
      @foreach($faq as $f)
        <details class="stektips-faq-item">
          <summary>{{ $f['vraag'] }}</summary>
          <div class="stektips-faq-antwoord">
            <p>{{ $f['antwoord'] }}</p>
            @if($f['link'])
              <p><a href="{{ url($f['link']['pad']) }}">{{ $f['link']['label'] }}</a></p>
            @endif
          </div>
        </details>
      @endforeach
    </section>
  @endif

  <aside class="soft-card stektips-proef">
    <h2 class="stektips-proef-title"><a href="{{ route('stektips.show', ['slug' => 'herfstolijfwilg']) }}">Jouw eigen proef</a></h2>
    <p>Probeer eens 50 stekken van de <a href="{{ route('stektips.show', ['slug' => 'herfstolijfwilg']) }}">herfstolijfwilg</a> in <a href="{{ route('stektips.artikel', ['slug' => 'wilgenwater']) }}">wilgenwater</a>. Zet de helft in vochtige stekgrond in een koude bak, noteer per bakje wat er gebeurt en deel de uitkomst in het voorjaar.</p>
  </aside>
  <p class="stektips-meer"><a href="{{ route('hulpmiddelen') }}">Hulpmiddelen</a></p>
</section>
@endsection
