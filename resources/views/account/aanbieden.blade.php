@extends('layouts.site')

@section('title', $title)
@section('meta_description', $metaDescription)
@section('canonical', $canonical)

@push('head')
  <meta name="robots" content="noindex">
  @vite(['resources/css/plantsoort-autocomplete.css', 'resources/css/aanbod-fotos.css'])
@endpush

@section('content')
<section class="section wrap aanbieden">
  <h1 class="page-title">Bied iets aan</h1>
  <p class="section-intro">Een plant, stekje of zakje zaad dat je over hebt? Typ wat het is of maak een foto. Het staat {{ (int) config('stekjes.aanbod_ttl_days', 60) }} dagen in zoeken en op de kaart.</p>
  <p class="muted"><x-icon name="waarschuwing" :size="16" /> Geen invasieve soorten (EU-Unielijst). <a href="{{ $invasieveUitlegUrl }}">Meer uitleg</a>.</p>

  @if($errors->any())
    <p class="error" role="alert">{{ $errors->first() }}</p>
  @endif

  @include('account.partials.aanbod-form')

  @if($aantalAanbod > 0)
    <p style="margin-top:1.25rem"><a href="{{ route('account.aanbod') }}">Bekijk en beheer je aanbod ({{ $aantalAanbod }}) →</a></p>
  @endif
</section>
@endsection

@include('account.partials.aanbod-form-scripts')
