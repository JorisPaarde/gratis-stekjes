@extends('layouts.site')

@section('title', $title)
@section('meta_description', $metaDescription)
@section('canonical', $canonical)

@section('content')
<section class="section wrap">
  <h1 class="page-title">Mijn account</h1>
  <p class="section-intro">Hallo {{ $user->name }}, beheer hier je gegevens, wachtwoord en privacy.</p>
  @if($user->isStatsViewer())
    <p class="account-hoofdactie"><a class="btn btn-groot" href="{{ route('beheer.statistieken') }}">Statistieken</a></p>
  @else
    <p class="account-hoofdactie"><a class="btn btn-groot" href="{{ route('aanbieden') }}"><x-icon name="plus" :size="18" /> Bied iets aan</a>
      <a class="btn btn-secondary" href="{{ route('account.aanbod') }}">Mijn aanbod</a></p>
  @endif

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
      @unless($user->isStatsViewer())
        <li><a href="{{ route('account.aanbod') }}">Mijn aanbod</a></li>
      @endunless
      <li><a href="{{ route('account.inbox') }}">Inbox</a>@if(($ongelezenBerichten ?? 0) > 0) <span class="ongelezen-badge">{{ $ongelezenBerichten }} nieuw</span>@endif</li>
      <li><a href="{{ route('account.ervaringen') }}">Mijn ervaringen</a></li>
      <li><a href="{{ route('account.verenigingen') }}">Mijn verenigingen</a> <span class="muted">(volkstuin, tuinvereniging, buurttuin)</span></li>
      <li><a href="{{ route('aanbod') }}">Kaart / zoeken</a></li>
      @can('see-stats')
        <li><a href="{{ route('beheer.statistieken') }}">Statistieken</a></li>
      @endcan
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
      <a class="badge-pionier" href="{{ $pionier['doel']['href'] }}">
        <img src="{{ $pionier['img'] }}" alt="" width="96" height="99">
        <div>
          <p class="badge-pionier-label">Exclusief · er is er maar één</p>
          <h3>{{ $pionier['label'] }}</h3>
          <p>{{ $pionier['hint'] }}</p>
          <span class="visually-hidden">{{ $pionier['doel']['label'] }}</span>
        </div>
      </a>
    @endif

    <ul class="badge-grid">
      @foreach($gewoon as $badge)
        <li>
          <a class="badge-item{{ $badge['earned'] ? ' is-verdiend' : '' }}" href="{{ $badge['doel']['href'] }}">
            <img src="{{ $badge['img'] }}" alt="" width="72" height="72" loading="lazy">
            <strong>{{ $badge['label'] }}</strong>
            <span class="visually-hidden">{{ $badge['earned'] ? 'Verdiend.' : 'Nog niet verdiend.' }} {{ $badge['doel']['label'] }}.</span>
            <span class="badge-hint">{{ $badge['hint'] }}</span>
          </a>
        </li>
      @endforeach
    </ul>

    <div class="badge-uitleg-lijst">
      <aside class="badge-uitleg" id="uitleg-oprichters" tabindex="-1">
        <h3>Eerste leden</h3>
        <p>De badges «Een van de eerste 10», «eerste 50», «eerste 100» en «eerste 200» krijg je automatisch als je tot de eerste leden hoort, op volgorde van aanmelden. Je kunt ze niet achteraf verdienen.</p>
      </aside>
      <aside class="badge-uitleg" id="uitleg-pionier" tabindex="-1">
        <h3>Pionier</h3>
        <p>Pionier is voor het lid dat als allereerste iets weggaf. Deze badge bestaat maar één keer; je kunt hem niet zelf invullen.</p>
      </aside>
      <aside class="badge-uitleg" id="uitleg-vaste-plant" tabindex="-1">
        <h3>Vaste plant</h3>
        <p>Vaste plant krijg je als je een jaar lid bent. Dat gaat vanzelf; je hoeft niets in te vullen.</p>
      </aside>
    </div>

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
    <p style="margin-top:1rem">
      <a class="btn" href="{{ route('app') }}"><x-icon name="telefoon" :size="16" /> Gratis planten als app</a>
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
