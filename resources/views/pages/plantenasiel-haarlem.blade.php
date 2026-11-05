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
  $praktisch = $initiatief?->praktischAlineas() ?? [];
  $ruilen = $initiatief?->ruilenAlineas() ?? [];
@endphp
<section class="section wrap prose plantenasiel-page">
  <h1 class="page-title">{{ $org['naam'] }}</h1>
  <p class="muted">{{ $org['plaats'] }} · Plantenasiel</p>

  <h2>Een tweede thuis voor kamerplanten</h2>
  @foreach($intro as $alinea)
    <p>{{ $alinea }}</p>
  @endforeach

  <h2>Langskomen in Haarlem</h2>
  @foreach($praktisch as $alinea)
    <p>{!! nl2br(e($alinea)) !!}</p>
  @endforeach

  <h2>Adopteren, ruilen en materialen</h2>
  @foreach($ruilen as $alinea)
    <p>{{ $alinea }}</p>
  @endforeach

  <p>
    <a href="{{ $org['website'] }}" rel="noopener noreferrer" target="_blank">Website</a>
    @foreach($extraLinks as $link)
      · <a href="{{ $link['url'] }}" @if($link['external']) rel="noopener noreferrer" target="_blank" @endif>{{ $link['label'] }}</a>
    @endforeach
  </p>

  @if(count($deelplekken) > 0)
    <h2>Op de deelplekkenkaart</h2>
    <p><a href="{{ route('deelplekken', ['plaats' => $org['plaats']]) }}">Bekijk de deelplek op de kaart</a></p>
  @endif

  <h2>Planten gratis weggeven?</h2>
  <p>Zet je planten, stekjes of zaden op Gratis planten en deel ze met iemand in de buurt.</p>
  <div class="struikroven-ctas" role="group" aria-label="Planten delen via Gratis planten">
    <a class="btn struikroven-cta" href="{{ route('aanbieden') }}">Planten aanbieden</a>
    <a class="btn btn-secondary struikroven-cta" href="{{ route('aanbod') }}">Planten zoeken in de buurt</a>
  </div>
  <p class="muted">Zoeken kan zonder account. Aanbieden vraagt een gratis account.</p>

  <p class="muted">
    <a href="{{ route('organisaties.index') }}">Alle organisaties</a> ·
    <a href="{{ route('voor-organisaties.aanmelden') }}">Zelf een plek aanmelden</a>
  </p>
</section>
@endsection
