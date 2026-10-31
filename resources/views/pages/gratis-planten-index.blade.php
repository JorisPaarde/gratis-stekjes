@extends('layouts.site')

@section('title', $title)
@section('meta_description', $metaDescription)
@section('canonical', $canonical)
@section('body_class', 'page-gratis-planten')

@push('head')
  @vite(['resources/css/gratis-planten.css'])
@endpush

@section('content')
<section class="section wrap gratis-planten-index">
  <h1 class="page-title">{{ $title }}</h1>
  <p class="section-intro">Kies je stad en zie wat buren gratis delen. Je haalt planten, stekjes en zaden op, met een praatje als enige prijs. In België vind je ook een <strong>zadenbib</strong>.</p>

  @foreach($byLand as $land => $byProvincie)
    <section class="gp-land" aria-labelledby="land-{{ \Illuminate\Support\Str::slug($land) }}">
      <h2 id="land-{{ \Illuminate\Support\Str::slug($land) }}">{{ $land }}</h2>
      @foreach($byProvincie as $provincie => $steden)
        <section class="gp-provincie" aria-labelledby="prov-{{ \Illuminate\Support\Str::slug($land.'-'.$provincie) }}">
          <h3 id="prov-{{ \Illuminate\Support\Str::slug($land.'-'.$provincie) }}">{{ $provincie }}</h3>
          <ul class="gp-stad-lijst">
            @foreach($steden as $s)
              <li>
                <a href="{{ route('gratis-planten.show', ['stad' => $s['slug']]) }}">Gratis planten in {{ $s['naam'] }}</a>
              </li>
            @endforeach
          </ul>
        </section>
      @endforeach
    </section>
  @endforeach

  <p class="muted gp-index-cta">Liever in heel het land zoeken? Ga naar het <a href="{{ route('aanbod') }}">aanbod op de kaart</a>
    @auth
      of <a href="{{ route('account.aanbod') }}">bied zelf iets aan</a>.
    @else
      of <a href="{{ route('account.registreren') }}">maak een account</a> om zelf iets aan te bieden.
    @endauth
  </p>
</section>
@endsection
