@extends('layouts.site')

{{-- Merknaam alleen in <title> (geen " · site" verdubbeling) --}}
@section('meta_description', $metaDescription)
@section('canonical', $canonical)
@section('body_class', 'page-home')

@section('content')
<section class="hero wrap">
  <h1 class="hero-brand">{{ config('site.name') }}</h1>
  <p class="hero-lead">Gratis planten en stekjes delen met mensen in de buurt. Je haalt ze alleen fysiek op; de enige ‘prijs’ is een praatje. Heel Nederland mag meedoen.</p>
</section>

<section class="section wrap" aria-labelledby="waarom-titel">
  <h2 id="waarom-titel">Waarom?</h2>
  <p class="section-intro">Drie dingen die dit leuk en zinvol maken — zonder grote beloftes.</p>
  <div class="waarom-grid">
    <article class="waarom-card">
      <h3>Mensen bij elkaar brengen</h3>
      <p>Stekjes zijn het excuus; het praatje bij de deur is het punt. Zo leer je buren en medetuiniers kennen rond iets kleins dat je deelt.</p>
    </article>
    <article class="waarom-card">
      <h3>Groen dichterbij huis</h3>
      <p>Meer planten in gewone tuinen, op balkons en in vensterbanken — biodiversiteit dichter bij waar je woont, zonder groot gebaar.</p>
    </article>
    <article class="waarom-card">
      <h3>Meedoen aan iets dat groeit</h3>
      <p>Zelf kweken en voedselbossen trekken steeds meer mensen. Dit is een eenvoudige manier om daarin mee te doen: stekken, weggeven, ophalen.</p>
    </article>
  </div>

  <div class="cta-panel" id="doe-mee">
    <h2>Doe mee</h2>
    <p>Kies wat bij je past. Stekjes blijven gratis — geen verkoop. Account, aanbod en kaart staan klaar; berichten volgen later.</p>
    <div class="cta-choices">
      <a class="cta-choice" href="{{ route('account.registreren', ['rol' => 'uitdelen']) }}">
        <strong>Ik deel planten uit</strong>
        <span>Account aanmaken (e-mailbevestiging verplicht).</span>
      </a>
      <a class="cta-choice" href="{{ route('account.registreren', ['rol' => 'ophalen']) }}">
        <strong>Ik wil ophalen</strong>
        <span>Account aanmaken en zoeken op de kaart.</span>
      </a>
      <div class="cta-choice cta-choice--soon">
        <strong>Ik help op een andere manier</strong>
        <span>Tips, foto’s, een regio trekken — helper zonder volledig account.</span>
        <span class="badge-soon">binnenkort</span>
      </div>
    </div>
  </div>
</section>

<section class="section wrap" id="hoe-werkt-het" aria-labelledby="hoe-titel">
  <h2 id="hoe-titel">Hoe werkt het</h2>
  <p class="section-intro">Drie spelregels: <strong>gratis</strong>, alleen <strong>fysiek ophalen</strong>, en de enige tegenprestatie is een <strong>praatje</strong>.</p>
  <ol class="list-plain">
    <li>Je maakt een account (of meldt je aan als helper).</li>
    <li>Uitdelers zetten op de site wat ze gratis weggeven, met een benaderde plek (postcode of plaats) — nooit een straatadres.</li>
    <li>Ophalers zoeken in de buurt en sturen een bericht via de site.</li>
    <li>Jullie spreken onderling een ophaalmoment af. Exacte adressen deel je zelf, als je dat wilt.</li>
    <li>Pas bij wederzijds akkoord zien jullie elkaars contactgegevens.</li>
  </ol>
  <p class="muted">Berichten: <em>binnenkort</em>. Account, aanbod en kaart staan klaar.</p>
</section>

@if($nuTeStekken && count($nuTeStekken['groepen']))
<section class="section wrap nu-te-stekken" aria-labelledby="nu-te-stekken-titel">
  <h2 id="nu-te-stekken-titel">Nu te stekken — {{ $nuTeStekken['maand_naam'] }}</h2>
  <p class="section-intro">{{ $nuTeStekken['intro'] }}</p>
  <div class="nu-te-stekken-grid">
    @foreach($nuTeStekken['groepen'] as $groep)
      <article class="soft-card">
        <h3>{{ $groep['titel'] ?? '' }}</h3>
        @if(!empty($groep['tip']))
          <p class="muted">{{ $groep['tip'] }}</p>
        @endif
        <ul>
          @foreach(($groep['planten'] ?? []) as $p)
            <li>{{ $p['naam'] ?? '' }}</li>
          @endforeach
        </ul>
      </article>
    @endforeach
  </div>
  <p class="muted">Uitgebreide stekpagina’s per soort: <em>binnenkort</em>.</p>
</section>
@endif

<section class="section wrap" id="veiligheid" aria-labelledby="veilig-titel">
  <h2 id="veilig-titel">Veilig delen</h2>
  <p class="section-intro">Niet alles hoort op een deelplatform. We vragen je om invasieve soorten in Nederland niet aan te bieden.</p>
  <div class="soft-card warn-card">
    <p>Sommige planten staan op de EU-Unielijst van invasieve uitheemse soorten (NVWA). Die horen hier niet tussen — ook niet “even een stekje voor de leuk”.</p>
    <p>De volledige waarschuwingslijst met bronnen komt mee in een volgende release. Twijfel je? Bied het dan niet aan.</p>
    <p><span class="badge-soon">Lijst binnenkort</span></p>
  </div>
</section>

<section class="section wrap" id="partners" aria-labelledby="partners-titel">
  <h2 id="partners-titel">Partners</h2>
  <p class="section-intro">Wij bouwen niet alles zelf. Twee initiatieven waar we naast willen staan — in onze eigen woorden, zonder beloftes die we nog niet hard kunnen maken.</p>
  <div class="stack partners-stack">
    <article class="soft-card">
      <h3><a href="https://voedselbosbouw.org/" rel="noopener noreferrer">Voedselbosbouw.org</a></h3>
      <p>Stichting Voedselbosbouw Nederland deelt kennis en praktijk rond voedselbossen. Wij richten ons op stekjes en planten van mens tot mens; zij op het bredere voedselbos-verhaal. Hoe we precies naar elkaar verwijzen of samenwerken: volgt later.</p>
      <p class="muted">Geen bezoeklocatie op onze kaart — zie hun eigen site.</p>
    </article>
    <article class="soft-card">
      <h3><a href="https://meerbomen.nu/" rel="noopener noreferrer">MeerBomen Nu</a></h3>
      <p>MeerBomen Nu zet zich in voor meer bomen. Het verschil met ons: zij doen bomen en acties, wij gratis stekjes en planten tussen buren. Eventuele concrete samenwerking volgt later.</p>
      <p class="muted">Geen bezoeklocatie op onze kaart.</p>
    </article>
  </div>
</section>

<section class="section wrap" aria-labelledby="verder-titel">
  <h2 id="verder-titel">Verder kijken</h2>
  <div class="grid-cards">
    <div class="soft-card">
      <h3>Aanbod &amp; kaart</h3>
      <p>Zoeken op postcode of plaats.</p>
      <p><a href="{{ route('aanbod') }}">Naar de kaart</a></p>
    </div>
    <div class="soft-card">
      <h3>Stektips</h3>
      <p>Praktische tips per soort.</p>
      <p><span class="badge-soon">binnenkort</span></p>
    </div>
    <div class="soft-card">
      <h3>Privacy</h3>
      <p>Wat we wel en niet bewaren.</p>
      <p><a href="{{ route('privacy') }}">Lees de verklaring</a></p>
    </div>
  </div>
</section>
@endsection
