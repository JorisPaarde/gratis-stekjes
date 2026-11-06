@extends('layouts.site')

@section('title', 'Oeps, dit gaat niet goed')
@section('meta_description', 'Deze pagina bestaat niet (meer). Ga verder via Doe mee, stektips of het aanbod.')
@section('robots', 'noindex, follow')

@section('content')
<section class="section wrap prose">
  <h1 class="page-title">Oeps, dit gaat niet goed</h1>
  <p>Deze pagina bestaat niet (meer). Misschien is de link verouderd of verkeerd geplakt. Geen zorgen — kies hieronder waar je heen wilt.</p>
  <p class="404-actions">
    <a class="btn" href="{{ route('home') }}">Naar home</a>
    <a class="btn btn-secondary" href="{{ route('aanbod') }}">Bekijk het aanbod</a>
  </p>
  <p class="404-meer muted">
    <a href="{{ route('stektips.index') }}">Stektips</a>
    <span aria-hidden="true">·</span>
    <a href="{{ route('ga.word-lid', ['utm_content' => '404']) }}">Doe mee</a>
  </p>
</section>
@endsection
