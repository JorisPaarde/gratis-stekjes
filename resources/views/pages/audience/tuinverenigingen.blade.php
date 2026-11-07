@extends('layouts.site')

@section('title', $title)
@section('meta_description', $metaDescription)
@section('canonical', $canonical)

@section('content')
@php
  $heroBase = 'assets/images/tuinverenigingen/dreef';
  $heroWidths = [640, 960, 1280, 1920];
  $heroSrcset = collect($heroWidths)
      ->map(fn (int $w) => asset($heroBase.'-'.$w.'.webp').' '.$w.'w')
      ->implode(', ');
@endphp
<x-audience-page
  eyebrow="Voor tuinverenigingen"
  heading="Geef plantmateriaal door met jullie tuinvereniging"
  intro="Een openbare pagina met het aanbod van jullie leden. Leden plaatsen zelf; het bestuur deelt de link."
  :current="$currentSeries"
  :series="$series"
>
  <p>Laat een bestuurslid of ander lid de vereniging toevoegen met een gratis persoonlijk account. Je koppelt hiermee ook jezelf als lid. Heb je al een account? Log dan in.</p>
  <p>Bij een nieuw account bevestig je eerst je e-mailadres.</p>

  <div class="audience-cta audience-cta--inline">
    <div class="audience-cta-actions">
      <a class="btn" href="{{ route('account.verenigingen') }}">Voeg jullie vereniging toe</a>
    </div>
    <p class="muted audience-cta-links">
      <a href="{{ route('account.registreren', ['next' => $intended]) }}">Maak een eigen account</a>
      ·
      <a href="{{ route('account.inloggen', ['next' => $intended]) }}">Inloggen</a>
    </p>
  </div>

  <figure class="audience-hero">
    <picture>
      <source
        type="image/webp"
        srcset="{{ $heroSrcset }}"
        sizes="(max-width: 390px) 100vw, (max-width: 880px) 92vw, 44rem"
      >
      <img
        src="{{ asset($heroBase.'-1280.jpg') }}"
        width="1280"
        height="853"
        alt="Volkstuinen bij de Dreef in Enkhuizen: tuintjes langs het water met kassen en bomen"
        decoding="async"
        loading="eager"
        fetchpriority="high"
      >
    </picture>
    <figcaption class="audience-hero-credit">
      Foto:
      <a href="https://commons.wikimedia.org/wiki/File:20180907_Volkstuin_bij_de_Dreef_Enkhuizen.jpg" rel="noopener noreferrer">Gouwenaar / Wikimedia Commons</a>,
      <a href="https://creativecommons.org/licenses/by-sa/4.0/" rel="noopener noreferrer license">CC BY-SA 4.0</a>
    </figcaption>
  </figure>

  <p>Op de tuin geven jullie al plantjes aan elkaar door. Via {{ config('site.name') }} kunnen leden ook zien wat er over is als ze er zelf even niet zijn: planten, stekjes en zaden die bij een ander verder kunnen groeien.</p>
  <p>Jullie vereniging kan een eigen openbare pagina krijgen met het aanbod van leden. Het bestuur deelt de pagina; leden zetten zelf hun aanbod erop en spreken het ophalen met elkaar af. Een apart bestuursaccount is niet nodig.</p>

  <h2>Zo doen jullie mee</h2>
  <ol class="list-plain audience-steps">
    <li>
      <strong>Maak gratis een persoonlijk account of log in</strong>
      Gebruik het account van een bestuurslid of ander lid dat zelf lid is van de vereniging. Nog geen account: maak er een, bevestig je e-mailadres en log in. Daarna ga je door naar Mijn verenigingen. Een apart bestuursaccount is niet nodig.
    </li>
    <li>
      <strong>Voeg jullie vereniging toe</strong>
      Open Mijn verenigingen. Begin de naam te typen en kies jullie vereniging met de juiste plaats. Staat ze er niet bij? Vul de naam, plaats en soort vereniging in en kies Toevoegen. Een nieuwe vereniging wacht op controle voordat ze op de site komt; daarvoor beloof ik geen termijn. Je koppelt hiermee ook jezelf als lid; je lidmaatschap wordt niet vanzelf openbaar. Toevoegen geeft geen bestuursrechten.
    </li>
    <li>
      <strong>Deel de link met de leden</strong>
      Zodra de vereniging is goedgekeurd, vind je de link bij Mijn verenigingen. Deel die bijvoorbeeld in jullie nieuwsbrief of een bericht aan de leden. Zo hebben jullie één plek om het aanbod terug te vinden, zonder dat het bestuur alle aanbiedingen hoeft te verzamelen.
    </li>
  </ol>

  <h2>Openbaar delen, zelf kiezen</h2>
  <p>De verenigingspagina en het aanbod zijn openbaar, niet alleen voor leden. Leden geven zelf aan bij welke vereniging ze horen en kiezen of dat bij hun aanbod zichtbaar mag zijn. Wil een lid het aanbod ook op jullie verenigingspagina laten zien? Dan zet het zelf <strong>‘Toon bij mijn aanbod dat ik hier lid van ben’</strong> aan. Zonder dat vinkje verschijnt hun aanbod niet op de verenigingspagina. Het aantal gekoppelde leden kan wel worden getoond.</p>
  <p>Ieder lid gebruikt een eigen account en kan meerdere verenigingen koppelen (maximaal vijf). Alles wat via {{ config('site.name') }} wordt aangeboden is gratis. Leden spreken zelf af waar en wanneer ze het ophalen.</p>

  <x-slot:cta>
    <div class="audience-cta-actions">
      <a class="btn" href="{{ route('account.verenigingen') }}">Voeg jullie vereniging toe</a>
    </div>
    <p class="muted">Ben je lid en wil je zelf iets delen of vinden? <a href="{{ route('volkstuinen') }}">Lees hoe het werkt voor volkstuinders</a>.</p>
  </x-slot:cta>
</x-audience-page>
@endsection
