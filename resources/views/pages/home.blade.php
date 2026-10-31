@extends('layouts.site')

@section('title', $title)
@section('meta_description', $metaDescription)
@section('canonical', $canonical)
@section('body_class', 'page-home')

@push('head')
  @vite(['resources/css/nu-te-stekken.css'])
@endpush

@php
  $overzicht = $overzicht ?? ['aanbod' => [], 'plekken' => [], 'plekken_totaal' => 0];
  $aanbodTypeLabels = ['uitdelen' => 'Te geef', 'ophalen' => 'Gezocht'];
@endphp

@section('content')
<section class="hero" aria-labelledby="hero-titel">
  <div class="hero-media" aria-hidden="true">
    <picture>
      <source
        type="image/webp"
        srcset="
          {{ asset('assets/images/hero/hero-stekjes-640.webp') }} 640w,
          {{ asset('assets/images/hero/hero-stekjes-1280.webp') }} 1280w,
          {{ asset('assets/images/hero/hero-stekjes-1920.webp') }} 1920w
        "
        sizes="100vw"
      >
      <img
        src="{{ asset('assets/images/hero/hero-stekjes-1280.webp') }}"
        alt=""
        width="1280"
        height="853"
        decoding="async"
        fetchpriority="high"
      >
    </picture>
  </div>
  <div class="hero-scrim" aria-hidden="true"></div>
  <div class="hero-inner">
    <h1 id="hero-titel" class="hero-brand">{{ $heading }}</h1>
    <p class="hero-lead">Stekjes zijn het excuus; het praatje bij de deur is het punt. Haal gratis planten, stekjes en zaden op bij mensen in de buurt of op een vaste deelplek, in heel Nederland en België.</p>
    <form class="hero-zoek" method="get" action="{{ route('aanbod') }}" role="search">
      <label for="hero-q">Wat is er bij jou in de buurt?</label>
      <div class="hero-zoek-veld">
        <input type="text" id="hero-q" name="q" maxlength="120" required autocomplete="postal-code" placeholder="Postcode of plaats, bijvoorbeeld Haarlem">
        <button class="btn" type="submit"><x-icon name="zoeken" :size="18" /> Zoek in de buurt</button>
      </div>
    </form>
    <p class="hero-links">
      <a href="#doe-mee">Zelf iets delen? Doe mee</a>
      <span aria-hidden="true">·</span>
      <a href="#hoe-werkt-het">Hoe werkt het</a>
    </p>
  </div>
</section>
<div class="hero-wave" aria-hidden="true">
  <img src="{{ asset('assets/illustrations/wave-divider.svg') }}" alt="" width="1440" height="80" decoding="async" loading="lazy">
</div>

@if(count($overzicht['aanbod']) || count($overzicht['plekken']))
<section class="section wrap" id="in-de-buurt" aria-labelledby="terecht-titel">
  <h2 id="terecht-titel">Hier kun je nu al terecht</h2>
  <p class="section-intro">Bij leden die iets weggeven, en op vaste plekken zoals zadenbibliotheken, plantenasiels en plantenbiebs.</p>

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
              <span class="aanbod-kaart-foto is-leeg" aria-hidden="true"><x-icon name="plant" :size="36" /></span>
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

  @if(count($overzicht['plekken']))
    <h3 class="home-subkop">Vaste deelplekken</h3>
    <ul class="plek-tellingen">
      @foreach($overzicht['plekken'] as $plek)
        <li><a href="{{ $plek['url'] }}"><strong>{{ $plek['aantal'] }}</strong> {{ $plek['label'] }}</a></li>
      @endforeach
    </ul>
    <p class="home-meer">
      {{ $overzicht['plekken_totaal'] }} plekken in Nederland en België.
      <a href="{{ route('aanbod') }}">Bekijk ze op de kaart</a> of <a href="{{ route('gratis-planten.index') }}">kies je stad</a>.
    </p>
  @endif
</section>
@endif

<section class="section section--mint" id="hoe-werkt-het" aria-labelledby="hoe-titel">
  <div class="wrap">
    <h2 id="hoe-titel">Hoe werkt het</h2>
    <p class="section-intro">Het is <strong>gratis</strong>, je haalt alles <strong>zelf op</strong>, en de enige tegenprestatie is een <strong>praatje</strong>.</p>
    <ol class="steps">
      <li><span><strong>Zoek of bied aan.</strong> Zet gratis planten, stekjes of zaden online met je postcode of plaats, nooit met je straatadres.</span></li>
      <li><span><strong>Stuur een bericht via de site.</strong> Je e-mailadres blijft verborgen.</span></li>
      <li><span><strong>Spreek af en haal op.</strong> Contactgegevens zien jullie alleen als jullie dat allebei willen.</span></li>
    </ol>
    <p class="home-meer">Geen invasieve soorten aanbieden, ook niet als klein stekje. <a href="{{ route('invasieve-soorten') }}">Bekijk de lijst</a>.</p>
  </div>
</section>

@if($nuTeStekken && count($nuTeStekken['groepen']))
  @include('partials.nu-te-stekken', ['nuTeStekken' => $nuTeStekken, 'headingId' => 'nu-te-stekken-titel'])
@endif

@include('partials.ervaringen-home')

<section class="section wrap" aria-labelledby="doe-mee-titel">
  <div class="cta-panel" id="doe-mee">
    <h2 id="doe-mee-titel">Doe mee</h2>
    <p>Met één account kun je iets aanbieden én iets ophalen. Alles blijft gratis, er wordt niets verkocht.</p>
    <div class="cta-choices">
      <a class="cta-choice" href="{{ route('account.registreren') }}">
        <strong>Account maken <span class="cta-pijl" aria-hidden="true">→</span></strong>
        <span>Bevestig je e-mailadres. Daarna kun je planten, stekjes of zaden aanbieden en berichten sturen.</span>
      </a>
      <a class="cta-choice" href="{{ route('aanbod') }}">
        <strong>Bekijk het aanbod <span class="cta-pijl" aria-hidden="true">→</span></strong>
        <span>Zoek in de buurt op de kaart. Kijken kan ook zonder account.</span>
      </a>
      <a class="cta-choice" href="{{ route('help-mee') }}">
        <strong>Ik wil op een andere manier helpen <span class="cta-pijl" aria-hidden="true">→</span></strong>
        <span>Deel het op social media, tip een volkstuinpark of wijs me op een initiatief. Zo word je verbinder.</span>
      </a>
    </div>
  </div>
</section>
@endsection
