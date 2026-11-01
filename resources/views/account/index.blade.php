@extends('layouts.site')

@section('title', $title)
@section('meta_description', $metaDescription)
@section('canonical', $canonical)

@section('content')
<section class="section wrap">
  <h1 class="page-title">Mijn account</h1>
  <p class="section-intro">Hallo {{ $user->name }}, beheer hier je gegevens, wachtwoord en privacy.</p>

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
      <p class="muted">Nog geen locatie. Die heb je nodig om aanbod te plaatsen.</p>
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
      <li><a href="{{ route('account.inbox') }}">Inbox</a>@if(($ongelezenBerichten ?? 0) > 0) <span class="ongelezen-badge">{{ $ongelezenBerichten }} nieuw</span>@endif</li>
      <li><a href="{{ route('account.ervaringen') }}">Mijn ervaringen</a></li>
      <li><a href="{{ route('aanbod') }}">Kaart / zoeken</a></li>
    </ul>
  </div>

  @isset($badges)
  @php
    $verdiend = collect($badges)->where('earned', true)->count();
    $pionier = collect($badges)->firstWhere('exclusief', true);
    $gewoon = collect($badges)->where('exclusief', false)->values();
  @endphp
  <div class="soft-card badges-card" style="margin-top:1rem" id="badges">
    <h2>Badges</h2>
    <p class="muted" style="margin-top:0">Je hebt er {{ $verdiend }} van de {{ count($badges) }}. Elke stap die je zet, levert er een op.</p>

    @if($pionier)
      <div class="badge-pionier">
        <img src="{{ $pionier['img'] }}" alt="" width="96" height="99">
        <div>
          <p class="badge-pionier-label">Exclusief · er is er maar één</p>
          <h3>{{ $pionier['label'] }}</h3>
          <p>{{ $pionier['hint'] }}</p>
        </div>
      </div>
    @endif

    <ul class="badge-grid">
      @foreach($gewoon as $badge)
        <li class="badge-item{{ $badge['earned'] ? ' is-verdiend' : '' }}">
          <img src="{{ $badge['img'] }}" alt="" width="72" height="72" loading="lazy">
          <strong>{{ $badge['label'] }}</strong>
          <span class="visually-hidden">{{ $badge['earned'] ? 'Verdiend.' : 'Nog niet verdiend.' }}</span>
          <span class="badge-hint">{{ $badge['hint'] }}</span>
        </li>
      @endforeach
    </ul>
    <p style="margin-top:.75rem"><a href="{{ route('account.ervaringen.create') }}">Deel een ervaring</a> · <a href="{{ route('account.aanbod') }}">Bied iets aan</a></p>
  </div>
  @endisset

  <div class="soft-card" style="margin-top:1rem">
    <h2>Privacy (AVG)</h2>
    <ul class="prose" style="margin:0;padding-left:1.25rem">
      <li><a href="{{ route('account.export') }}">Gegevens downloaden</a></li>
      <li><a href="{{ route('account.verwijderen') }}">Account verwijderen</a></li>
    </ul>
  </div>

  <div class="soft-card" style="margin-top:1rem" data-pwa-install-block>
    <h2>Zet Gratis planten op je beginscherm</h2>
    <p>Zet Gratis planten op je beginscherm, dan open je de site met één tik. Geen app store en geen pushmeldingen, alleen een icoon.</p>
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
