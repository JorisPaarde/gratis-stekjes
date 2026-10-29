@extends('layouts.site')

@section('title', $title)
@section('meta_description', $metaDescription)
@section('canonical', $canonical)

@section('content')
<section class="section wrap prose">
  <h1 class="page-title">Organisaties</h1>
  <p class="section-intro">Korte pagina’s over organisaties en initiatieven rond gratis planten delen. Geen officiële partnerschappen — alleen publiek controleerbare info met link naar hun eigen site.</p>
  <ul>
    @foreach($organisaties as $org)
      <li>
        <a href="{{ route('organisaties.show', ['slug' => $org['slug']]) }}">{{ $org['naam'] }}</a>
        <span class="muted"> · {{ $org['plaats'] }}</span>
      </li>
    @endforeach
  </ul>
  <p class="muted">
    <a href="{{ route('voor-organisaties') }}">Voor organisaties</a> ·
    <a href="{{ route('voor-organisaties.aanmelden') }}">Plek aanmelden</a> ·
    <a href="{{ route('deelplekken') }}">Deelplekken</a>
  </p>
</section>
@endsection
