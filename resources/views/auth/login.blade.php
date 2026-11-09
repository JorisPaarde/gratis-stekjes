@extends('layouts.site')

@section('title', $title)
@section('meta_description', $metaDescription)
@section('canonical', $canonical)

@section('content')
<section class="section wrap">
  <h1 class="page-title">Inloggen</h1>

  @if($errors->any())
    <p class="error" role="alert">{{ $errors->first() }}</p>
  @endif

  <form class="form" method="post" action="{{ route('account.inloggen') }}" autocomplete="on">
    @csrf
    @include('partials.honeypot')
    <input type="hidden" name="next" value="{{ $next }}">

    <label for="login-email">E-mailadres
      <input
        type="email"
        id="login-email"
        name="email"
        required
        maxlength="254"
        autocomplete="username"
        inputmode="email"
        autocapitalize="off"
        spellcheck="false"
        value="{{ old('email') }}"
      >
    </label>
    <label for="login-password">Wachtwoord
      <input
        type="password"
        id="login-password"
        name="password"
        required
        maxlength="200"
        autocomplete="current-password"
      >
    </label>
    <label class="checkbox" for="login-remember">
      <input type="checkbox" id="login-remember" name="remember" value="1" @checked(old('remember'))>
      <span>Onthoud mij</span>
    </label>
    <button class="btn" type="submit">Inloggen</button>
  </form>

  <p class="muted">
    <a href="{{ route('account.wachtwoord.vergeten') }}">Wachtwoord vergeten?</a>
    · <a href="{{ route('account.registreren', ['next' => $next]) }}">Account maken</a>
    · <a href="{{ route('account.bevestiging-nodig') }}">Bevestigingsmail opnieuw sturen</a>
  </p>
</section>
@endsection
