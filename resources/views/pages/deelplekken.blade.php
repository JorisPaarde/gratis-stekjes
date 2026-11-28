@extends('layouts.site')

@section('title', $title)
@section('meta_description', $metaDescription)
@section('canonical', $canonical)
@section('og_image', '/assets/og/deelplekken.jpg')
@section('body_class', 'page-deelplekken')

@push('head')
  <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" integrity="sha256-p4NxAoJBhIIN+hmNHrzRCf9tD/miZyoHS5obTRR9BMY=" crossorigin="">
  <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js" integrity="sha256-20nQCchB9co0qIjJZRGuk2/Z9VM+kNiyxNV1lvTlZBo=" crossorigin=""></script>
  {{-- Markercluster via unpkg (zelfde L als Leaflet-CDN); cluster-CSS zit in deelplekken.css (GP-huisstijl). --}}
  <script src="https://unpkg.com/leaflet.markercluster@1.5.3/dist/leaflet.markercluster.js" integrity="sha256-Hk4dIpcqOSb0hZjgyvFOP+cEmDXUKKNE/tT542ZbNQg=" crossorigin=""></script>
  <script>
    window.STEKJES_DEELPLEKKEN = {
      api: @json(route('api.deelplekken')),
      type: @json($filters['type']),
      provincie: @json($filters['provincie']),
      plaats: @json($filters['plaats']),
      types: @json($typeLabels)
    };
  </script>
  @vite(['resources/css/aanbod.css', 'resources/css/deelplekken.css', 'resources/css/stektips.css'])
@endpush

@section('content')
@php
  $isFilter = $filters['type'] !== '' || $filters['provincie'] !== '' || $filters['plaats'] !== '';
  $bronUrlCounts = app(\App\Services\Deelplekken::class)->bronUrlCounts();
  $bronLabel = function ($plek) use ($bronUrlCounts): string {
      $evenement = ! empty($plek->tijdelijk);
      return \App\Support\BronLabel::for(
          (string) ($plek->bron_url ?? ''),
          (string) ($plek->naam ?? ''),
          $evenement,
          $bronUrlCounts,
      );
  };
  $meervoud = [
      'plantenasiel' => 'Plantenasiels', 'plantenbieb' => 'Plantenbiebs', 'ruilkast' => 'Ruilkasten',
      'stekjesruilpunt' => 'Stekjesruilpunten', 'zaadbibliotheek' => 'Zadenbibliotheken',
      'voedselbos' => 'Voedselbossen', 'tuinvereniging' => 'Tuinverenigingen',
  ];
@endphp
<section class="section wrap deelplekken-page">
  @include('partials.plekken-tabs')
  <h1 class="deelplekken-titel">Vind een deelplek in de buurt</h1>
  <p class="section-intro">Plantenasiels, plantenbiebs, ruilkastjes en zadenbibliotheken in Nederland en België: plekken waar je planten, stekjes of zaden kunt brengen, ruilen of ophalen. Kijk vóór je gaat even op de website van de plek voor de openingstijden. Zoek je liever per plaats? Bekijk <a href="{{ route('gratis-planten.index') }}">gratis planten per stad</a>. Ken je een plek die hier nog niet staat? <a href="{{ route('voor-organisaties.aanmelden') }}">Plek aanmelden</a>. Na controle wordt hij zichtbaar op de kaart.</p>

  <form class="zoekbalk deelplekken-zoek" method="get" action="{{ route('deelplekken') }}" role="search">
    @if($filters['type'] !== '')
      <input type="hidden" name="type" value="{{ $filters['type'] }}">
    @endif
    <div class="zoekbalk-veld zoekbalk-veld--waar">
      <label for="dp-plaats">Plaats</label>
      <input id="dp-plaats" type="search" name="plaats" value="{{ $filters['plaats'] }}" maxlength="120" placeholder="Bijvoorbeeld Utrecht of Gent">
    </div>
    <div class="zoekbalk-veld">
      <label for="dp-provincie">Provincie</label>
      <select id="dp-provincie" name="provincie">
        <option value="">Alle provincies</option>
        @foreach($provincies as $p)
          <option value="{{ $p }}" @selected($filters['provincie'] === $p)>{{ $p }}</option>
        @endforeach
      </select>
    </div>
    <button class="btn zoekbalk-knop" type="submit"><x-icon name="zoeken" :size="18" /> Zoek</button>
  </form>

  <nav class="deelplekken-types" aria-label="Soort plek">
    <a class="dp-chip {{ $filters['type'] === '' ? 'is-actief' : '' }}" href="{{ route('deelplekken') }}">Alles</a>
    @foreach($typeLabels as $key => $label)
      @if(($typeAantallen[$key] ?? 0) > 0)
        <a class="dp-chip {{ $filters['type'] === $key ? 'is-actief' : '' }}" href="{{ route('deelplekken', ['type' => $key]) }}">{{ $meervoud[$key] ?? $label }}</a>
      @endif
    @endforeach
    @if(($uitdeeldagenAantal ?? 0) > 0)
      <a class="dp-chip dp-chip--uitdeeldagen {{ $filters['type'] === 'uitdeeldagen' ? 'is-actief' : '' }}" href="{{ route('deelplekken', ['type' => 'uitdeeldagen']) }}">
        Evenementen
        @if($filters['type'] !== 'uitdeeldagen')
          <span>{{ $uitdeeldagenAantal }}</span>
        @endif
      </a>
    @endif
  </nav>

  @if($items->isEmpty())
    <div class="deelplekken-leeg" role="status">
      <p class="deelplekken-leeg-titel">
        @if($filters['plaats'] !== '')
          Geen deelplek gevonden in {{ $filters['plaats'] }}.
        @else
          Geen deelplek gevonden met deze keuzes.
        @endif
      </p>
      <p>Ken je er een? Meld de plek aan. Na controle wordt hij zichtbaar op de kaart.</p>
      <p class="deelplekken-leeg-acties">
        <a class="btn btn-secondary" href="{{ route('voor-organisaties.aanmelden') }}">Plek aanmelden</a>
        <a href="{{ route('deelplekken') }}">Toon alle deelplekken</a>
      </p>
    </div>
  @endif

  <div id="deelplekken-kaart" role="img" aria-label="Kaart met deelplekken voor planten"></div>

  @if($items->isNotEmpty())
    <p class="zoek-status" id="deelplekken-status-tekst">
      @if($isFilter)
        Gevonden deelplekken · <a href="{{ route('deelplekken') }}">Toon alles</a>
      @else
        Steeds meer deelplekken in Nederland en België
      @endif
    </p>
  @endif
  <span class="visually-hidden" id="deelplekken-status" aria-live="polite"></span>

  @php $plekPayloads = []; @endphp
  <ul class="zoek-lijst deelplekken-lijst" id="deelplekken-lijst">
    @forelse($items as $index => $plek)
      @php
        $isEvenement = ! empty($plek->tijdelijk);
        $typeLabel = $isEvenement
          ? ($plek->type_label ?? ($evenementTypeLabels[$plek->type] ?? $plek->type))
          : ($typeLabels[$plek->type] ?? $plek->type);
        $datumLabel = trim((string) ($plek->datum_label ?? ''));
        $lijstVerborgen = $index >= 12;
        $plekKind = $isEvenement ? 'evenement' : 'deelplek';
        $plekId = $plek->id ?? null;
        $plekKey = $plekKind.':'.($plekId ?? $plek->naam);
        $plekPayloads[] = [
            'id' => $plekId,
            'key' => $plekKey,
            'kind' => $plekKind,
            'naam' => (string) $plek->naam,
            'type' => (string) $plek->type,
            'type_label' => $typeLabel,
            'plaats' => (string) ($plek->plaats ?? ''),
            'provincie' => (string) ($plek->provincie ?? ''),
            'adres' => (string) ($plek->adres ?? ''),
            'beschrijving' => trim((string) ($plek->beschrijving ?? $plek->omschrijving ?? '')),
            'open_regel' => $isEvenement ? null : ($plek->openRegel() ?? null),
            'datum_label' => $datumLabel,
            'bron_url' => (string) ($plek->bron_url ?? ''),
            'bron_label' => $plek->bron_url ? $bronLabel($plek) : '',
            'lat' => $plek->lat ?? null,
            'lng' => $plek->lng ?? null,
        ];
      @endphp
      <li class="zoek-item zoek-item--plek zoek-item--deelplek{{ $isEvenement ? ' zoek-item--evenement' : '' }}{{ $lijstVerborgen ? ' is-lijst-verborgen' : '' }}" data-kind="{{ $isEvenement ? 'evenement' : 'deelplek' }}" data-type="{{ $plek->type }}" @if($lijstVerborgen) hidden @endif>
        <div class="zoek-item-kop"><h2>{{ $plek->naam }}</h2></div>
        <p class="zoek-item-meta">
          @if($isEvenement)
            <span class="badge badge--tijdelijk">Tijdelijk</span>
          @endif
          <span class="badge badge--{{ $plek->type }}">{{ $typeLabel }}</span>
          {{ $plek->plaats }}@if($plek->provincie) · {{ $plek->provincie }}@endif
        </p>
        @if($isEvenement && $datumLabel !== '')
          <p class="zoek-item-datum">{{ $datumLabel }}</p>
        @endif
        @if($plek->adres !== '')
          <p class="zoek-item-adres">{{ $plek->adres }}</p>
        @endif
        <p class="zoek-item-acties">
          <button type="button" class="zoek-regel-meer-knop" data-plek-open="{{ $plekKey }}" aria-haspopup="dialog">Meer info</button>
          <button type="button" class="zoek-op-kaart" data-lat="{{ $plek->lat }}" data-lng="{{ $plek->lng }}">Op de kaart</button>
          @if($plek->bron_url)
            <a href="{{ $plek->bron_url }}" rel="noopener noreferrer" target="_blank">{{ $bronLabel($plek) }}</a>
          @endif
        </p>
        @if($isEvenement === false && $plek->geraadpleegd)
          <p class="zoek-item-datum">Gecontroleerd op {{ $plek->geraadpleegd->locale('nl')->translatedFormat('j F Y') }}</p>
        @endif
      </li>
    @empty
    @endforelse
  </ul>
  @if(!empty($plekPayloads))
    <script type="application/json" data-plek-items>{!! json_encode($plekPayloads, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP) !!}</script>
  @endif
  @if($items->count() > 12)
    <p class="deelplekken-meer">
      <button type="button" class="btn btn-secondary" id="deelplekken-laad-meer" data-stap="12" aria-controls="deelplekken-lijst">Laad meer</button>
    </p>
    <p class="visually-hidden" id="deelplekken-meer-status" aria-live="polite"></p>
  @endif

  @if(!empty($faq))
    <section class="stektips-faq deelplekken-faq" id="veelgestelde-vragen" aria-labelledby="faq-titel">
      <h2 id="faq-titel">Veelgestelde vragen</h2>
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

  <p class="muted deelplekken-voet">Mis je een plek, of klopt er iets niet? <a href="{{ route('voor-organisaties.aanmelden') }}">Plek aanmelden</a>. Na controle wordt hij zichtbaar op de kaart. Op de <a href="{{ route('aanbod') }}">aanbodkaart</a> zie je ook wat mensen in de buurt weggeven.<br>
  Kaart: © OpenStreetMap-bijdragers. De plekken staan ongeveer op het openbare adres uit de bron. Dit is geen officiële lijst van samenwerkingspartners.</p>
</section>
@endsection

@push('scripts')
  @vite(['resources/js/deelplekken.js'])
@endpush
