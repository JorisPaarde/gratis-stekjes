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
      bericht_starten: @json(url('/account/bericht-starten')),
      api_nu_te_stekken: @json(route('api.nu-te-stekken')),
      bericht_binnenkort: false
    };
  </script>
  @vite(['resources/css/aanbod.css', 'resources/css/nu-te-stekken.css'])
@endpush

@section('content')
<section class="section wrap">
  <h1>Aanbod</h1>
  <p class="section-intro">Zoek op postcode (4 cijfers) of plaats in Nederland. Je ziet benaderde plekken op de kaart en een lijst gesorteerd op afstand. Exacte adressen staan niet op de site.</p>

  <form class="form" id="zoek-form" style="max-width:100%;margin-bottom:1rem">
    <div style="display:grid;gap:.75rem;grid-template-columns:1fr;align-items:end">
      <label>Postcode of plaats
        <input type="text" name="q" id="zoek-q" maxlength="120" placeholder="Bijv. 2011 of Haarlem" required>
      </label>
      <label>Aanbodtype
        <select name="type" id="zoek-type">
          <option value="">Alles</option>
          <option value="uitdelen">Uitdelen</option>
          <option value="ophalen">Ophalen / zoeken</option>
        </select>
      </label>
      <button class="btn" type="submit">Zoeken</button>
    </div>
  </form>

  <fieldset class="marker-filters" id="marker-filters">
    <legend>Toon op kaart en in lijst</legend>
    <label class="check"><input type="checkbox" data-aanbod checked> Aanbod</label>
    <label class="check"><input type="checkbox" data-partner-type="partner" checked> Partner</label>
    <label class="check"><input type="checkbox" data-partner-type="voedselbos" checked> Voedselbos</label>
    <label class="check"><input type="checkbox" data-partner-type="plantenasiel" checked> Plantenasiel</label>
    <label class="check"><input type="checkbox" data-partner-type="plantenbieb" checked> Plantenbieb</label>
    <label class="check"><input type="checkbox" data-partner-type="ruilkast" checked> Ruilkast</label>
  </fieldset>

  <div class="search-layout">
    <div id="kaart" role="img" aria-label="Kaart met benaderde ophaalplekken"></div>
    <div>
      <ul class="zoek-lijst" id="zoek-lijst"></ul>
      <p class="muted" id="zoek-status">Vul een postcode of plaats in om aanbod te zoeken. Partnermarkers volgen in een volgende release.</p>
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
