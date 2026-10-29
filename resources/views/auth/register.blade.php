@extends('layouts.site')

@section('title', $title)
@section('meta_description', $metaDescription)
@section('canonical', $canonical)

@section('content')
<section class="section wrap">
  <h1>Account maken</h1>
  <p class="section-intro">Gratis meedoen. Stekjes blijven gratis — geen verkoop. Na registratie bevestig je je e-mail (double opt-in).</p>

  @if($errors->any())
    <p class="error" role="alert">{{ $errors->first() }}</p>
  @endif

  <form class="form" method="post" action="{{ route('account.registreren') }}" novalidate>
    @csrf
    @include('partials.form-guard')
    <input type="hidden" name="rol" value="{{ $rol }}">

    <label>Voornaam of bijnaam *
      <input name="voornaam" required maxlength="80" autocomplete="nickname" value="{{ old('voornaam') }}">
    </label>
    <label>E-mail *
      <input type="email" name="email" required maxlength="254" autocomplete="email" value="{{ old('email') }}">
    </label>
    <label>Wachtwoord * <span class="hint">Minstens 10 tekens</span>
      <input type="password" name="password" required minlength="10" maxlength="200" autocomplete="new-password">
    </label>
    <label>Wachtwoord opnieuw *
      <input type="password" name="password_confirmation" required minlength="10" maxlength="200" autocomplete="new-password">
    </label>

    @if($rol)
      <p class="muted">Je koos: <strong>{{ $rol === 'uitdelen' ? 'stekjes uitdelen' : 'stekjes ophalen' }}</strong>. Na het bevestigen vul je je profiel en eventueel aanbod in.</p>
    @endif

    <label class="checkbox">
      <input type="checkbox" name="gratis_akkoord" value="1" required @checked(old('gratis_akkoord'))>
      <span>Ik ga akkoord: alleen <strong>gratis</strong>, geen verkoop van stekjes via deze site.</span>
    </label>
    <label class="checkbox">
      <input type="checkbox" name="privacy" value="1" required @checked(old('privacy'))>
      <span>Ik ga akkoord met de <a href="{{ route('privacy') }}">privacyverklaring</a>.</span>
    </label>

    <button class="btn" type="submit">Registreren</button>
  </form>

  <p class="muted">Al een account? <a href="{{ route('account.inloggen') }}">Inloggen</a></p>
</section>
@endsection
