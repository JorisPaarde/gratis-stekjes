@extends('layouts.site')

@section('title', $title)
@section('meta_description', $metaDescription)
@section('canonical', $canonical)

@section('content')
<section class="section wrap">
  <h1 class="page-title">Account maken</h1>
  <p class="section-intro">Meedoen is gratis. Met één account kun je iets aanbieden én iets ophalen: planten, stekjes of zaden. Er wordt niets verkocht. Je krijgt een e-mail om je aanmelding te bevestigen.</p>

  @if($errors->any())
    <ul class="error-list" role="alert">
      @foreach($errors->all() as $error)
        <li class="error">{{ $error }}</li>
      @endforeach
    </ul>
  @endif

  <form class="form" method="post" action="{{ route('account.registreren') }}" novalidate>
    @csrf
    @include('partials.honeypot')

    <label>Voornaam of bijnaam *
      <input name="voornaam" required maxlength="80" autocomplete="nickname" value="{{ old('voornaam') }}">
    </label>
    @include('partials.naam-check')
    <label>E-mailadres *
      <input type="email" name="email" required maxlength="254" autocomplete="email" value="{{ old('email') }}">
    </label>
    <label>Wachtwoord * <span class="hint">Minstens 10 tekens</span>
      <input type="password" name="password" required minlength="10" maxlength="200" autocomplete="new-password">
    </label>
    <label>Wachtwoord opnieuw *
      <input type="password" name="password_confirmation" required minlength="10" maxlength="200" autocomplete="new-password">
    </label>

    <label class="checkbox">
      <input type="checkbox" name="gratis_akkoord" value="1" required @checked(old('gratis_akkoord'))>
      <span>Ik ga akkoord: ik bied alles <strong>gratis</strong> aan en verkoop niets via deze site.</span>
    </label>
    <label class="checkbox">
      <input type="checkbox" name="privacy" value="1" required @checked(old('privacy'))>
      <span>Ik ga akkoord met de <a href="{{ route('privacy') }}">privacyverklaring</a>.</span>
    </label>

    <button class="btn" type="submit">Account maken</button>
  </form>

  <p class="muted">Al een account? <a href="{{ route('account.inloggen') }}">Inloggen</a></p>
</section>
@endsection
