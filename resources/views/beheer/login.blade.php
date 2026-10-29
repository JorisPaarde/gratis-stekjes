@extends('layouts.site')

@section('title', $title)
@section('meta_description', $metaDescription)
@section('canonical', $canonical)

@push('head')
  @vite(['resources/css/admin.css'])
  <meta name="robots" content="noindex, nofollow">
@endpush

@section('content')
<section class="section wrap beheer-page">
  <h1>Beheer</h1>
  @if($errors->any())
    <p class="error" role="alert">{{ $errors->first() }}</p>
  @endif
  <form class="form" method="post" action="{{ route('beheer.login') }}" style="max-width:22rem">
    @csrf
    <label>Wachtwoord
      <input type="password" name="password" required autocomplete="current-password">
    </label>
    <button class="btn" type="submit">Inloggen</button>
  </form>
</section>
@endsection
