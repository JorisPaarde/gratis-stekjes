@extends('layouts.site')

@section('title', $title)
@section('meta_description', $metaDescription)
@section('canonical', $canonical)

@section('content')
<section class="section wrap">
  <h1 class="page-title">E-mailadres wijzigen</h1>
  <p class="section-intro">Je huidige adres blijft actief tot je het nieuwe adres bevestigt via de link in de mail.</p>

  @if($errors->any())
    <p class="error" role="alert">{{ $errors->first() }}</p>
  @endif

  <div class="soft-card" style="margin-bottom:1.25rem">
    <p><strong>Huidig e-mailadres:</strong> {{ $user->email }}</p>
    @if($user->hasPendingEmail())
      <p><strong>Wacht op bevestiging:</strong> {{ $user->pending_email }}</p>
      <form method="post" action="{{ route('account.email.annuleren') }}" style="margin-top:.75rem">
        @csrf
        <button class="btn btn-secondary" type="submit">Wijziging annuleren</button>
      </form>
    @endif
  </div>

  <form class="form" method="post" action="{{ route('account.email') }}">
    @csrf
    <label>Nieuw e-mailadres
      <input type="email" name="email" required maxlength="254" autocomplete="email" value="{{ old('email') }}">
    </label>
    <label>Bevestig met wachtwoord
      <input type="password" name="password" required maxlength="200" autocomplete="current-password">
    </label>
    <button class="btn" type="submit">Bevestigingsmail sturen</button>
  </form>

  <p class="muted" style="margin-top:1rem">
    <a href="{{ route('account.index') }}">Terug naar Mijn account</a>
  </p>
</section>
@endsection
