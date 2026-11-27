@extends('layouts.site')

@section('title', $title)
@section('meta_description', $metaDescription)
@section('canonical', $canonical)

@section('content')
<section class="section wrap app-page" data-app-pagina>
  <h1 class="page-title">Gratis planten als app</h1>
  <p class="section-intro" data-app-intro>Zet Gratis planten op je beginscherm. Dan open je het met één tik, net als een app. Gratis, zonder app store en zonder meldingen.</p>

  <div class="app-kaart" data-app-scherm="android" hidden>
    <h2>Installeer in één tik</h2>
    <div class="app-icoon">
      <img src="{{ asset('assets/icons/icon-192.png') }}" width="56" height="56" alt="">
      <span>
        <strong>Gratis planten</strong>
        <span class="muted">Komt op je beginscherm</span>
      </span>
    </div>
    <p>
      <button type="button" class="btn w-full" data-pwa-install hidden>
        <x-icon name="telefoon" :size="16" /> Installeer de app
      </button>
    </p>
    <p class="muted" data-pwa-aanbod-prompt hidden style="margin:.75rem 0 0">Je telefoon vraagt of het goed is. Tik dan op «Installeren».</p>
  </div>

  <div class="app-kaart" data-app-scherm="iphone" hidden>
    <h2>In 3 stappen op je iPhone</h2>
    <div class="app-icoon">
      <img src="{{ asset('assets/icons/icon-192.png') }}" width="56" height="56" alt="">
      <span>
        <strong>Gratis planten</strong>
        <span class="muted">Komt op je beginscherm</span>
      </span>
    </div>
    <ol class="stappen">
      <li>
        <span>Tik onderin Safari op <span class="kbd"><x-icon name="delen-omhoog" :size="16" /> Deel</span>.<br>
        <span class="muted">Zie je die niet? Tik eerst op <span class="kbd"><x-icon name="meer" :size="16" /></span>.</span></span>
      </li>
      <li>
        <span>Scrol een stukje en tik op <span class="kbd"><x-icon name="beginscherm" :size="16" /> Zet op beginscherm</span>.</span>
      </li>
      <li>
        <span>Tik rechtsboven op <span class="kbd">Voeg toe</span>. Klaar.</span>
      </li>
    </ol>
  </div>

  <div class="app-kaart" data-app-scherm="anders" hidden>
    <h2>Open deze pagina in Safari of Chrome</h2>
    <p>In deze browser lukt het niet. Op een iPhone kan het in Safari, op Android in Chrome.</p>
    <p>
      <button type="button" class="btn btn-secondary w-full" data-app-kopieer>
        <x-icon name="link" :size="16" /> Kopieer de link
      </button>
    </p>
    <p class="muted" data-app-kopieer-status style="margin:.75rem 0 0">Plak de link in Safari of Chrome en kom terug op deze pagina.</p>
  </div>

  <div class="app-kaart" data-app-scherm="desktop" hidden>
    <h2>Op je telefoon</h2>
    <p>De app is vooral handig op je telefoon. Open <strong>gratisplanten.nl/app</strong> op je telefoon en volg daar de stappen.</p>
    <p class="muted" style="margin:0">Op je computer gebruik je gewoon de site.</p>
  </div>

  <div class="app-kaart" data-app-scherm="geinstalleerd" hidden>
    <h2><x-icon name="check" :size="20" /> Je hebt de app al</h2>
    <p>Gratis planten staat op je beginscherm. Tik op het icoon, dan open je het meteen.</p>
    <div class="app-icoon">
      <img src="{{ asset('assets/icons/icon-192.png') }}" width="56" height="56" alt="">
      <span>
        <strong>Gratis planten</strong>
        <span class="muted">Staat op je beginscherm</span>
      </span>
    </div>
  </div>

  <noscript>
    <div class="app-kaart">
      <h2>In 3 stappen op je iPhone</h2>
      <ol class="stappen">
        <li><span>Tik onderin Safari op <span class="kbd">Deel</span>.</span></li>
        <li><span>Scrol een stukje en tik op <span class="kbd">Zet op beginscherm</span>.</span></li>
        <li><span>Tik rechtsboven op <span class="kbd">Voeg toe</span>. Klaar.</span></li>
      </ol>
    </div>
    <div class="app-kaart">
      <h2>Op je telefoon</h2>
      <p>De app is vooral handig op je telefoon. Open <strong>gratisplanten.nl/app</strong> op je telefoon en volg daar de stappen.</p>
    </div>
  </noscript>

  <p class="app-terug"><a href="{{ route('home') }}">← Terug naar de site</a></p>
</section>
@endsection
