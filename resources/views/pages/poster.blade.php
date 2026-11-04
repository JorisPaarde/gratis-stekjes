@extends('layouts.site')

@section('title', $title)
@section('meta_description', $metaDescription)
@section('canonical', $canonical)
@section('robots', 'noindex, follow')

@php
  $foto = $poster['foto'];
  $fotoPad = '/assets/images/posters/'.$voor;
  $isA5 = ($formaat ?? 'a4') === 'a5';
  $stappenLijst = $stappen ?? [
    'Scan de code en kijk wat er bij jou in de buurt te halen is.',
    'Iets over? Zet het er gratis op, het kost je een minuut.',
    'Je haalt het zelf op. Een praatje is de enige prijs.',
  ];
@endphp

@section('body_class', $isA5 ? 'poster-print-a5' : '')

@push('head')
  @vite(['resources/css/poster.css'])
  @if($isA5)
    <style>@media print { @page { size: A5; margin: 0; } }</style>
  @endif
@endpush

@section('content')
<section class="section wrap">
  <div class="poster-tools">
    <button type="button" class="btn" data-print>Print of bewaar als pdf</button>
    @if($voor === 'struikroven')
      <a href="{{ route('struikroven') }}">Terug naar Struikroven</a>
    @else
      <a href="{{ route('help-mee') }}#poster">Terug naar Help mee</a>
    @endif
    <span class="muted">Andere poster:
      @foreach(\App\Http\Controllers\MeedoenController::POSTERS as $key => $p)
        @if($key !== $voor)<a href="{{ route('poster', ['voor' => $key]) }}">{{ $p['titel'] }}</a>@if(! $loop->last) · @endif @endif
      @endforeach
    </span>
  </div>
  <p class="poster-tip muted">Tip: kies in het printvenster {{ $isA5 ? 'A5' : 'A4' }} en staand. Bij marges kies je «Geen».</p>

  <article class="poster poster--foto poster--{{ $voor }}{{ $isA5 ? ' poster--a5' : '' }}">
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
          @foreach($stappenLijst as $i => $stap)
            <li><b>{{ $i + 1 }}</b> <span>{{ $stap }}</span></li>
          @endforeach
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
