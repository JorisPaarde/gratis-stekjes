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
    <a href="{{ route('help-mee') }}#poster">Terug naar Help mee</a>
    <span class="muted">Andere poster:
      @foreach(\App\Http\Controllers\MeedoenController::POSTERS as $key => $p)
        @if($key !== $voor)<a href="{{ route('poster', ['voor' => $key]) }}">{{ $p['titel'] }}</a>@if(! $loop->last) · @endif @endif
      @endforeach
    </span>
  </div>

  <article class="poster">
    <p class="poster-merk"><img src="/assets/badges/eerste_stekje.svg" alt="" width="56" height="56"> {{ config('site.name') }}</p>
    <div>
      <h1>{{ $poster['kop'] }}</h1>
      <p class="poster-sub">{{ $poster['sub'] }}</p>
      <div class="poster-qr" data-qr-url="{{ $url }}"></div>
      <ol class="poster-stappen">
        <li><b>1</b> Scan de code en kijk wat er bij jou in de buurt te halen is.</li>
        <li><b>2</b> Iets over? Zet het er gratis op, het kost je een minuut.</li>
        <li><b>3</b> Je haalt het zelf op. Een praatje is de enige prijs.</li>
      </ol>
    </div>
    <div>
      <p class="poster-url">{{ $kortUrl }}</p>
      <p class="poster-voet">Gratis, zonder reclame. Voor iedereen in Nederland en België.</p>
    </div>
  </article>
</section>
@endsection

@push('scripts')
  @vite(['resources/js/poster.js'])
@endpush
