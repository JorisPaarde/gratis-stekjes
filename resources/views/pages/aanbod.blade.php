@extends('layouts.site')

@section('title', $title)
@section('meta_description', $metaDescription)
@section('canonical', $canonical)
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
      Filter op <strong>{{ $plant }}</strong> in aanbodteksten.
      Vul een postcode of plaats in om dichtbij te zoeken. Exacte adressen staan niet op de site.
    @else
      Zoek op postcode of plaats in Nederland of België. Je ziet benaderde plekken op de kaart en een lijst gesorteerd op afstand. Exacte adressen staan niet op de site.
    @endif
  </p>

  <form class="form" id="zoek-form" method="get" action="{{ route('aanbod') }}" style="max-width:100%;margin-bottom:1rem">
    <div style="display:grid;gap:.75rem;grid-template-columns:1fr;align-items:end">
      <label for="zoek-q">Postcode of plaats
        <input type="text" name="q" id="zoek-q" maxlength="120" placeholder="Bijv. 2011 of Haarlem" @if(empty($plant)) required @endif>
      </label>
      <label for="zoek-plant">Plant / stekje
        <input type="text" name="plant" id="zoek-plant" maxlength="80" value="{{ $plant ?? '' }}" placeholder="Bijv. hortensia">
      </label>
      <label for="zoek-type">Aanbodtype
        <select name="type" id="zoek-type">
          <option value="">Alles</option>
          <option value="uitdelen">Uitdelen</option>
          <option value="ophalen">Ophalen / zoeken</option>
        </select>
      </label>
      <button class="btn" type="submit">Zoeken</button>
    </div>
  </form>

  @if(!empty($plant) && isset($plantItems))
    <section class="stektips-plant-aanbod" aria-labelledby="plant-aanbod-titel">
      <h2 id="plant-aanbod-titel" class="stektips-related-title">Treffers voor “{{ $plant }}” (zonder plaats)</h2>
      @if(count($plantItems) === 0)
        <p class="empty-state" role="status">Nog geen openbaar aanbod met “{{ $plant }}”. <a href="{{ route('account.registreren') }}">Doe mee</a> om zelf te delen, of zoek op plaats hierboven.</p>
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

  <fieldset class="marker-filters" id="marker-filters">
    <legend>Toon op kaart en in lijst</legend>
    <label class="check"><input type="checkbox" data-aanbod checked> Aanbod</label>
    <label class="check"><input type="checkbox" data-partner-type="partner" checked> Initiatief</label>
    <label class="check"><input type="checkbox" data-partner-type="voedselbos" checked> Voedselbos</label>
    <label class="check"><input type="checkbox" data-partner-type="plantenasiel" checked> Plantenasiel</label>
    <label class="check"><input type="checkbox" data-partner-type="plantenbieb" checked> Plantenbieb</label>
    <label class="check"><input type="checkbox" data-partner-type="ruilkast" checked> Ruilkast</label>
    <label class="check"><input type="checkbox" data-partner-type="stekjesruilpunt" checked> Stekjesruilpunt</label>
    <label class="check"><input type="checkbox" data-partner-type="zaadbibliotheek" checked> Zaadbibliotheek</label>
  </fieldset>

  <div class="search-layout">
    <div id="kaart" role="img" aria-label="Kaart met benaderde ophaalplekken"></div>
    <div>
      <ul class="zoek-lijst" id="zoek-lijst"></ul>
      <p class="muted empty-state" id="zoek-status" role="status">Vul een postcode of plaats in om aanbod te zoeken. Deelplekken (plantenasiel, plantenbieb, …) staan al op de kaart — of <a href="{{ route('account.registreren') }}">maak een account</a> om zelf iets te plaatsen.</p>
    </div>
  </div>

  <p class="muted" style="margin-top:1rem">Kaarttegels: © OpenStreetMap-bijdragers. Locaties via PDOK Locatieserver (benaderd).
    @auth
      · <a href="{{ route('account.aanbod') }}">Zelf aanbod plaatsen</a>
    @else
      · <a href="{{ route('account.registreren') }}">Account maken</a> om aanbod te plaatsen
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
