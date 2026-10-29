@extends('layouts.site')

@section('title', $title)
@section('meta_description', $metaDescription)
@section('canonical', $canonical)

@push('head')
  @vite(['resources/css/stektips.css'])
@endpush

@section('content')
<section class="section wrap stektips-page">
  <h1 class="page-title">Stektips &amp; kennisbank</h1>
  <p class="section-intro">Artikelen over stekmethoden, timing, wortelbevordering en permacultuur, plus soortpagina’s. Filter en zoek werken zonder JavaScript. Soorten van de EU-Unielijst invasieve exoten horen hier niet tussen.</p>

  <form class="form stektips-filters" method="get" action="{{ route('stektips.index') }}" role="search">
    <div class="stektips-filter-grid">
      <label for="stektips-q">Zoeken
        <input id="stektips-q" type="search" name="q" value="{{ $filters['q'] }}" maxlength="120" placeholder="Bijv. wilgenwater of munt" autocomplete="off">
      </label>
      <label for="stektips-categorie">Categorie / onderwerp
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
      <label for="stektips-locatie">Binnen / buiten
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
        <button class="btn" type="submit">Filteren</button>
        @if($hasActiveFilters)
          <a class="btn btn-secondary" href="{{ route('stektips.index') }}">Wis filters</a>
        @endif
      </div>
    </div>
  </form>

  <p class="muted stektips-resultaat" role="status">
    {{ $totalCount }} {{ $totalCount === 1 ? 'resultaat' : 'resultaten' }}
    ({{ $artikelenCount }} {{ $artikelenCount === 1 ? 'artikel' : 'artikelen' }},
    {{ $soortenCount }} {{ $soortenCount === 1 ? 'soort' : 'soorten' }}).
  </p>

  @if($totalCount === 0)
    <p class="empty-state">Geen treffers voor deze filters. <a href="{{ route('stektips.index') }}">Toon alles</a></p>
  @else
    <div class="grid-cards stektips-entries">
      @foreach($entries as $entry)
        <a class="soft-card stektips-entry stektips-entry--{{ $entry['type'] }}" href="{{ $entry['url'] }}">
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
        </a>
      @endforeach
    </div>
  @endif

  <aside class="soft-card stektips-proef">
    <h2 class="stektips-proef-title"><a href="{{ route('stektips.show', ['slug' => 'herfstolijfwilg']) }}">Joris' eigen proef</a></h2>
    <p>50 stekken van de <a href="{{ route('stektips.show', ['slug' => 'herfstolijfwilg']) }}">herfstolijfwilg</a> in <a href="{{ route('stektips.artikel', ['slug' => 'wilgenwater']) }}">wilgenwater</a> zijn een mooie eerste case. Zet de helft in vochtige stekgrond in een koude bak, noteer per bakje wat er gebeurt, en publiceer de uitkomst in het voorjaar.</p>
  </aside>
  <p class="stektips-meer"><a href="{{ route('hulpmiddelen') }}">Hulpmiddelen</a></p>
</section>
@endsection
