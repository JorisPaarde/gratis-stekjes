@extends('layouts.site')

@section('title', $title)
@section('meta_description', $metaDescription)
@section('canonical', $canonical)
@section('body_class', 'page-home')

@push('head')
  @vite(['resources/css/home.css', 'resources/css/nu-te-stekken.css'])
@endpush

@php
  $hero = $hero ?? [];
  $overzicht = $overzicht ?? ['aanbod' => [], 'plekken' => [], 'plekken_totaal' => 0];
  $aanbodTypeLabels = ['uitdelen' => 'Te geef', 'ophalen' => 'Gezocht'];
@endphp

@section('content')
{{-- 1. Hero: foto, kop, zoeken als hoofdactie, account als tweede actie --}}
<section class="home-hero" aria-labelledby="hero-titel">
  <div class="home-hero-media" aria-hidden="true">
    <x-home-picture id="hero" class="home-hero-pic" :eager="true" :show-credit="false" sizes="100vw" alt="" />
  </div>
  <div class="home-hero-scrim" aria-hidden="true"></div>
  <div class="home-hero-inner">
    <h1 id="hero-titel" class="home-hero-heading">{{ $heading }}</h1>
    @if(!empty($hero['tagline']))
      <p class="home-hero-tagline">{{ $hero['tagline'] }}</p>
    @endif
    @if(!empty($hero['lead']))
      <p class="home-hero-lead">{{ $hero['lead'] }}</p>
    @endif
    <form class="hero-zoek" method="get" action="{{ route('aanbod') }}" role="search">
      <label for="hero-q">Wat is er bij jou in de buurt?</label>
      <div class="hero-zoek-veld">
        <input type="text" id="hero-q" name="q" maxlength="120" required autocomplete="postal-code" placeholder="Postcode of plaats, bijvoorbeeld Haarlem">
        <button class="btn" type="submit"><x-icon name="zoeken" :size="18" /> Zoek</button>
      </div>
    </form>
    <p class="home-hero-account">
      Zelf iets weggeven?
      <a class="btn btn-on-dark" href="{{ route('account.registreren') }}"><x-icon name="plus" :size="18" /> Maak gratis een account</a>
    </p>
  </div>
</section>

{{-- 2. Drie korte stappen, met een foto per stap --}}
<section class="section wrap home-section" id="hoe-werkt-het" aria-labelledby="hoe-titel">
  <h2 id="hoe-titel">Zo werkt het</h2>
  <p class="section-intro">Alles is gratis en je haalt het zelf op. Er wordt niets verkocht.</p>
  <ol class="home-stappen">
    <li class="home-stap">
      <x-home-picture id="zaden" :show-credit="false" sizes="(max-width: 800px) 100vw, 33vw" />
      <h3><span class="home-stap-num" aria-hidden="true">1</span> Zoek of bied aan</h3>
      <p>Kijk wat er in de buurt is, of zet zelf planten, stekjes of zaden online. Met je postcode of plaats, nooit met je straatadres.</p>
    </li>
    <li class="home-stap">
      <x-home-picture id="stappen" :show-credit="false" sizes="(max-width: 800px) 100vw, 33vw" />
      <h3><span class="home-stap-num" aria-hidden="true">2</span> Stuur een bericht</h3>
      <p>Via de site, zonder dat je e-mailadres zichtbaar wordt. Contactgegevens zien jullie alleen als jullie dat allebei willen.</p>
    </li>
    <li class="home-stap">
      <x-home-picture id="delen" :show-credit="false" sizes="(max-width: 800px) 100vw, 33vw" />
      <h3><span class="home-stap-num" aria-hidden="true">3</span> Spreek af en haal op</h3>
      <p>Kies samen een moment en haal het op. Een praatje bij de deur hoort er vaak bij.</p>
    </li>
  </ol>
  <p class="home-meer">Geen invasieve soorten aanbieden, ook niet als klein stekje. <a href="{{ route('invasieve-soorten') }}">Bekijk de lijst</a>.</p>
</section>

{{-- 3. Echt aanbod en vaste deelplekken (alleen wat er is) --}}
@if(count($overzicht['aanbod']) || count($overzicht['plekken']))
<section class="section section--mint home-section" id="in-de-buurt" aria-labelledby="terecht-titel">
  <div class="wrap">
    <div class="home-split">
      <div class="home-split-copy">
        <h2 id="terecht-titel">Hier kun je nu al terecht</h2>
        <p class="section-intro">Bij mensen die iets weggeven, en op vaste plekken zoals zadenbibliotheken, plantenasiels en plantenbiebs.</p>
        @if(count($overzicht['plekken']))
          <ul class="plek-tellingen">
            @foreach($overzicht['plekken'] as $plek)
              <li><a href="{{ $plek['url'] }}"><strong>{{ $plek['aantal'] }}</strong> {{ $plek['label'] }}</a></li>
            @endforeach
          </ul>
          <p class="home-meer">{{ $overzicht['plekken_totaal'] }} plekken in Nederland en België. Ken je er nog een? <a href="{{ route('voor-organisaties') }}">Meld hem aan</a>.</p>
        @endif
        <p class="home-actions">
          <a class="btn" href="{{ route('aanbod') }}"><x-icon name="kaart" :size="18" /> Open de kaart</a>
          <a class="btn btn-secondary" href="{{ route('gratis-planten.index') }}"><x-icon name="locatie" :size="18" /> Kies je stad</a>
        </p>
      </div>
      <a class="home-split-media home-kaart-preview" href="{{ route('aanbod') }}" aria-label="Naar de kaart met aanbod en deelplekken">
        <x-home-picture id="kaart" :show-credit="false" sizes="(max-width: 800px) 100vw, 45vw" />
      </a>
    </div>

    @if(count($overzicht['aanbod']))
      <h3 class="home-subkop">Net aangeboden</h3>
      <ul class="aanbod-kaarten">
        @foreach($overzicht['aanbod'] as $item)
          @php
            $foto = $item['fotos'][0] ?? null;
            $plaats = trim((string) ($item['locatie_label'] ?? ''));
          @endphp
          <li>
            <a class="aanbod-kaart" href="{{ route('aanbod', $plaats !== '' ? ['q' => $plaats] : []) }}">
              @if($foto && !empty($foto['thumb_url']))
                <img class="aanbod-kaart-foto" src="{{ $foto['thumb_url'] }}" alt="{{ $foto['alt'] ?? '' }}" width="320" height="220" loading="lazy" decoding="async">
              @else
                <span class="aanbod-kaart-foto is-leeg" aria-hidden="true"><x-icon name="plant" :size="32" /></span>
              @endif
              <span class="aanbod-kaart-body">
                <span class="badge">{{ $aanbodTypeLabels[$item['type'] ?? ''] ?? ($item['type'] ?? '') }}</span>
                <span class="aanbod-kaart-tekst">{{ \Illuminate\Support\Str::limit((string) ($item['tekst'] ?? ''), 90) }}</span>
                <span class="aanbod-kaart-meta">{{ $item['voornaam'] ?? '' }}@if($plaats !== '') · {{ $plaats }}@endif</span>
              </span>
            </a>
          </li>
        @endforeach
      </ul>
      <p class="home-meer"><a href="{{ route('aanbod') }}">Bekijk al het aanbod op de kaart</a></p>
    @endif
  </div>
</section>
@endif

{{-- 4. Waarom: het verhaal achter de site (tekst van /over) --}}
<section class="section wrap home-section" id="waarom" aria-labelledby="waarom-titel">
  <div class="home-split home-split--rev">
    <div class="home-split-copy">
      <h2 id="waarom-titel">Waarom Gratis planten</h2>
      <p class="home-verhaal">Te veel goede planten verdwijnen op de compost of in de kliko, terwijl iemand in de buurt er blij mee zou zijn. En vaak koop je iets nieuws, terwijl een stekje van een buur net zo goed was geweest.</p>
      <p class="home-verhaal">Daarom deze site: meer groen, en meer contact tussen mensen die het delen. Zonder reclame en zonder dat er iets verkocht wordt.</p>
      <p><a href="{{ route('over') }}">Lees meer over het idee</a></p>
    </div>
    <div class="home-split-media">
      <x-home-picture id="stekjes" :show-credit="false" sizes="(max-width: 800px) 100vw, 45vw" />
    </div>
  </div>
</section>

{{-- 5. Seizoenstips --}}
@if($nuTeStekken)
  @include('partials.nu-te-stekken', ['nuTeStekken' => $nuTeStekken, 'headingId' => 'nu-te-stekken-titel'])
@endif

@include('partials.ervaringen-home')

{{-- 6. Doe mee --}}
<section class="home-signup" id="doe-mee" aria-labelledby="doe-mee-titel">
  <div class="wrap home-signup-inner">
    <div class="home-signup-copy">
      <h2 id="doe-mee-titel">Doe mee, het is gratis</h2>
      <p>Met één account kun je iets aanbieden én iets ophalen. Liever op een andere manier helpen? Tip een volkstuinpark of een initiatief bij jou in de buurt.</p>
      <p class="home-actions">
        <a class="btn" href="{{ route('account.registreren') }}"><x-icon name="plus" :size="18" /> Maak gratis een account</a>
        <a class="btn btn-on-dark" href="{{ route('help-mee') }}">Op een andere manier helpen</a>
      </p>
    </div>
    <div class="home-signup-media" aria-hidden="true">
      <x-home-picture id="aanmelden" :show-credit="false" sizes="(max-width: 800px) 100vw, 40vw" />
    </div>
  </div>
</section>

<section class="wrap home-credits" aria-label="Fotoverantwoording">
  @php
    $fotoCredits = collect(json_decode((string) file_get_contents(resource_path('data/foto-credits.json')), true)['fotos'] ?? [])
      ->filter(fn ($f) => ! empty($f['attribution_required']));
  @endphp
  <p class="muted home-credits-text">
    Foto’s:
    @foreach($fotoCredits as $f)
      <a href="{{ $f['bron_url'] }}" rel="noopener noreferrer">{{ $f['maker'] }}</a>
      (<a href="{{ $f['licentie_url'] }}" rel="noopener noreferrer license">{{ $f['licentie'] }}</a>)@if(! $loop->last),@endif
    @endforeach
    via Wikimedia Commons; overige foto’s via Pexels.
    Kaart © <a href="https://www.openstreetmap.org/copyright" rel="noopener noreferrer">OpenStreetMap</a>-bijdragers.
  </p>
</section>
@endsection
