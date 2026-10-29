@extends('layouts.site')

@section('title', $title)
@section('meta_description', $metaDescription)
@section('canonical', $canonical)

@section('content')
<section class="section wrap prose">
  @if($ok)
    <h1>Aanbod verlengd</h1>
    <p>Je aanbod staat weer {{ (int) config('stekjes.aanbod_ttl_days', 60) }} dagen in zoeken en op de kaart.</p>
    @auth
      <p><a class="btn" href="{{ route('account.aanbod') }}">Naar mijn aanbod</a></p>
    @else
      <p><a class="btn" href="{{ route('account.inloggen') }}">Inloggen</a></p>
    @endauth
  @else
    <h1>Verlengen mislukt</h1>
    <p class="error" role="alert">{{ $error }}</p>
    <p><a href="{{ route('home') }}">Naar home</a></p>
  @endif
</section>
@endsection
