@extends('layouts.site')

@section('title', $title)
@section('meta_description', $metaDescription)
@section('canonical', $canonical)

@section('content')
<section class="section wrap">
  <h1>Inloggen</h1>

  @if($errors->any())
    <p class="error" role="alert">{{ $errors->first() }}</p>
  @endif

  <form class="form" method="post" action="{{ route('account.inloggen') }}">
    @csrf
    @include('partials.honeypot')
    <input type="hidden" name="next" value="{{ $next }}">

    <label>E-mail
      <input type="email" name="email" required maxlength="254" autocomplete="email" value="{{ old('email') }}">
    </label>
    <label>Wachtwoord
      <input type="password" name="password" required maxlength="200" autocomplete="current-password">
    </label>
    <button class="btn" type="submit">Inloggen</button>
  </form>

  <p class="muted">
    <a href="{{ route('account.registreren') }}">Account maken</a>
    · <a href="{{ route('account.bevestiging-nodig') }}">Bevestigingsmail opnieuw</a>
  </p>
</section>
@endsection
