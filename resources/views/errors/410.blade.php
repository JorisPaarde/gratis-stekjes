@extends('layouts.site')

@section('title', 'Deze deelplek is weg')
@section('meta_description', 'Deze deelplek bestaat niet meer. Bekijk andere deelplekken op de kaart.')
@section('robots', 'noindex, follow')

@section('content')
<section class="section wrap prose">
  <h1 class="page-title">Deze deelplek is weg</h1>
  <p>De plek die je zoekt is definitief verwijderd. Geen zorgen, op de kaart staan nog genoeg andere deelplekken.</p>
  <p class="404-actions">
    <a class="btn" href="{{ route('deelplekken') }}">Naar deelplekken</a>
    <a class="btn btn-secondary" href="{{ route('home') }}">Naar home</a>
  </p>
</section>
@endsection
