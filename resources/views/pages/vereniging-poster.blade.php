@extends('layouts.site')

@section('title', $title)
@section('meta_description', $metaDescription)
@section('canonical', $canonical)

@section('robots', 'noindex, follow')

@push('head')
  @vite(['resources/css/poster.css'])
@endpush

@section('content')
<section class="section wrap">
  <div class="poster-tools">
    <button type="button" class="btn" data-print>Print of bewaar als pdf</button>
    <a href="{{ $url }}">Terug naar de pagina</a>
    <span class="muted">Hang hem op in het clubhuis, bij de tuin of op het prikbord.</span>
  </div>

  <article class="poster">
    <p class="poster-merk"><img src="/assets/badges/eerste_stekje.svg" alt="" width="56" height="56"> Gratis planten</p>
    <div>
      <h1>Plantje over?</h1>
      <p class="poster-sub">Deel het met andere leden van <strong>{{ $naam }}</strong>.</p>
      <div class="poster-qr" data-qr-url="{{ $url }}"></div>
      <ol class="poster-stappen">
        <li><b>1</b> Scan de code en kijk wat andere leden delen.</li>
        <li><b>2</b> Maak een gratis account en koppel {{ $naam }}.</li>
        <li><b>3</b> Zet je stekjes, planten of zaden online. Zelf ophalen, met een praatje erbij.</li>
      </ol>
    </div>
    <div>
      <p class="poster-url">{{ preg_replace('#^https?://#', '', $url) }}</p>
      <p class="poster-voet">Gratis, zonder reclame. Ook voor wie geen lid is.</p>
    </div>
  </article>
</section>
@endsection

@push('scripts')
  @vite(['resources/js/poster.js'])
@endpush
