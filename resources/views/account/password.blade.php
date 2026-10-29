@extends('layouts.site')

@section('title', $title)
@section('meta_description', $metaDescription)
@section('canonical', $canonical)

@section('content')
<section class="section wrap">
  <h1 class="page-title">Wachtwoord wijzigen</h1>
  <p class="section-intro">Na het wijzigen worden andere sessies uitgelogd. Dit apparaat blijft ingelogd.</p>

  @if($errors->any())
    <p class="error" role="alert">{{ $errors->first() }}</p>
  @endif

  <form class="form" method="post" action="{{ route('account.wachtwoord') }}">
    @csrf
    <label>Huidig wachtwoord
      <input type="password" name="current_password" required maxlength="200" autocomplete="current-password">
    </label>
    <label>Nieuw wachtwoord <span class="hint">Minstens 10 tekens</span>
      <input type="password" name="password" required minlength="10" maxlength="200" autocomplete="new-password">
    </label>
    <label>Nieuw wachtwoord opnieuw
      <input type="password" name="password_confirmation" required minlength="10" maxlength="200" autocomplete="new-password">
    </label>
    <button class="btn" type="submit">Wachtwoord opslaan</button>
  </form>

  <p class="muted" style="margin-top:1rem">
    <a href="{{ route('account.index') }}">Terug naar Mijn account</a>
  </p>
</section>
@endsection
