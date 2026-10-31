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
      <li><a href="{{ route('account.ervaringen') }}">Mijn ervaringen</a></li>
      <li><a href="{{ route('aanbod') }}">Kaart / zoeken</a></li>
    </ul>
  </div>

  @isset($badges)
  <div class="soft-card" style="margin-top:1rem">
    <h2>Mijlpalen</h2>
    <p class="muted" style="margin-top:0">Lichte badges als herinnering aan wat je al deed.</p>
    <ul class="prose" style="margin:0;padding-left:1.25rem">
      @foreach($badges as $badge)
        <li>
          @if($badge['earned'])
            <x-icon name="check" :size="16" /> <strong>{{ $badge['label'] }}</strong>
          @else
            <span class="muted">{{ $badge['label'] }}</span>
          @endif
          <span class="muted"> — {{ $badge['hint'] }}</span>
        </li>
      @endforeach
    </ul>
    <p style="margin-top:.75rem"><a href="{{ route('account.ervaringen.create') }}">Deel een ervaring</a></p>
  </div>
  @endisset

  <div class="soft-card" style="margin-top:1rem">
    <h2>Privacy (AVG)</h2>
    <ul class="prose" style="margin:0;padding-left:1.25rem">
      <li><a href="{{ route('account.export') }}">Gegevens downloaden (JSON)</a></li>
      <li><a href="{{ route('account.verwijderen') }}">Account verwijderen</a></li>
    </ul>
  </div>

  <div class="soft-card" style="margin-top:1rem" data-pwa-install-block>
    <h2>Zet Gratis planten op je beginscherm</h2>
    <p>Ik open de site sneller als app, zonder app store. Geen pushmeldingen — alleen een icoon op mijn beginscherm.</p>
    <ol class="prose" style="margin:.75rem 0 0;padding-left:1.25rem">
      <li><strong>iPhone (Safari):</strong> tik op Delen → «Zet op beginscherm» → Voeg toe.</li>
      <li><strong>Android (Chrome):</strong> open het menu (⋮) → «App installeren» of «Toevoegen aan startscherm».</li>
    </ol>
    <p style="margin-top:1rem">
      <button type="button" class="btn" data-pwa-install hidden>Installeer Gratis planten</button>
    </p>
    <p class="muted" data-pwa-install-hint style="margin-top:.75rem">
      Zie je geen installatieknop? Gebruik dan de stappen hierboven in Safari of Chrome.
    </p>
  </div>

  <p style="margin-top:1.25rem">
    <form method="post" action="{{ route('account.uitloggen') }}" style="display:inline">
      @csrf
      <button class="btn btn-secondary" type="submit">Uitloggen</button>
    </form>
  </p>
</section>
@endsection
