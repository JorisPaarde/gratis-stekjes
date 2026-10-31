@extends('layouts.site')

@section('title', 'Privacy')
@section('meta_description', $metaDescription)
@section('canonical', $canonical)

@section('content')
<section class="section wrap prose">
  <h1 class="page-title">Privacyverklaring</h1>

  <p>Deze verklaring beschrijft welke persoonsgegevens {{ config('site.name') }} (gratisplanten.nl) verwerkt, waarom, hoe lang, en welke rechten je hebt. Het werkgebied van de site is Nederland en Vlaanderen (inclusief Brussel); ik voeg geen aparte Belgische juridische claims toe. Ik houd het dicht bij wat de applicatie echt doet (zie ook de technische inventaris in de broncode).</p>

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
    <li><strong>Account:</strong> e-mailadres, wachtwoord (alleen als hash, bcrypt/argon via Laravel), voornaam of bijnaam, optioneel telefoonnummer (versleuteld opgeslagen), land (NL/BE), benaderde locatie (label zoals postcode of plaats, plus afgeronde coördinaten). Optioneel een tijdelijk nieuw e-mailadres bij e-mailwijziging (<code>pending_email</code>) tot bevestiging.</li>
    <li><strong>Aanbod:</strong> type (uitdelen of ophalen), vrije tekst (plantensoort), zichtbaarheid, verloopdatum en (intern) verlengtoken bij herinneringsmails. Optioneel tot drie foto’s die jij uploadt: ik controleer of het echt een afbeelding is (JPEG/PNG/WebP), herschrijf die server-side (zonder EXIF/GPS), bewaar ze buiten uitvoerbare paden met willekeurige bestandsnamen, en toon ze bij je aanbod. Foto’s zijn openbaar zolang het aanbod zichtbaar is.</li>
    <li><strong>Ervaringen:</strong> korte tekst (max. ±500 tekens), optionele foto (zelfde veilige verwerking als aanbodfoto’s: JPEG/PNG/WebP, max. 8&nbsp;MB, finfo + decode, opnieuw zonder EXIF/GPS), optionele koppeling aan een soort of jouw aanbod, en een plaatsnaam op gemeenteniveau (nooit een straatadres). Nieuwe ervaringen wachten op mijn goedkeuring vóór ze publiek zichtbaar zijn.</li>
    <li><strong>Berichten:</strong> inhoud van gesprekken (versleuteld opgeslagen), of je verder contact wilt delen (ja/nee), of jullie een overdracht als afgerond markeren, leestijd, blokkeerregels, en meldingen met reden (versleuteld) voor de beheerder.</li>
    <li><strong>Mijlpalen:</strong> intern of ik je al een felicitatiemail voor je “eerste stekje” heb (geprobeerd te) sturen — zonder ranglijst of punten. Die mail staat standaard uit.</li>
    <li><strong>Feedback:</strong> optionele tekst die je via de feedbackknop stuurt (versleuteld), plus het pad van de pagina waarop je zat. Ook te gebruiken om je als verbinder te melden via /help-mee.</li>
    <li><strong>Organisatie- of deelplek-aanmelding:</strong> naam, type, plaats, beschrijving, contact-e-mail (versleuteld, niet publiek) en tijdstip van privacy-akkoord. Pas na goedkeuring door beheer verschijnt de plek op deelplekken/stadspagina’s.</li>
    <li><strong>Technisch:</strong> sessie (login/“onthoud mij”), CSRF-token, rate-limits (cache-keys die o.a. IP of e-mail kunnen bevatten, met korte TTL), geocode-cache van zoekopdrachten (genormaliseerde query, label, afgeronde lat/lon, tijdstip). Serverlogs zonder herleidbare e-mail/IP (maskering). Geen trackingcookies, geen analytiek-scripts van derden, geen advertenties.</li>
  </ul>

  <h2>Wat staat er publiek</h2>
  <ul class="list-plain">
    <li>Voornaam of bijnaam</li>
    <li>Wat je uitdeelt of zoekt (vrije tekst) en eventuele foto’s bij dat aanbod</li>
    <li>Benaderde locatie (postcode of plaats/wijk) en afgeronde kaartpunten — nooit een straat of huisnummer via de site</li>
    <li>Goedgekeurde deelplekken/organisaties met de publieke info die daarvoor is goedgekeurd</li>
    <li>Goedgekeurde ervaringen: je voornaam/bijnaam, tekst, optionele foto, plaatsnaam (gemeente) en eventuele soortnaam</li>
  </ul>

  <h2>Wat blijft privé</h2>
  <ul class="list-plain">
    <li>E-mail — niet zichtbaar voor anderen, tenzij jullie in een gesprek beide “ja” kiezen voor verder contact. E-mail sla ik niet versleuteld op: het is je login en moet uniek opzoekbaar zijn.</li>
    <li>Optioneel telefoonnummer (zelfde zichtbaarheidsregel; wél versleuteld in de database)</li>
    <li>Exacte adressen en exacte coördinaten: die sla ik niet op in het gebruikersprofiel; exacte ophaaladressen deel je zelf buiten of in een bericht als je dat wilt</li>
    <li>Contact-e-mail bij organisatie-aanmeldingen (versleuteld)</li>
    <li>Berichtinhoud en meldingsredenen (versleuteld), wachtwoorden (alleen hash), beheerwachtwoord-sessie</li>
  </ul>

  <h2>Doel en grondslag</h2>
  <ul class="list-plain">
    <li><strong>Doel:</strong> elkaar vinden om planten en stekjes gratis uit te delen of op te halen; berichten; organisatie-/deelplek-zichtbaarheid na controle; beveiliging (rate-limits, spamfilters) en transactionele mail.</li>
    <li><strong>Grondslag:</strong> toestemming (vinkjes bij registratie/aanmelding + deze verklaring) en, waar nodig, gerechtvaardigd belang voor beveiliging en misbruikpreventie.</li>
  </ul>

  <h2>Jouw rechten</h2>
  <ul class="list-plain">
    <li><strong>Inzien / export:</strong> ingelogd via <a href="{{ route('account.export') }}">Gegevens downloaden</a> (JSON van account, aanbod, ervaringen, berichten, blokkades/meldingen en links naar jouw aanbodfoto’s; zonder wachtwoord of tokens; foto’s van ervaringen als relatieve paden).</li>
    <li><strong>Wissen (ervaring):</strong> via <a href="{{ route('account.ervaringen') }}">Mijn ervaringen</a> kun je een ervaring (inclusief foto) zelf verwijderen.</li>
    <li><strong>Wissen (account):</strong> <a href="{{ route('account.verwijderen') }}">Account verwijderen</a> (bevestiging met wachtwoord). Daarmee verdwijnen account, aanbod, bijbehorende foto’s op de server, ervaringen en foto’s, berichten/gesprekken, mijlpaalregistraties, blokkades, jouw meldingen, wachtwoordreset-tokens en sessies. Feedback blijft bewaard zonder koppeling aan jou (geanonimiseerd). Verwijder je een apart aanboditem, dan wis ik ook de foto’s van dat item.</li>
    <li><strong>Rectificatie:</strong> via je accountprofiel (naam, telefoon, locatie, e-mailwijziging met herbevestiging).</li>
    <li><strong>Bezwaar / klacht:</strong> mail <a href="mailto:{{ config('site.contact_email') }}">{{ config('site.contact_email') }}</a>. Je kunt ook een klacht indienen bij de Autoriteit Persoonsgegevens (<a href="https://www.autoriteitpersoonsgegevens.nl/" rel="noopener noreferrer">autoriteitpersoonsgegevens.nl</a>).</li>

  </ul>

  <h2>Bewaren en dataminimalisatie</h2>
  <ul class="list-plain">
    <li>Ik bewaar alleen wat nodig is voor bovenstaande doelen.</li>
    <li><strong>Aanbod:</strong> standaard zichtbaar tot {{ (int) config('stekjes.aanbod_ttl_days', 60) }} dagen (verlengbaar via herinneringsmail); verlopen aanbod verdwijnt uit zoeken/kaart. Foto’s bewaar ik zolang het aanbod (of jouw account) bestaat; bij wissen verdwijnen de bestanden mee.</li>
    <li><strong>Geocode-cache:</strong> max. {{ (int) config('stekjes.geocode_cache_retention_days', 90) }} dagen; oudere rijen worden door de cron-job opgeschoond.</li>
    <li><strong>Rate-limits / cache:</strong> korte TTL (uren tot dagen), geen aparte “IP-database”.</li>
    <li><strong>Sessies:</strong> standaard lifetime {{ (int) config('session.lifetime', 120) }} minuten (configureerbaar); “onthoud mij” houdt je langer ingelogd via een remember-cookie.</li>
    <li><strong>Inactieve accounts:</strong> beleid {{ (int) config('stekjes.inactive_account_months', 12) }} maanden zonder activiteit — automatische purge volgt later indien gewenst.</li>
    <li><strong>Database-backups:</strong> worden versleuteld gemaakt (AES) en bewaard met een rolling retentie; de sleutel staat niet in de broncode. Zie documentatie in de repo (`docs/backups.md`).</li>
  </ul>

  <h2>Cookies (alleen functioneel)</h2>
  <ul class="list-plain">
    <li><strong>Sessiecookie</strong> — houdt je ingelogd tijdens je bezoek (en langer bij “onthoud mij” via remember-token).</li>
    <li><strong>XSRF/CSRF-cookie</strong> — beschermt formulieren tegen vervalste verzoeken.</li>
    <li>Geen trackingcookies. Geen externe formulierdienst. Geen reclame of ad-networks.</li>
  </ul>

  <h2>Mails en hosting</h2>
  <ul class="list-plain">
    <li>Bevestigingsmails, wachtwoordreset, e-mailwijziging, “je hebt een nieuw bericht” (zonder berichtinhoud of andermans adres), aanbod-herinneringen en — alleen als ik dat aanzet — een felicitatiemail bij je eerste stekje gaan via SMTP (eigen mailbox; typisch Hoasted).</li>
    <li>Organisatie-aanmelders krijgen een bevestiging; de beheerder een interne melding. Geen mail naar derden buiten de aanmelder.</li>
    <li>Verbinders die zich via /help-mee melden: via contact (jij mailt zelf) of via feedback (alleen opslag in de database, geen automatische bevestigingsmail).</li>
    <li>Gegevens staan op hosting in de EU die ik beheer. Mail verloopt via de geconfigureerde SMTP-provider.</li>
  </ul>

  <h2>Kaart en derden</h2>
  <p>De aanbodkaart gebruikt Leaflet met tegels van OpenStreetMap. Zoeklocaties in Nederland via de gratis PDOK Locatieserver; voor Vlaanderen/Brussel (en gemengd zoeken) via Nominatim (OpenStreetMap), beperkt tot landen NL en BE. Resultaten cache ik lokaal. Op de kaart alleen benaderde punten. OpenStreetMap/PDOK/Nominatim ontvangen daarbij de gebruikelijke technische verzoeken van je browser of mijn server (geen accountgegevens van {{ config('site.name') }}).</p>

  <h2>Melden en blokkeren</h2>
  <p>Je kunt een gebruiker melden of blokkeren. Meldingen worden opgeslagen voor de beheerder. Er is geen openbare moderatiewachtrij.</p>

  <h2>Wijzigingen</h2>
  <p>Versie van deze site: {{ config('site.version') }}. Datum van deze concepttekst: 31 oktober 2026. Bij inhoudelijke wijzigingen werk ik deze pagina bij.</p>
</section>
@endsection
