@extends('layouts.site')

@section('title', $title)
@section('meta_description', $metaDescription)
@section('canonical', $canonical)

@section('content')
<section class="section wrap">
  <h1 class="page-title">Mijn account</h1>
  <p class="section-intro">Hallo {{ $user->name }} — beheer hier je gegevens, wachtwoord en privacy.</p>

  <div class="soft-card">
    <h2><x-icon name="gebruiker" :size="20" /> Overzicht</h2>
    <p><strong>E-mail:</strong> {{ $user->email }}
      @if($user->hasPendingEmail())
        <span class="muted">(wijziging naar {{ $user->pending_email }} wacht op bevestiging)</span>
      @endif
    </p>
    @if($user->hasLocation())
      <p><strong>Locatie:</strong> {{ $user->locatie_label }}</p>
    @else
      <p class="muted">Nog geen locatie — nodig om aanbod te plaatsen.</p>
    @endif
  </div>

  <div class="soft-card" style="margin-top:1rem">
    <h2>Instellingen</h2>
    <ul class="prose" style="margin:0;padding-left:1.25rem">
      <li><a href="{{ route('account.profiel') }}">Profiel en locatie</a></li>
      <li><a href="{{ route('account.email') }}">E-mailadres wijzigen</a></li>
      <li><a href="{{ route('account.wachtwoord') }}">Wachtwoord wijzigen</a></li>
    </ul>
  </div>

  <div class="soft-card" style="margin-top:1rem">
    <h2>Activiteit</h2>
    <ul class="prose" style="margin:0;padding-left:1.25rem">
      <li><a href="{{ route('account.aanbod') }}">Mijn aanbod</a></li>
      <li><a href="{{ route('account.inbox') }}">Inbox</a></li>
      <li><a href="{{ route('aanbod') }}">Kaart / zoeken</a></li>
    </ul>
  </div>

  <div class="soft-card" style="margin-top:1rem">
    <h2>Privacy (AVG)</h2>
    <ul class="prose" style="margin:0;padding-left:1.25rem">
      <li><a href="{{ route('account.export') }}">Gegevens downloaden (JSON)</a></li>
      <li><a href="{{ route('account.verwijderen') }}">Account verwijderen</a></li>
    </ul>
  </div>

  <p style="margin-top:1.25rem">
    <form method="post" action="{{ route('account.uitloggen') }}" style="display:inline">
      @csrf
      <button class="btn btn-secondary" type="submit">Uitloggen</button>
    </form>
  </p>
</section>
@endsection
