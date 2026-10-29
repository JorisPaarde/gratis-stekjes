@extends('layouts.site')

@section('title', $title)
@section('meta_description', $metaDescription)
@section('canonical', $canonical)

@push('head')
  @vite(['resources/css/partners.css'])
@endpush

@section('content')
<section class="section wrap partners-page">
  <h1>Partners</h1>
  <p class="section-intro">Wij bouwen niet alles zelf. Hieronder staan initiatieven waar we naast willen staan. Waar we iets nog niet zeker weten, staat <em>Volgt binnenkort</em>.</p>

  <div class="stack partners-stack">
    <article class="soft-card">
      <h2><a href="https://voedselbosbouw.org/" rel="noopener noreferrer">Voedselbosbouw.org</a></h2>
      <p>Stichting Voedselbosbouw Nederland — een plek voor kennis en praktijk rond voedselbossen. Volgt binnenkort: hoe we precies samenwerken of naar elkaar verwijzen.</p>
      <p class="muted">Geen bezoeklocatie op onze kaart; zie hun eigen site.</p>
    </article>
    <article class="soft-card">
      <h2><a href="https://meerbomen.nu/" rel="noopener noreferrer">MeerBomen Nu</a></h2>
      <p>Zij zetten zich in voor bomen. Het verschil met ons: zij doen bomen, wij doen stekjes van mens tot mens.</p>
      <p class="muted">Volgt binnenkort: eventuele concrete samenwerking. Geen bezoeklocatie op onze kaart.</p>
    </article>
  </div>

  <h2>Andere links</h2>
  <p class="section-intro">Losse verwijzingen. Teksten mogen later scherper.</p>
  <div class="grid-cards partners-links">
    <article class="soft-card">
      <h3><a href="https://stekjesroute.com/" rel="noopener noreferrer">Stekjesroute</a></h3>
      <p>Volgt binnenkort</p>
      <p class="muted">Daar ruilen; hier gratis met een praatje.</p>
    </article>
    <article class="soft-card">
      <h3><a href="https://velt.nu/zaden-en-plantenruilbeurzen" rel="noopener noreferrer">Velt</a></h3>
      <p>Volgt binnenkort</p>
    </article>
    <article class="soft-card">
      <h3><a href="https://zadenbibliotheeknederland.nl/" rel="noopener noreferrer">Zadenbibliotheek Nederland</a></h3>
      <p>Volgt binnenkort</p>
      <p class="muted">Zaden; wij doen stekjes.</p>
    </article>
    <article class="soft-card">
      <h3><a href="https://voedseluithetbos.nl/" rel="noopener noreferrer">Voedsel uit het Bos</a></h3>
      <p>Volgt binnenkort</p>
    </article>
    <article class="soft-card">
      <h3><a href="https://netwerkvoedselbosbouw.nl/" rel="noopener noreferrer">Netwerk Voedselbosbouw</a></h3>
      <p>Volgt binnenkort</p>
    </article>
    <article class="soft-card">
      <h3><a href="https://www.permacultuurnederland.org/" rel="noopener noreferrer">Permacultuur Nederland</a></h3>
      <p>Volgt binnenkort</p>
    </article>
    <article class="soft-card">
      <h3><a href="https://www.eetbaargoed.nl/" rel="noopener noreferrer">Eetbaargoed</a></h3>
      <p>Volgt binnenkort</p>
    </article>
  </div>

  <p class="partners-kaart-hint">Plekken met een bezoeklocatie (voedselbos, plantenbieb, ruilkast, …) staan op de <a href="{{ route('aanbod') }}">aanbodkaart</a>. Beheer van die markers volgt in de admin-PR.</p>
</section>
@endsection
