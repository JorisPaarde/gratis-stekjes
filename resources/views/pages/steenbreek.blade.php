@extends('layouts.site')

@section('title', $title)
@section('meta_description', $metaDescription)
@section('canonical', $canonical)

@push('head')
  @vite(array_merge(
    ['resources/css/struikroven.css'],
    ($initiatief?->secties ?? []) !== [] ? ['resources/css/stektips.css'] : []
  ))
@endpush

@section('content')
@php
  $intro = $initiatief?->introAlineas() ?? [];
  $introTitel = trim((string) ($initiatief?->intro_titel ?? ''));
  $siteUrl = trim((string) ($initiatief?->site_url ?? $org['website'] ?? ''));
  $siteLinkTekst = trim((string) ($initiatief?->site_link_tekst ?? ''));
  $secties = $initiatief?->secties ?? [];
@endphp
<section class="section wrap struikroven-page plantenasiel-page">
  <h1 class="page-title">{{ $org['naam'] }}</h1>
  <p class="muted">{{ $org['plaats'] }} · Initiatief</p>

  <div class="struikroven-intro">
    <div class="struikroven-intro-tekst">
      <picture class="struikroven-logo-wrap">
        <source type="image/webp" srcset="/assets/images/initiatieven/logo-steenbreek.webp">
        <img
          class="struikroven-logo struikroven-logo--breed"
          src="/assets/images/initiatieven/logo-steenbreek.png"
          width="220"
          height="62"
          alt="Steenbreek"
          decoding="async"
        >
      </picture>

      @if($introTitel !== '')
        <h2>{{ $introTitel }}</h2>
      @endif
      @foreach($intro as $alinea)
        <p>{{ $alinea }}</p>
      @endforeach

      @if($siteUrl !== '')
        <p class="struikroven-links muted">
          <a href="{{ $siteUrl }}" rel="noopener noreferrer" target="_blank">{{ $siteLinkTekst !== '' ? $siteLinkTekst : 'Meer info' }}</a>
        </p>
      @endif
    </div>

    <figure class="struikroven-plantenbieb">
      <div class="struikroven-plantenbieb-media">
        <picture>
          <source
            type="image/webp"
            srcset="/assets/images/initiatieven/steenbreek-geveltuin-480.webp 480w, /assets/images/initiatieven/steenbreek-geveltuin-960.webp 960w"
            sizes="(min-width: 1024px) 38vw, min(100vw - 2rem, 360px)"
          >
          <img
            src="/assets/images/initiatieven/steenbreek-geveltuin-480.jpg"
            srcset="/assets/images/initiatieven/steenbreek-geveltuin-480.jpg 480w, /assets/images/initiatieven/steenbreek-geveltuin-960.jpg 960w"
            sizes="(min-width: 1024px) 38vw, min(100vw - 2rem, 360px)"
            width="480"
            height="360"
            loading="lazy"
            decoding="async"
            alt="Bloeiende geveltuin met klimrozen en stokrozen tegen een bakstenen gevel"
          >
        </picture>
      </div>
      <figcaption>
        Foto:
        <a href="https://steenbreek.nl/word-steenbreker/inwoners/" rel="noopener noreferrer" target="_blank">Steenbreek</a>
      </figcaption>
    </figure>
  </div>

  @if($secties !== [])
    <div class="stektips-page stektips-artikel-body org-secties">
      @foreach($secties as $block)
        @include('partials.kennisbank-blok', ['block' => $block])
      @endforeach
    </div>
  @endif

  <p>
    <a href="{{ $org['website'] }}" rel="noopener noreferrer" target="_blank">Website</a>
    ·
    <a href="{{ $org['bron_url'] }}" rel="noopener noreferrer" target="_blank">Bron</a>
    @foreach($extraLinks as $link)
      ·
      @if(!empty($link['external']))
        <a href="{{ $link['url'] }}" rel="noopener noreferrer" target="_blank">{{ $link['label'] }}</a>
      @else
        <a href="{{ $link['url'] }}">{{ $link['label'] }}</a>
      @endif
    @endforeach
  </p>

  <p class="muted">Niet te bezoeken via mijn <a href="{{ route('deelplekken') }}">kaart</a>; zie hun eigen site.</p>

  <h2>Planten gratis weggeven?</h2>
  <p>Zet je planten, stekjes of zaden op Gratis planten en deel ze met iemand in de buurt.</p>
  <div class="struikroven-ctas" role="group" aria-label="Planten delen via Gratis planten">
    <a class="btn struikroven-cta" href="{{ route('aanbieden') }}">Planten aanbieden</a>
    <a class="btn btn-secondary struikroven-cta" href="{{ route('aanbod') }}">Planten zoeken in de buurt</a>
  </div>
  <p class="muted">Zoeken kan zonder account. Aanbieden vraagt een gratis account.</p>

  <p class="muted">
    <a href="{{ route('partners') }}#organisaties">Alle organisaties</a> ·
    <a href="{{ route('voor-organisaties.aanmelden') }}">Zelf een plek aanmelden</a>
  </p>
</section>
@endsection
