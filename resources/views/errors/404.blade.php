@extends('layouts.site')

@section('title', 'Pagina niet gevonden')
@section('meta_description', 'Deze pagina bestaat niet (meer).')
@section('canonical', url()->current())

@section('content')
<section class="section wrap prose">
  <h1 class="page-title">Pagina niet gevonden</h1>
  <p>Deze pagina bestaat niet (meer). Controleer de link of ga terug naar de homepage.</p>
  <p>
    <a class="btn" href="{{ route('home') }}">Naar home</a>
    <a class="btn btn-secondary" href="{{ route('aanbod') }}">Aanbod bekijken</a>
  </p>
</section>
@endsection
