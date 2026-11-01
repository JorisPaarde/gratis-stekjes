@extends('layouts.site')

@section('title', $title)
@section('meta_description', $metaDescription)
@section('canonical', $canonical)

@section('content')
<section class="section wrap prose">
  <h1 class="page-title">Organisaties</h1>
  <p class="section-intro">Korte pagina’s over organisaties en initiatieven rond gratis planten delen. Het zijn geen officiële partnerschappen. Ik gebruik alleen informatie die iedereen kan controleren, met een link naar hun eigen site.</p>
  <ul>
    @foreach($organisaties as $org)
      <li>
        <a href="{{ route('organisaties.show', ['slug' => $org['slug']]) }}">{{ $org['naam'] }}</a>
        <span class="muted"> · {{ $org['plaats'] }}</span>
      </li>
    @endforeach
  </ul>
  @if(count($verenigingen) > 0)
    <h2>Verenigingen van leden</h2>
    <p>Volkstuinen, tuinverenigingen en buurttuinen waar leden van Gratis planten bij horen.</p>
    <ul>
      @foreach($verenigingen as $v)
        <li>
          <a href="{{ $v->url() }}">{{ $v->naam }}</a>
          <span class="muted"> · {{ $v->plaats }} · {{ $v->leden_count }} {{ $v->leden_count === 1 ? 'lid' : 'leden' }}</span>
        </li>
      @endforeach
    </ul>
  @endif
  <p>Ben je lid van een volkstuin of tuinvereniging? <a href="{{ route('account.verenigingen') }}">Koppel je account</a>, dan krijgt je vereniging een eigen pagina met het aanbod van leden.</p>

  <p class="muted">
    <a href="{{ route('voor-organisaties') }}">Voor organisaties</a> ·
    <a href="{{ route('voor-organisaties.aanmelden') }}">Plek aanmelden</a> ·
    <a href="{{ route('deelplekken') }}">Deelplekken</a>
  </p>
</section>
@endsection
