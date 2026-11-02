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
    <span class="muted">8 labels per A4. Knip ze uit en steek er een bij elk stekje dat je weggeeft, of plak hem op het potje.</span>
  </div>

  <div class="labels-vel">
    @for($i = 0; $i < 8; $i++)
      <div class="label">
        <div class="label-qr" data-qr-url="{{ $url }}"></div>
        <div class="label-tekst">
          <p class="label-kop">Gratis gekregen</p>
          <p>Ook iets over? Geef het weg aan iemand in de buurt.</p>
          <p class="label-url">{{ $kortUrl }}</p>
        </div>
      </div>
    @endfor
  </div>
</section>
@endsection

@push('scripts')
  @vite(['resources/js/poster.js'])
@endpush
