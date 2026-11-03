@extends('layouts.site')

@section('title', $title)
@section('meta_description', $metaDescription)
@section('canonical', $canonical)
@section('robots', 'noindex, follow')

@push('head')
  @vite(['resources/css/poster.css'])
@endpush

@php
  $foto = $poster['foto'];
  $fotoPad = '/assets/images/posters/'.$voor;
@endphp

@section('content')
<section class="section wrap">
  <div class="poster-tools">
    <button type="button" class="btn" data-print>Print of bewaar als pdf</button>
    <a href="{{ route('help-mee') }}#poster">Terug naar Help mee</a>
    <span class="muted">Andere poster:
      @foreach(\App\Http\Controllers\MeedoenController::POSTERS as $key => $p)
        @if($key !== $voor)<a href="{{ route('poster', ['voor' => $key]) }}">{{ $p['titel'] }}</a>@if(! $loop->last) · @endif @endif
      @endforeach
    </span>
  </div>
  <p class="poster-tip muted">Tip: kies in het printvenster A4 en staand. Bij marges kies je «Geen».</p>

  <article class="poster poster--foto poster--{{ $voor }}">
    <div class="poster-foto">
      <picture>
        <source type="image/webp" srcset="{{ $fotoPad }}-1600.webp">
        <img src="{{ $fotoPad }}-1600.jpg" alt="{{ $foto['alt'] }}" width="1600" height="1000" style="object-position: {{ $foto['positie'] }}" fetchpriority="high">
      </picture>
      <p class="poster-merk"><img src="/assets/badges/eerste_stekje.svg" alt="" width="40" height="40"> {{ config('site.name') }}</p>
    </div>

    <div class="poster-body">
      <h1>{{ $poster['kop'] }}</h1>
      <p class="poster-sub">{{ $poster['sub'] }}</p>

      <div class="poster-midden">
        <div class="poster-qr-vak">
          <div class="poster-qr" data-qr-url="{{ $url }}"></div>
          <p class="poster-scan">Scan mij</p>
        </div>
        <ol class="poster-stappen">
          <li><b>1</b> <span>Scan de code en kijk wat er bij jou in de buurt te halen is.</span></li>
          <li><b>2</b> <span>Iets over? Zet het er gratis op, het kost je een minuut.</span></li>
          <li><b>3</b> <span>Je haalt het zelf op. Een praatje is de enige prijs.</span></li>
        </ol>
      </div>
    </div>

    <footer class="poster-voet">
      <p class="poster-url">{{ $kortUrl }}</p>
      <p class="poster-voet-tekst">Gratis, zonder reclame. Voor iedereen in Nederland en België.</p>
      <p class="poster-credit">Foto: <a href="{{ $foto['bron_url'] }}" rel="noopener" target="_blank">{{ $foto['maker'] }} / Unsplash</a></p>
    </footer>
  </article>
</section>
@endsection

@push('scripts')
  @vite(['resources/js/poster.js'])
@endpush
