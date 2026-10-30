@extends('layouts.site')

@section('title', $title)
@section('meta_description', $metaDescription)
@section('canonical', $canonical)

@push('head')
  @vite(['resources/css/partners.css'])
@endpush

@section('content')
<section class="section wrap partners-page">
  <h1 class="page-title">Initiatieven</h1>
  <p class="section-intro">Hieronder staan initiatieven waar ik naar link — plantenasiels, plantenbiebs, ruilkasten, voedselbossen en meer. Geen officiële partnerschappen; het zijn plekken en organisaties rondom gratis planten delen. Teksten zijn kort en feitelijk, gebaseerd op hun eigen websites.</p>

  <div class="stack partners-stack">
    <article class="soft-card">
      <h2><a href="https://voedselbosbouw.org/" rel="noopener noreferrer">Voedselbosbouw.org</a></h2>
      <p>Stichting Voedselbosbouw Nederland begeleidt boeren en grondeigenaren bij agrarische voedselbossen, deelt kennis en lobbyt voor deze vorm van landbouw. Ik richt me op stekjes van mens tot mens; zij op het bredere voedselbos-verhaal.</p>
      <p class="muted">Geen bezoeklocatie op mijn kaart; zie hun eigen site.</p>
    </article>
    <article class="soft-card">
      <h2><a href="https://meerbomen.nu/" rel="noopener noreferrer">MeerBomen Nu</a></h2>
      <p>Landelijk initiatief dat kansarme zaailingen redt en gratis tweede-kansbomen en -struiken uitdeelt via bomenhubs en vrijwilligers. Het verschil met mij: zij doen bomen en acties, ik stekjes van mens tot mens.</p>
      <p class="muted">Geen bezoeklocatie op mijn kaart.</p>
    </article>
  </div>

  <h2>Andere links</h2>
  <p class="section-intro">Losse verwijzingen naar verwante plekken en netwerken.</p>
  <div class="grid-cards partners-links">
    <article class="soft-card">
      <h3><a href="https://stekjesroute.com/" rel="noopener noreferrer">Stekjesroute</a></h3>
      <p>Verzamelplatform voor plantenruilkasten in Nederland en België. Bij die kasten ruil je stekjes en planten (niet gratis; anders een donatie volgens de kast).</p>
      <p class="muted">Daar ruilen; hier gratis met een praatje.</p>
    </article>
    <article class="soft-card">
      <h3><a href="https://velt.nu/zaden-en-plantenruilbeurzen" rel="noopener noreferrer">Velt</a></h3>
      <p>Velt-groepen organiseren zaden- en plantenruilbeurzen in Nederland en België: delen van zaden en planten, zonder dat er geld aan te pas hoeft te komen.</p>
    </article>
    <article class="soft-card">
      <h3><a href="https://zadenbibliotheeknederland.nl/" rel="noopener noreferrer">Zadenbibliotheek Nederland</a></h3>
      <p>Netwerk van zadenbibliotheken waar je zaden kosteloos kunt lenen, ruilen en inbrengen. Lokale collecties verschillen per locatie.</p>
      <p class="muted">Zaden; ik doe stekjes.</p>
    </article>
    <article class="soft-card">
      <h3><a href="https://voedseluithetbos.nl/" rel="noopener noreferrer">Voedsel uit het Bos</a></h3>
      <p>Vereniging gericht op opleidingen, kennisbank, podcast en community rond goed aangelegde en beheerde voedselbossen in Nederland.</p>
    </article>
    <article class="soft-card">
      <h3><a href="https://netwerkvoedselbosbouw.nl/" rel="noopener noreferrer">Netwerk Voedselbosbouw</a></h3>
      <p>Netwerk rond voedselbosprofessionals (boer, ontwerper, kweker, investeerder, beleid), voortgekomen uit de Green Deal Voedselbossen. Publiceert o.a. een voedselbossenkaart.</p>
    </article>
    <article class="soft-card">
      <h3><a href="https://www.permacultuurnederland.org/" rel="noopener noreferrer">Permacultuur Nederland</a></h3>
      <p>Website en kennisnetwerk over permacultuur in Nederland: eetbare tuinen, artikelen, cursusmateriaal en verwijzing naar de landelijke community.</p>
    </article>
    <article class="soft-card">
      <h3><a href="https://www.eetbaargoed.nl/" rel="noopener noreferrer">Eetbaargoed</a></h3>
      <p>Kwekerij van Arjan en Esther (Vaassen) met eetbare planten, fruitbomen en struiken voor voedselbossen en eetbare tuinen.</p>
    </article>
  </div>

  <p class="partners-kaart-hint">Bezoeklocaties (plantenasiel, plantenbieb, ruilkast, …) staan op <a href="{{ route('deelplekken') }}">deelplekken</a> en op de <a href="{{ route('aanbod') }}">aanbodkaart</a>. Meer over organisaties: <a href="{{ route('organisaties.index') }}">organisaties</a>. Zelf aanmelden: <a href="{{ route('voor-organisaties') }}">voor organisaties</a>.</p>
</section>
@endsection
