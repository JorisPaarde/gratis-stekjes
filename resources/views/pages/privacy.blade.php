@extends('layouts.site')

@section('title', 'Privacy')
@section('meta_description', $metaDescription)
@section('canonical', $canonical)

@section('content')
<section class="section wrap prose">
  <h1 class="page-title">Privacyverklaring</h1>
  <p>Kort en duidelijk. Waar nog iets formeels moet worden ingevuld, staat <em>Volgt binnenkort</em>.</p>

  <h2>Verantwoordelijke</h2>
  <p>Volgt binnenkort (naam, adres, contact-e-mail).</p>

  <h2>Welke gegevens</h2>
  <ul class="list-plain">
    <li><strong>Account:</strong> e-mail, wachtwoordhash, voornaam/bijnaam, optioneel telefoon, benaderde locatie (label + afgeronde coördinaten).</li>
    <li><strong>Aanbod:</strong> type (uitdelen/ophalen) en vrije tekst.</li>
    <li><strong>Berichten:</strong> inhoud van gesprekken tussen accounts.</li>
    <li><strong>Helpers:</strong> naam, e-mail, vrije tekst “wat wil je doen?”, bevestigingsstatus.</li>
    <li><strong>Technisch:</strong> gehashte IP’s voor rate-limits (geen IP in plaintext), geocode-cache van zoekopdrachten.</li>
  </ul>

  <h2>Wat staat er publiek</h2>
  <ul class="list-plain">
    <li>Voornaam of bijnaam</li>
    <li>Wat je uitdeelt of zoekt (vrije tekst)</li>
    <li>Benaderde locatie (postcode-4 of plaats/wijk), nooit een straat of huisnummer</li>
  </ul>

  <h2>Wat blijft privé</h2>
  <ul class="list-plain">
    <li>E-mail — niet zichtbaar voor anderen, tenzij jullie in een gesprek beide “ja” kiezen voor verder contact</li>
    <li>Optioneel telefoonnummer (zelfde regel)</li>
    <li>Exacte adressen en exacte coördinaten: die slaan we niet op</li>
  </ul>

  <h2>Doel en grondslag</h2>
  <ul class="list-plain">
    <li>Doel: elkaar vinden om planten gratis uit te delen/op te halen, en (voor helpers) meedenken.</li>
    <li>Grondslag: toestemming (vinkje bij aanmelden + deze verklaring).</li>
  </ul>

  <h2>Jouw rechten</h2>
  <ul class="list-plain">
    <li><strong>Inzien / export:</strong> ingelogd via <a href="{{ route('account.export') }}">Gegevens downloaden</a> (JSON).</li>
    <li><strong>Wissen (account):</strong> <a href="{{ route('account.verwijderen') }}">Account verwijderen</a>.</li>
    <li><strong>Wissen (helper zonder account):</strong> volgt wanneer helpers live gaan.</li>
    <li>Vragen of klachten: Volgt binnenkort (contact). Je kunt ook naar de Autoriteit Persoonsgegevens.</li>
  </ul>

  <h2>Bewaren en dataminimalisatie</h2>
  <ul class="list-plain">
    <li>We bewaren alleen wat nodig is voor bovenstaande doelen.</li>
    <li>Rate-limits: Laravel cache-keys met korte TTL (geen plaintext-IP’s in de database).</li>
    <li>Geocode-cache: standaard max. {{ (int) config('stekjes.geocode_cache_retention_days', 90) }} dagen (configureerbaar; purge via cron wanneer actief).</li>
    <li>Inactieve accounts: beleid {{ (int) config('stekjes.inactive_account_months', 12) }} maanden zonder activiteit — automatische purge volgt later indien gewenst.</li>
    <li>Geen trackingcookies. Geen externe formulierdienst.</li>
  </ul>

  <h2>Mails en hosting</h2>
  <ul class="list-plain">
    <li>Bevestigingsmails, wachtwoordreset en “je hebt een nieuw bericht” (zonder berichtinhoud of andermans adres) gaan via SMTP uit de config (eigen mailbox).</li>
    <li>Gegevens staan op eigen hosting in de EU.</li>
  </ul>

  <h2>Kaart en derden</h2>
  <p>De aanbodkaart gebruikt Leaflet met tegels van OpenStreetMap. Zoeklocaties via de gratis PDOK Locatieserver; resultaten cachen we lokaal. Op de kaart alleen benaderde punten.</p>

  <h2>Melden en blokkeren</h2>
  <p>Je kunt een gebruiker melden of blokkeren. Meldingen worden opgeslagen voor de beheerder. Geen openbare moderatiewachtrij.</p>

  <h2>Wijzigingen</h2>
  <p>Versie {{ config('site.version') }}. Latere wijzigingen: Volgt binnenkort (datum).</p>
</section>
@endsection
