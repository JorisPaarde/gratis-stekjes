@extends('layouts.site')

@section('title', $title)
@section('meta_description', $metaDescription)
@section('canonical', $canonical)

@push('head')
  @vite(['resources/css/gratis-planten.css', 'resources/css/aanbod.css'])
@endpush

@section('content')
<section class="section wrap prose vereniging-page">
  <h1 class="page-title">{{ $vereniging->naam }}</h1>
  <p class="muted">{{ $vereniging->plaats }} · {{ $vereniging->typeLabel() }}</p>
  <p class="section-intro">Leden van {{ $vereniging->naam }} delen hier planten, stekjes en zaden. Je haalt ze zelf op; een praatje is de enige prijs. Iedereen mag kijken, ook als je geen lid bent.</p>

  @include('partials.vereniging-leden', ['blok' => $blok, 'naam' => $vereniging->naam, 'slug' => $vereniging->slug])

  <p class="muted">
    Deze vereniging is door een lid toegevoegd. Klopt er iets niet? <a href="{{ route('contact') }}">Laat het me weten</a>.
    · <a href="{{ route('organisaties.index') }}">Alle organisaties en verenigingen</a>
  </p>
</section>
@endsection
