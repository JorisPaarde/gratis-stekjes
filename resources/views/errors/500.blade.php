@extends('layouts.site')

@section('title', 'Er ging iets mis')
@section('meta_description', 'Er ging iets mis. Probeer het later opnieuw.')
@section('robots', 'noindex, follow')

@section('content')
<section class="section wrap prose">
  <h1 class="page-title">Er ging iets mis</h1>
  <p>Sorry — er is een technische fout opgetreden. Probeer het zo opnieuw. Blijft het misgaan, probeer later nog eens.</p>
  <p>
    <a class="btn" href="{{ route('home') }}">Naar home</a>
    <a class="btn btn-secondary" href="{{ route('account.inloggen') }}">Inloggen</a>
  </p>
</section>
@endsection
