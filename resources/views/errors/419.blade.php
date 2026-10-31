@extends('layouts.site')

@section('title', 'Sessie verlopen')
@section('meta_description', 'Je sessie is verlopen. Probeer het opnieuw.')
@section('robots', 'noindex, follow')

@section('content')
<section class="section wrap prose">
  <h1 class="page-title">Sessie verlopen</h1>
  <p>Je formulier-sessie is verlopen (of de beveiligingstoken klopt niet). Ga terug en probeer het opnieuw.</p>
  <p>
    <a class="btn" href="{{ url()->previous() }}">Terug</a>
    <a class="btn btn-secondary" href="{{ route('home') }}">Naar home</a>
  </p>
</section>
@endsection
