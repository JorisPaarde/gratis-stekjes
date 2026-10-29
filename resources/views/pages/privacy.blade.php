@extends('layouts.site')

@section('title', 'Privacy')
@section('meta_description', $metaDescription)
@section('canonical', $canonical)

@section('content')
<section class="section wrap prose">
  <h1 class="page-title">Privacyverklaring</h1>

  <p class="flash flash-warn" role="status"><strong>Concept – nog niet definitief.</strong> Joris leest deze tekst na vóór livegang.</p>

  <p>Deze verklaring beschrijft welke persoonsgegevens {{ config('site.name') }} (gratisplanten.nl) verwerkt, waarom, hoe lang, en welke rechten je hebt. Ik houd het dicht bij wat de applicatie echt doet.</p>

  <h2>Verwerkingsverantwoordelijke</h2>
  <p>
    {{ config('site.name') }} is een project van <a href="https://jpwebcreation.nl" rel="noopener noreferrer">JP Web Creation</a>
    (eenmanszaak van Joris Paardekooper Overman).<br>
    KvK: 90397320<br>
    Btw: NL004813310B95<br>
    Contact: <a href="mailto:{{ config('site.contact_email') }}">{{ config('site.contact_email') }}</a>
  </p>

  <h2>Welke gegevens ik verwerk</h2>
  <ul class="list-plain">
    <li><strong>Account:</strong> e-mailadres, wachtwoord (alleen als hash), voornaam of bijnaam, optioneel telefoonnummer, benaderde locatie (label zoals postcode-4 of plaats, plus afgeronde coördinaten). Optioneel een tijdelijk nieuw e-mailadres bij e-mailwijziging (<code>pending_email</code>) tot bevestiging.</li>
    <li><strong>Aanbod:</strong> type (uitdelen of ophalen), vrije tekst, zichtbaarheid, verloopdatum en (intern) verlengtoken bij herinneringsmails.</li>
    <li><strong>Berichten:</strong> inhoud van gesprekken tussen accounts, of je verder contact wilt delen (ja/nee), leestijd, en bijbehorende meldingen/blokkeerregels.</li>
    <li><strong>Organisatie- of deelplek-aanmelding:</strong> naam, type, plaats, beschrijving, contact-e-mail en tijdstip van privacy-akkoord. Contact-e-mail is niet publiek; pas na goedkeuring door beheer verschijnt de plek op deelplekken/stadspagina’s.</li>
    <li><strong>Meldingen:</strong> wie meldt wie, optioneel gekoppeld gesprek, reden, of de melding is afgehandeld (alleen voor de beheerder).</li>
    <li><strong>Helpers:</strong> gepland (naam, e-mail, “wat wil je doen?”, bevestiging), nog niet live op de site.</li>
    <li><strong>Technisch:</strong> sessie (login/“onthoud mij”), CSRF-token, rate-limits (cache-keys die o.a. IP of e-mail kunnen bevatten, met korte TTL), geocode-cache van zoekopdrachten (genormaliseerde query, label, afgeronde lat/lon, tijdstip). Geen trackingcookies, geen analytiek-scripts van derden, geen advertenties.</li>
  </ul>

  <h2>Wat staat er publiek</h2>
  <ul class="list-plain">
    <li>Voornaam of bijnaam</li>
    <li>Wat je uitdeelt of zoekt (vrije tekst)</li>
    <li>Benaderde locatie (postcode-4 of plaats/wijk) en afgeronde kaartpunten — nooit een straat of huisnummer via de site</li>
    <li>Goedgekeurde deelplekken/organisaties met de publieke info die daarvoor is goedgekeurd</li>
  </ul>

  <h2>Wat blijft privé</h2>
  <ul class="list-plain">
    <li>E-mail — niet zichtbaar voor anderen, tenzij jullie in een gesprek beide “ja” kiezen voor verder contact</li>
    <li>Optioneel telefoonnummer (zelfde regel)</li>
    <li>Exacte adressen en exacte coördinaten: die sla ik niet op in het gebruikersprofiel; exacte ophaaladressen deel je zelf buiten of in een bericht als je dat wilt</li>
    <li>Contact-e-mail bij organisatie-aanmeldingen</li>
    <li>Wachtwoorden (alleen hash), meldingen en beheerwachtwoord-sessie</li>
  </ul>

  <h2>Doel en grondslag</h2>
  <ul class="list-plain">
    <li><strong>Doel:</strong> elkaar vinden om planten en stekjes gratis uit te delen of op te halen; berichten en (later) helpers; organisatie-/deelplek-zichtbaarheid na controle; beveiliging (rate-limits, spamfilters) en transactionele mail.</li>
    <li><strong>Grondslag:</strong> toestemming (vinkjes bij registratie/aanmelding + deze verklaring) en, waar nodig, gerechtvaardigd belang voor beveiliging en misbruikpreventie.</li>
  </ul>

  <h2>Jouw rechten</h2>
  <ul class="list-plain">
    <li><strong>Inzien / export:</strong> ingelogd via <a href="{{ route('account.export') }}">Gegevens downloaden</a> (JSON, zonder wachtwoord of tokens).</li>
    <li><strong>Wissen (account):</strong> <a href="{{ route('account.verwijderen') }}">Account verwijderen</a> (bevestiging met wachtwoord). Daarmee verdwijnen account, aanbod, berichten/gesprekken, blokkades en jouw meldingen.</li>
    <li><strong>Wissen (helper zonder account):</strong> volgt wanneer helpers live gaan.</li>
    <li><strong>Rectificatie:</strong> via je accountprofiel (naam, telefoon, locatie, e-mailwijziging met herbevestiging).</li>
    <li><strong>Bezwaar / klacht:</strong> mail <a href="mailto:{{ config('site.contact_email') }}">{{ config('site.contact_email') }}</a>. Je kunt ook een klacht indienen bij de Autoriteit Persoonsgegevens (<a href="https://www.autoriteitpersoonsgegevens.nl/" rel="noopener noreferrer">autoriteitpersoonsgegevens.nl</a>).</li>

  </ul>

  <h2>Bewaren en dataminimalisatie</h2>
  <ul class="list-plain">
    <li>Ik bewaar alleen wat nodig is voor bovenstaande doelen.</li>
    <li><strong>Aanbod:</strong> standaard zichtbaar tot {{ (int) config('stekjes.aanbod_ttl_days', 60) }} dagen (verlengbaar via herinneringsmail); verlopen aanbod verdwijnt uit zoeken/kaart.</li>
    <li><strong>Geocode-cache:</strong> max. {{ (int) config('stekjes.geocode_cache_retention_days', 90) }} dagen; oudere rijen worden door de cron-job opgeschoond.</li>
    <li><strong>Rate-limits / cache:</strong> korte TTL (uren tot dagen), geen aparte “IP-database”.</li>
    <li><strong>Sessies:</strong> standaard lifetime {{ (int) config('session.lifetime', 120) }} minuten (configureerbaar); “onthoud mij” houdt je langer ingelogd via een remember-cookie.</li>
    <li><strong>Inactieve accounts:</strong> beleid {{ (int) config('stekjes.inactive_account_months', 12) }} maanden zonder activiteit — automatische purge volgt later indien gewenst.</li>
    <li>Geen trackingcookies. Geen externe formulierdienst. Geen reclame of ad-networks.</li>
  </ul>

  <h2>Mails en hosting</h2>
  <ul class="list-plain">
    <li>Bevestigingsmails, wachtwoordreset, e-mailwijziging, “je hebt een nieuw bericht” (zonder berichtinhoud of andermans adres) en aanbod-herinneringen gaan via SMTP (eigen mailbox; typisch Hoasted).</li>
    <li>Organisatie-aanmelders krijgen een bevestiging; de beheerder een interne melding. Geen mail naar derden buiten de aanmelder.</li>
    <li>Gegevens staan op hosting in de EU die ik beheer. Mail verloopt via de geconfigureerde SMTP-provider.</li>
  </ul>

  <h2>Kaart en derden</h2>
  <p>De aanbodkaart gebruikt Leaflet met tegels van OpenStreetMap. Zoeklocaties via de gratis PDOK Locatieserver; resultaten cache ik lokaal. Op de kaart alleen benaderde punten. OpenStreetMap/PDOK ontvangen daarbij de gebruikelijke technische verzoeken van je browser of mijn server (geen accountgegevens van {{ config('site.name') }}).</p>

  <h2>Melden en blokkeren</h2>
  <p>Je kunt een gebruiker melden of blokkeren. Meldingen worden opgeslagen voor de beheerder. Er is geen openbare moderatiewachtrij.</p>

  <h2>Wijzigingen</h2>
  <p>Versie van deze site: {{ config('site.version') }}. Datum van deze concepttekst: 29 oktober 2026. Bij inhoudelijke wijzigingen werk ik deze pagina bij.</p>
</section>
@endsection
