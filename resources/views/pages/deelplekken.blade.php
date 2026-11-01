@extends('layouts.site')

@section('title', $title)
@section('meta_description', $metaDescription)
@section('canonical', $canonical)
@section('og_image', '/assets/og/deelplekken.jpg')
@section('body_class', 'page-deelplekken')

@push('head')
  <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" integrity="sha256-p4NxAoJBhIIN+hmNHrzRCf9tD/miZyoHS5obTRR9BMY=" crossorigin="">
  <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js" integrity="sha256-20nQCchB9co0qIjJZRGuk2/Z9VM+kNiyxNV1lvTlZBo=" crossorigin=""></script>
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
  $bronLabel = function ($plek): string {
      $host = strtolower((string) parse_url((string) $plek->bron_url, PHP_URL_HOST));
      $host = preg_replace('/^www\./', '', $host) ?? '';
      $hostKern = preg_replace('/[^a-z0-9]/', '', (string) strstr($host.'.', '.', true));
      $naam = preg_replace('/[^a-z0-9]/', '', \Illuminate\Support\Str::ascii(mb_strtolower((string) $plek->naam)));
      $generiek = ['facebook', 'instagram', 'google', 'nextdoor', 'linktree'];
      if ($hostKern !== '' && ! in_array($hostKern, $generiek, true)
          && (str_contains($naam, $hostKern) || str_contains($hostKern, $naam) || similar_text($naam, $hostKern) >= 8)) {
          return 'Website';
      }
      return 'Meer informatie';
  };
  $meervoud = [
      'plantenasiel' => 'Plantenasiels', 'plantenbieb' => 'Plantenbiebs', 'ruilkast' => 'Ruilkasten',
      'stekjesruilpunt' => 'Stekjesruilpunten', 'zaadbibliotheek' => 'Zadenbibliotheken',
      'voedselbos' => 'Voedselbossen', 'tuinvereniging' => 'Tuinverenigingen',
  ];
@endphp
<section class="section wrap deelplekken-page">
  <h1 class="deelplekken-titel">Vind een deelplek in de buurt</h1>
  <p class="section-intro">Plantenasiels, plantenbiebs, ruilkastjes en zadenbibliotheken in Nederland en België: plekken waar je planten, stekjes of zaden kunt brengen, ruilen of ophalen. Kijk vóór je gaat even op de website van de plek voor de openingstijden.</p>

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
    <a class="dp-chip {{ $filters['type'] === '' ? 'is-actief' : '' }}" href="{{ route('deelplekken') }}">Alles <span>{{ array_sum($typeAantallen) }}</span></a>
    @foreach($typeLabels as $key => $label)
      @if(($typeAantallen[$key] ?? 0) > 0)
        <a class="dp-chip {{ $filters['type'] === $key ? 'is-actief' : '' }}" href="{{ route('deelplekken', ['type' => $key]) }}">{{ $meervoud[$key] ?? $label }} <span>{{ $typeAantallen[$key] }}</span></a>
      @endif
    @endforeach
  </nav>

  <div id="deelplekken-kaart" role="img" aria-label="Kaart met deelplekken voor planten"></div>

  <p class="zoek-status" id="deelplekken-status-tekst">
    {{ $items->count() }} {{ $items->count() === 1 ? 'plek' : 'plekken' }}@if($isFilter) gevonden · <a href="{{ route('deelplekken') }}">Toon alles</a>@endif
  </p>
  <span class="visually-hidden" id="deelplekken-status" aria-live="polite"></span>

  <ul class="zoek-lijst deelplekken-lijst" id="deelplekken-lijst">
    @forelse($items as $plek)
      <li class="zoek-item zoek-item--plek zoek-item--deelplek" data-kind="deelplek" data-type="{{ $plek->type }}">
        <div class="zoek-item-kop"><h2>{{ $plek->naam }}</h2></div>
        <p class="zoek-item-meta"><span class="badge badge--{{ $plek->type }}">{{ $typeLabels[$plek->type] ?? $plek->type }}</span> {{ $plek->plaats }}@if($plek->provincie) · {{ $plek->provincie }}@endif</p>
        @if($plek->adres !== '')
          <p class="zoek-item-adres">{{ $plek->adres }}</p>
        @endif
        <p class="zoek-item-acties">
          <button type="button" class="zoek-op-kaart" data-lat="{{ $plek->lat }}" data-lng="{{ $plek->lng }}">Op de kaart</button>
          @if($plek->bron_url)
            <a href="{{ $plek->bron_url }}" rel="noopener noreferrer" target="_blank">{{ $bronLabel($plek) }}</a>
          @endif
        </p>
        @if($plek->geraadpleegd)
          <p class="zoek-item-datum">Gecontroleerd op {{ $plek->geraadpleegd->locale('nl')->translatedFormat('j F Y') }}</p>
        @endif
      </li>
    @empty
      <li class="zoek-item">
        <p>Geen deelplekken gevonden met deze keuzes. <a href="{{ route('deelplekken') }}">Toon alles</a></p>
      </li>
    @endforelse
  </ul>

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

  <p class="muted deelplekken-voet">Mis je een plek, of klopt er iets niet? <a href="{{ route('voor-organisaties') }}">Organisatie of plek aanmelden</a>. Op de <a href="{{ route('aanbod') }}">aanbodkaart</a> zie je ook wat mensen in de buurt weggeven.<br>
  Kaart: © OpenStreetMap-bijdragers. De plekken staan ongeveer op het openbare adres uit de bron. Dit is geen officiële lijst van samenwerkingspartners.</p>
</section>
@endsection

@push('scripts')
  @vite(['resources/js/deelplekken.js'])
@endpush
