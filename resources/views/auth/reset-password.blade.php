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

  <form class="form" method="post" action="{{ route('account.wachtwoord.reset.store') }}" autocomplete="on">
    @csrf
    <input type="hidden" name="token" value="{{ $token }}">

    <label for="reset-email">E-mail
      <input
        type="email"
        id="reset-email"
        name="email"
        required
        maxlength="254"
        autocomplete="email"
        inputmode="email"
        autocapitalize="off"
        spellcheck="false"
        value="{{ old('email', $email) }}"
      >
    </label>
    <label for="reset-password">Nieuw wachtwoord
      <input
        type="password"
        id="reset-password"
        name="password"
        required
        minlength="10"
        maxlength="200"
        autocomplete="new-password"
        passwordrules="minlength: 10;"
      >
    </label>
    <label for="reset-password-confirm">Wachtwoord opnieuw
      <input
        type="password"
        id="reset-password-confirm"
        name="password_confirmation"
        required
        minlength="10"
        maxlength="200"
        autocomplete="new-password"
        passwordrules="minlength: 10;"
      >
    </label>
    <button class="btn" type="submit">Wachtwoord opslaan</button>
  </form>
</section>
@endsection
