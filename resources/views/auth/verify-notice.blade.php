@extends('layouts.site')

@section('title', $title)
@section('meta_description', $metaDescription)
@section('canonical', $canonical)

@section('content')
<section class="section wrap prose">
  <h1 class="page-title">Bevestig je e-mail</h1>
  <p>We hebben je een bevestigingslink gestuurd. Klik die link om verder te gaan. Geen mail? Kijk in je spamfolder of stuur hieronder opnieuw.</p>

  @if($errors->any())
    <p class="error" role="alert">{{ $errors->first() }}</p>
  @endif

  <form class="form" method="post" action="{{ route('account.bevestiging-opnieuw') }}">
    @csrf
    <label>E-mail opnieuw sturen
      <input type="email" name="email" required maxlength="254" autocomplete="email" value="{{ old('email') }}">
    </label>
    <button class="btn btn-secondary" type="submit">Stuur opnieuw</button>
  </form>

  <p><a class="btn" href="{{ route('home') }}">Naar home</a>
    <a href="{{ route('account.inloggen') }}">Inloggen</a></p>
</section>
@endsection
