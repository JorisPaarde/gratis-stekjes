@extends('layouts.site')

@section('title', $title)
@section('meta_description', $metaDescription)
@section('canonical', $canonical)

@push('head')
  @vite(['resources/css/struikroven.css'])
@endpush

@section('content')
@php
  $intro = $initiatief?->introAlineas() ?? [];
  $introTitel = trim((string) ($initiatief?->intro_titel ?? ''));
@endphp
<section class="section wrap prose plantenasiel-page">
  <h1 class="page-title">{{ $org['naam'] }}</h1>
  <p class="muted">{{ $org['plaats'] }} · Initiatief</p>

  <picture class="struikroven-logo-wrap">
    <source type="image/webp" srcset="/assets/images/initiatieven/logo-steenbreek.webp">
    <img
      class="struikroven-logo"
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
