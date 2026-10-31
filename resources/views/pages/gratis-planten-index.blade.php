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
  <p class="section-intro">Kies een stad en zie wat buren gratis delen — stekjes en planten ophalen met een praatje als enige prijs. Hoofdterm: <strong>gratis planten</strong>, lokaal via stadspagina’s.</p>

  @foreach($byProvincie as $provincie => $steden)
    <section class="gp-provincie" aria-labelledby="prov-{{ \Illuminate\Support\Str::slug($provincie) }}">
      <h2 id="prov-{{ \Illuminate\Support\Str::slug($provincie) }}">{{ $provincie }}</h2>
      <ul class="gp-stad-lijst">
        @foreach($steden as $s)
          <li>
            <a href="{{ route('gratis-planten.show', ['stad' => $s['slug']]) }}">Gratis planten in {{ $s['naam'] }}</a>
          </li>
        @endforeach
      </ul>
    </section>
  @endforeach

  <p class="muted gp-index-cta">Liever landelijk zoeken? Ga naar het <a href="{{ route('aanbod') }}">aanbod op de kaart</a>
    @auth
      of <a href="{{ route('account.aanbod') }}">plaats zelf aanbod</a>.
    @else
      of <a href="{{ route('account.registreren') }}">maak een account</a> om zelf iets aan te bieden.
    @endauth
  </p>
</section>
@endsection
