@extends('layouts.site')

@section('title', $title)
@section('meta_description', $metaDescription)
@section('canonical', $canonical)
@section('body_class', 'page-home')

@push('head')
  @vite(['resources/css/nu-te-stekken.css'])
@endpush

@section('content')
<section class="hero" aria-labelledby="hero-titel">
  <div class="hero-media" aria-hidden="true">
    <picture>
      <source
        type="image/webp"
        srcset="
          {{ asset('assets/images/hero/hero-stekjes-640.webp') }} 640w,
          {{ asset('assets/images/hero/hero-stekjes-1280.webp') }} 1280w,
          {{ asset('assets/images/hero/hero-stekjes-1920.webp') }} 1920w
        "
        sizes="100vw"
      >
      <img
        src="{{ asset('assets/images/hero/hero-stekjes-1280.webp') }}"
        alt=""
        width="1280"
        height="853"
        decoding="async"
        fetchpriority="high"
      >
    </picture>
  </div>
  <div class="hero-scrim" aria-hidden="true"></div>
  <div class="hero-inner">
    <h1 id="hero-titel" class="hero-brand">{{ $heading }}</h1>
    <p class="hero-lead">Gratis planten en stekjes delen met mensen in de buurt. Je haalt ze alleen fysiek op; de enige ‘prijs’ is een praatje. Heel Nederland en België mogen meedoen.</p>
    <div class="hero-actions">
      <a class="btn" href="#doe-mee"><x-icon name="plant" :size="18" /> Doe mee</a>
      <a class="btn btn-on-dark" href="#hoe-werkt-het"><x-icon name="info" :size="18" /> Hoe werkt het</a>
    </div>
  </div>
</section>
<div class="hero-wave" aria-hidden="true">
  <img src="{{ asset('assets/illustrations/wave-divider.svg') }}" alt="" width="1440" height="80" decoding="async" loading="lazy">
</div>

<section class="section wrap" aria-labelledby="waarom-titel">
  <h2 id="waarom-titel">Waarom?</h2>
  <p class="section-intro">Drie dingen die dit leuk en zinvol maken.</p>
  <div class="waarom-grid">
    <article class="waarom-card">
      <h3>Mensen bij elkaar brengen</h3>
      <p>Een stekje is een mooi excuus voor een praatje bij de deur. Zo leer je buren en andere tuiniers kennen, rond iets kleins dat je deelt.</p>
    </article>
    <article class="waarom-card">
      <h3>Groen dichterbij huis</h3>
      <p>Meer planten in gewone tuinen, op balkons en in vensterbanken. Zo wordt het groener en levendiger waar je woont.</p>
    </article>
    <article class="waarom-card">
      <h3>Meedoen aan iets dat groeit</h3>
      <p>Steeds meer mensen kweken zelf planten of leggen een voedselbos aan. Hier doe je gemakkelijk mee: stekken, zaden verzamelen, weggeven of ophalen.</p>
    </article>
  </div>

  <div class="cta-panel" id="doe-mee">
    <h2>Doe mee</h2>
    <p>Met één account kun je iets aanbieden én iets ophalen. Alles blijft gratis, er wordt niets verkocht.</p>
    <div class="cta-choices">
      <a class="cta-choice" href="{{ route('account.registreren') }}">
        <strong>Account maken</strong>
        <span>Bevestig je e-mailadres. Daarna kun je planten, stekjes of zaden aanbieden en berichten sturen.</span>
      </a>
      <a class="cta-choice" href="{{ route('aanbod') }}">
        <strong>Bekijk het aanbod</strong>
        <span>Zoek in de buurt op de kaart. Kijken kan ook zonder account.</span>
      </a>
      <a class="cta-choice" href="{{ route('help-mee') }}">
        <strong>Ik wil op een andere manier helpen</strong>
        <span>Deel het op social media, tip een volkstuinpark of wijs me op een initiatief. Zo word je verbinder.</span>
      </a>
    </div>
  </div>
</section>

<section class="section section--mint" id="hoe-werkt-het" aria-labelledby="hoe-titel">
  <div class="wrap">
    <p class="section-figure" aria-hidden="true" style="color:var(--forest)"><x-icon name="stekje" :size="40" /></p>
    <h2 id="hoe-titel">Hoe werkt het</h2>
    <p class="section-intro">Drie spelregels: het is <strong>gratis</strong>, je haalt alles <strong>zelf op</strong>, en de enige tegenprestatie is een <strong>praatje</strong>.</p>
    <ol class="steps">
      <li><span>Je maakt één account en bevestigt je e-mailadres. Of je meldt je aan als <a href="{{ route('help-mee') }}">verbinder</a>.</span></li>
      <li><span>Met datzelfde account zet je op de site wat je gratis weggeeft. Je geeft een postcode of plaats op, nooit je straatadres.</span></li>
      <li><span>Je zoekt in de buurt en stuurt een bericht via de site. Je e-mailadres blijft verborgen.</span></li>
      <li><span>Jullie spreken samen een ophaalmoment af. Je adres deel je zelf, als je dat wilt.</span></li>
      <li><span>Alleen als jullie allebei ja zeggen, zien jullie elkaars contactgegevens.</span></li>
    </ol>
    <p class="muted" style="margin-top:1.25rem">Klaar om te beginnen? Maak een account en kijk wat er bij jou in de buurt is.</p>
  </div>
</section>

@if($nuTeStekken && count($nuTeStekken['groepen']))
  @include('partials.nu-te-stekken', ['nuTeStekken' => $nuTeStekken, 'headingId' => 'nu-te-stekken-titel'])
@endif

@include('partials.ervaringen-home')

<section class="section section--mint" id="veiligheid" aria-labelledby="veilig-titel">
  <div class="wrap">
    <h2 id="veilig-titel">Veilig delen</h2>
    <p class="section-intro">Niet alles is geschikt om te delen. Ik vraag je om invasieve soorten niet aan te bieden.</p>
    <div class="soft-card warn-card">
      <p><x-icon name="waarschuwing" :size="20" /> Sommige planten staan op de EU-Unielijst van invasieve uitheemse soorten (NVWA). Die horen hier niet tussen, ook niet als een klein stekje.</p>
      <p>Bij het plaatsen van aanbod controleer ik op deze soorten. Twijfel je? Bied het dan liever niet aan.</p>
      <p><a href="{{ route('invasieve-soorten') }}">Lijst en uitleg over invasieve soorten</a></p>
    </div>
  </div>
</section>

<section class="section wrap" id="initiatieven" aria-labelledby="initiatieven-titel">
  <h2 id="initiatieven-titel">Initiatieven</h2>
  <p class="section-intro">Een paar initiatieven die ik de moeite waard vind. Ik werk er niet officieel mee samen.</p>
  <div class="stack partners-stack">
    <article class="soft-card">
      <h3><a href="https://voedselbosbouw.org/" rel="noopener noreferrer">Voedselbosbouw.org</a></h3>
      <p>Stichting Voedselbosbouw Nederland deelt kennis over voedselbossen en hoe je ze aanlegt. Ik richt me op planten, stekjes en zaden van mens tot mens.</p>
      <p class="muted">Niet te bezoeken via mijn kaart. Kijk op hun eigen site.</p>
    </article>
    <article class="soft-card">
      <h3><a href="https://meerbomen.nu/" rel="noopener noreferrer">MeerBomen Nu</a></h3>
      <p>MeerBomen Nu zet zich in voor meer bomen. Zij richten zich op bomen en acties, ik op gratis stekjes en planten tussen buren.</p>
      <p class="muted">Niet te bezoeken via mijn kaart.</p>
    </article>
  </div>
  <p><a href="{{ route('partners') }}">Alle initiatieven en links</a> · <a href="{{ route('deelplekken') }}">deelplekken bij jou in de buurt</a> · of kijk op de <a href="{{ route('aanbod') }}">kaart</a>.</p>
</section>

<section class="section section--cream" aria-labelledby="verder-titel">
  <div class="wrap">
    <h2 id="verder-titel">Verder kijken</h2>
    <div class="grid-cards">
      <div class="soft-card">
        <h3>Aanbod &amp; kaart</h3>
        <p>Zoek planten, stekjes en zaden op postcode of plaats.</p>
        <p><a href="{{ route('aanbod') }}">Naar de kaart</a></p>
      </div>
      <div class="soft-card">
        <h3>Gratis planten in jouw stad</h3>
        <p>Een eigen pagina voor de grotere steden.</p>
        <p><a href="{{ route('gratis-planten.index') }}">Kies je stad</a></p>
      </div>
      <div class="soft-card">
        <h3>Deelplekken</h3>
        <p>Een plantenasiel, plantenbieb of ruilkast bij jou in de buurt.</p>
        <p><a href="{{ route('deelplekken') }}">Bekijk deelplekken</a></p>
      </div>
      <div class="soft-card">
        <h3>Stektips</h3>
        <p>Handige tips per plantensoort.</p>
        <p><a href="{{ route('stektips.index') }}">Bekijk stektips</a></p>
      </div>
      <div class="soft-card">
        <h3>Privacy</h3>
        <p>Wat ik wel en niet van je bewaar.</p>
        <p><a href="{{ route('privacy') }}">Lees de verklaring</a></p>
      </div>
    </div>
  </div>
</section>
@endsection
