@extends('layouts.site')

@section('title', 'Te veel verzoeken')
@section('meta_description', 'Te veel verzoeken. Wacht even en probeer opnieuw.')
@section('robots', 'noindex, follow')

@section('content')
<section class="section wrap prose">
  <h1 class="page-title">Te veel verzoeken</h1>
  <p>{{ $exception->getMessage() !== '' && $exception->getMessage() !== 'Too Many Attempts.'
      ? $exception->getMessage()
      : 'Je hebt te vaak iets geprobeerd. Wacht even en probeer het daarna opnieuw.' }}</p>
  <p>
    <a class="btn" href="{{ url()->previous() }}">Terug</a>
    <a class="btn btn-secondary" href="{{ route('home') }}">Naar home</a>
  </p>
</section>
@endsection
