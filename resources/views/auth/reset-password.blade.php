@extends('layouts.site')

@section('title', $title)
@section('meta_description', $metaDescription)
@section('canonical', $canonical)

@section('content')
<section class="section wrap">
  <h1 class="page-title">Nieuw wachtwoord</h1>
  <p class="section-intro">Kies een nieuw wachtwoord van minstens 10 tekens.</p>

  @if($errors->any())
    <p class="error" role="alert">{{ $errors->first() }}</p>
  @endif

  <form class="form" method="post" action="{{ route('account.wachtwoord.reset.store') }}">
    @csrf
    <input type="hidden" name="token" value="{{ $token }}">

    <label>E-mail
      <input type="email" name="email" required maxlength="254" autocomplete="email" value="{{ old('email', $email) }}">
    </label>
    <label>Nieuw wachtwoord
      <input type="password" name="password" required minlength="10" maxlength="200" autocomplete="new-password">
    </label>
    <label>Wachtwoord opnieuw
      <input type="password" name="password_confirmation" required minlength="10" maxlength="200" autocomplete="new-password">
    </label>
    <button class="btn" type="submit">Wachtwoord opslaan</button>
  </form>
</section>
@endsection
