@extends('layouts.site')

@section('title', $title)
@section('meta_description', $metaDescription)
@section('canonical', $canonical)
@section('og_image', '/assets/og/aanbod.jpg')
@section('body_class', 'page-aanbod')

@push('head')
  <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" integrity="sha256-p4NxAoJBhIIN+hmNHrzRCf9tD/miZyoHS5obTRR9BMY=" crossorigin="">
  <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js" integrity="sha256-20nQCchB9co0qIjJZRGuk2/Z9VM+kNiyxNV1lvTlZBo=" crossorigin=""></script>
  <script>
    window.STEKJES_SITE = {
      api_zoeken: @json(route('api.zoeken')),
      api_partners: @json(route('api.partners')),
      api_deelplekken: @json(route('api.deelplekken')),
      bericht_starten: @json(url('/account/bericht-starten')),
      api_nu_te_stekken: @json(route('api.nu-te-stekken')),
      bericht_binnenkort: false,
      plant_filter: @json($plant ?? '')
    };
  </script>
  @vite(['resources/css/aanbod.css', 'resources/css/aanbod-fotos.css', 'resources/css/deelplekken.css', 'resources/css/nu-te-stekken.css'])
@endpush

@section('content')
<section class="section wrap">
  <h1 class="page-title">{{ $heading ?? $title }}</h1>
  <p class="section-intro">
    @if(!empty($plant))
      Je ziet aanbod met <strong>{{ $plant }}</strong>.
      Vul een postcode of plaats in om dichtbij te zoeken. Straatadressen staan niet op de site.
    @else
      Zoek op postcode of plaats in Nederland of België. Je ziet aanbod van leden en vaste plekken zoals zadenbibliotheken en plantenasiels, het dichtstbij eerst. Straatadressen van leden staan niet op de site.
    @endif
  </p>

  <form class="zoekbalk" id="zoek-form" method="get" action="{{ route('aanbod') }}" role="search">
    <div class="zoekbalk-veld zoekbalk-veld--waar">
      <label for="zoek-q">Postcode of plaats</label>
      <input type="text" name="q" id="zoek-q" maxlength="120" value="{{ $q ?? '' }}" placeholder="Bijvoorbeeld 2011 of Haarlem" autocomplete="postal-code" @if(empty($plant)) required @endif>
    </div>
    <div class="zoekbalk-veld">
      <label for="zoek-plant">Plant, stekje of zaad <span class="zoekbalk-optioneel">(optioneel)</span></label>
      <input type="text" name="plant" id="zoek-plant" maxlength="80" value="{{ $plant ?? '' }}" placeholder="Bijvoorbeeld hortensia">
    </div>
    <button class="btn zoekbalk-knop" type="submit"><x-icon name="zoeken" :size="18" /> Zoek</button>
  </form>

  @if(!empty($plant) && isset($plantItems))
    <section class="stektips-plant-aanbod" aria-labelledby="plant-aanbod-titel">
      <h2 id="plant-aanbod-titel" class="stektips-related-title">Aanbod met “{{ $plant }}” (alle plaatsen)</h2>
      @if(count($plantItems) === 0)
        <p class="empty-state" role="status">Er is nog geen aanbod met “{{ $plant }}”. <a href="{{ route('account.registreren') }}">Doe mee</a> en deel zelf iets, of zoek hierboven op plaats.</p>
      @else
        <ul class="zoek-lijst">
          @foreach($plantItems as $item)
            <li class="zoek-item">
              <strong>{{ $item['voornaam'] ?? '' }}</strong>
              <span class="muted"> · {{ $item['locatie_label'] ?? '' }} · {{ $item['type'] ?? '' }}</span>
              <p>{{ $item['tekst'] ?? '' }}</p>
            </li>
          @endforeach
        </ul>
      @endif
    </section>
  @endif

  <div class="marker-filters" id="marker-filters" role="group" aria-labelledby="marker-filters-label">
    <span class="marker-filters-label" id="marker-filters-label">Toon</span>
    <label class="filter-chip"><input type="checkbox" data-aanbod checked><span>Aanbod</span></label>
    <label class="filter-chip"><input type="checkbox" data-partner-type="plantenasiel" checked><span>Plantenasiel</span></label>
    <label class="filter-chip"><input type="checkbox" data-partner-type="plantenbieb" checked><span>Plantenbieb</span></label>
    <label class="filter-chip"><input type="checkbox" data-partner-type="zaadbibliotheek" checked><span>Zadenbibliotheek</span></label>
    <label class="filter-chip"><input type="checkbox" data-partner-type="ruilkast" checked><span>Ruilkast</span></label>
    <label class="filter-chip"><input type="checkbox" data-partner-type="stekjesruilpunt" checked><span>Stekjesruilpunt</span></label>
    <label class="filter-chip"><input type="checkbox" data-partner-type="voedselbos" checked><span>Voedselbos</span></label>
    <label class="filter-chip"><input type="checkbox" data-partner-type="partner" checked><span>Initiatief</span></label>
  </div>

  <div class="search-layout">
    <div id="kaart" role="img" aria-label="Kaart met ongeveer de plekken waar je iets kunt ophalen"></div>
    <div>
      <p class="zoek-status" id="zoek-status" role="status">Vul een postcode of plaats in om te zien wat er dichtbij is. Of <a href="{{ route('account.registreren') }}">maak een account</a> om zelf iets aan te bieden.</p>
      <ul class="zoek-lijst" id="zoek-lijst"></ul>
    </div>
  </div>

  <p class="muted" style="margin-top:1rem">Kaart: © OpenStreetMap-bijdragers. Plaatsen zoek ik op via PDOK en OpenStreetMap; ze staan er bij benadering op.
    @auth
      · <a href="{{ route('account.aanbod') }}">Zelf iets aanbieden</a>
    @else
      · <a href="{{ route('account.registreren') }}">Maak een account</a> om iets aan te bieden
    @endauth
  </p>
</section>

@if(!empty($nuTeStekken) && count($nuTeStekken['groepen']))
  @include('partials.nu-te-stekken', ['nuTeStekken' => $nuTeStekken, 'headingId' => 'nu-te-stekken-titel-aanbod'])
@endif
@endsection

@push('scripts')
  @vite(['resources/js/zoeken.js'])
@endpush
