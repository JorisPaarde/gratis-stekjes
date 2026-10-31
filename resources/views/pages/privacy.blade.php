@extends('layouts.site')

@section('title', 'Privacy')
@section('meta_description', $metaDescription)
@section('canonical', $canonical)

@section('content')
<section class="section wrap prose">
  <h1 class="page-title">Privacyverklaring</h1>

  <p>Deze verklaring beschrijft welke persoonsgegevens {{ config('site.name') }} (gratisplanten.nl) verwerkt, waarom, hoe lang, en welke rechten je hebt. Het werkgebied van de site is Nederland en België. Ik verwerk gegevens volgens de AVG, die in beide landen geldt. Woon je in België, dan lees je hieronder ook waar je terechtkunt met een klacht. Ik beschrijf hier alleen wat de site echt doet.</p>

  <h2>Verwerkingsverantwoordelijke</h2>
  <p>
    {{ config('site.name') }} is een project van <a href="https://jpwebcreation.nl" rel="noopener noreferrer">JP Web Creation</a>
    (eenmanszaak van Joris Paardekooper Overman).<br>
    KvK: 90397320<br>
    Btw: NL004813310B95<br>
    Contact: <a href="mailto:{{ config('site.contact_email') }}">{{ config('site.contact_email') }}</a>
  </p>
  <p>Ik ben gevestigd in Nederland. Daarom is de Nederlandse Autoriteit Persoonsgegevens mijn leidende toezichthouder. Woon je in België, dan mag je ook bij de Belgische Gegevensbeschermingsautoriteit terecht.</p>

  <h2>Welke gegevens ik verwerk</h2>
  <ul class="list-plain">
    <li><strong>Account:</strong> e-mailadres, wachtwoord (nooit leesbaar opgeslagen), voornaam of bijnaam, optioneel telefoonnummer (versleuteld), land (NL/BE), locatie bij benadering (zoals postcode of plaats, plus afgeronde coördinaten: een kaartpunt dat ik afrond, zodat het niet je precieze adres aanwijst). Bij een e-mailwijziging bewaar ik tijdelijk het nieuwe adres tot je het bevestigt.</li>
    <li><strong>Aanbod:</strong> type (uitdelen of ophalen), vrije tekst (plantensoort), zichtbaarheid, verloopdatum en een tijdelijke verlenglink in herinneringsmails. Optioneel tot drie foto’s die jij uploadt: ik controleer of het echt een afbeelding is (JPEG/PNG/WebP), verklein die opnieuw zonder locatiegegevens in het bestand, bewaar ze met willekeurige bestandsnamen, en toon ze bij je aanbod. Foto’s zijn openbaar zolang het aanbod zichtbaar is.</li>
    <li><strong>Ervaringen:</strong> korte tekst (maximaal ongeveer 500 tekens), optionele foto (zelfde veilige verwerking als aanbodfoto’s: JPEG/PNG/WebP, max. 8&nbsp;MB, opnieuw zonder locatiegegevens), optionele koppeling aan een soort of jouw aanbod, en een plaatsnaam op gemeenteniveau (nooit een straatadres). Nieuwe ervaringen wachten op mijn goedkeuring vóór ze publiek zichtbaar zijn.</li>
    <li><strong>Berichten:</strong> inhoud van gesprekken (versleuteld opgeslagen), of je verder contact wilt delen (ja/nee), of jullie een overdracht als afgerond markeren, leestijd, blokkeerregels, en meldingen met reden (versleuteld) voor de beheerder.</li>
    <li><strong>Mijlpalen:</strong> of ik je al een felicitatiemail voor je “eerste stekje” heb gestuurd (of heb geprobeerd te sturen), zonder ranglijst of punten. Die mail staat standaard uit.</li>
    <li><strong>Feedback:</strong> optionele tekst die je via de feedbackknop stuurt (versleuteld), plus welke pagina je bekeek. Je kunt de feedbackknop ook gebruiken om je aan te melden als verbinder, via de pagina Help mee.</li>
    <li><strong>Organisatie- of deelplek-aanmelding:</strong> naam, type, plaats, beschrijving, contact-e-mail (versleuteld, niet publiek) en tijdstip van privacy-akkoord. Pas na goedkeuring door de beheerder verschijnt de plek op de deelplekkenpagina en de stadspagina’s.</li>
    <li><strong>Technisch:</strong> sessie (login/“onthoud mij”), beveiliging van formulieren, korte limieten tegen misbruik (die tijdelijk een IP of e-mail kunnen bevatten), en tijdelijke opslag van zoeklocaties (zoekterm, label, afgeronde coördinaten, tijdstip). Serverlogs zonder herleidbare e-mail/IP. Geen trackingcookies, geen analytiek van derden, geen advertenties.</li>
  </ul>

  <h2>Wat staat er publiek</h2>
  <ul class="list-plain">
    <li>Voornaam of bijnaam</li>
    <li>Wat je uitdeelt of zoekt (vrije tekst) en eventuele foto’s bij dat aanbod</li>
    <li>Locatie bij benadering (postcode of plaats/wijk) en afgeronde kaartpunten. Nooit een straat of huisnummer via de site</li>
    <li>Goedgekeurde deelplekken/organisaties met de publieke info die daarvoor is goedgekeurd</li>
    <li>Goedgekeurde ervaringen: je voornaam/bijnaam, tekst, optionele foto, plaatsnaam (gemeente) en eventuele soortnaam</li>
  </ul>

  <h2>Wat blijft privé</h2>
  <ul class="list-plain">
    <li>E-mail: niet zichtbaar voor anderen, tenzij jullie in een gesprek beide “ja” kiezen voor verder contact. E-mail sla ik niet versleuteld op: het is je login en moet uniek opzoekbaar zijn.</li>
    <li>Optioneel telefoonnummer (zelfde zichtbaarheidsregel; wél versleuteld in de database)</li>
    <li>Exacte adressen en exacte coördinaten: die sla ik niet op in het gebruikersprofiel; exacte ophaaladressen deel je zelf buiten of in een bericht als je dat wilt</li>
    <li>Contact-e-mail bij organisatie-aanmeldingen (versleuteld)</li>
    <li>Berichtinhoud en meldingsredenen (versleuteld), wachtwoorden (nooit leesbaar opgeslagen), beheerwachtwoord-sessie</li>
  </ul>

  <h2>Doel en grondslag</h2>
  <ul class="list-plain">
    <li><strong>Doel:</strong> elkaar vinden om planten en stekjes gratis uit te delen of op te halen; berichten; organisatie-/deelplek-zichtbaarheid na controle; beveiliging tegen misbruik en spam; en mails die bij je account horen.</li>
    <li><strong>Grondslag:</strong> toestemming (vinkjes bij registratie/aanmelding + deze verklaring) en, waar nodig, gerechtvaardigd belang voor beveiliging en misbruikpreventie.</li>
  </ul>

  <h2>Jouw rechten</h2>
  <ul class="list-plain">
    <li><strong>Inzien en downloaden:</strong> ingelogd via <a href="{{ route('account.export') }}">Gegevens downloaden</a> (een bestand met account, aanbod, ervaringen, berichten, blokkades/meldingen en links naar jouw aanbodfoto’s; zonder wachtwoord of geheime links).</li>
    <li><strong>Wissen (ervaring):</strong> via <a href="{{ route('account.ervaringen') }}">Mijn ervaringen</a> kun je een ervaring (inclusief foto) zelf verwijderen.</li>
    <li><strong>Wissen (account):</strong> <a href="{{ route('account.verwijderen') }}">Account verwijderen</a> (bevestiging met wachtwoord). Daarmee verdwijnen account, aanbod, bijbehorende foto’s, ervaringen en foto’s, berichten/gesprekken, mijlpaalregistraties, blokkades, jouw meldingen, wachtwoordreset-links en sessies. Feedback blijft bewaard zonder koppeling aan jou (geanonimiseerd). Verwijder je een apart aanboditem, dan wis ik ook de foto’s van dat item.</li>
    <li><strong>Corrigeren:</strong> via je accountprofiel (naam, telefoon, locatie, e-mailwijziging met herbevestiging).</li>
    <li><strong>Bezwaar of klacht:</strong> mail <a href="mailto:{{ config('site.contact_email') }}">{{ config('site.contact_email') }}</a>. Kom je er met mij niet uit, dan kun je een klacht indienen bij een toezichthouder:
      <ul>
        <li>in Nederland bij de Autoriteit Persoonsgegevens (<a href="https://www.autoriteitpersoonsgegevens.nl/" rel="noopener noreferrer">autoriteitpersoonsgegevens.nl</a>);</li>
        <li>in België bij de Gegevensbeschermingsautoriteit (GBA), Drukpersstraat 35, 1000 Brussel, +32 2 274 48 00, <a href="mailto:contact@apd-gba.be">contact@apd-gba.be</a>, <a href="https://www.gegevensbeschermingsautoriteit.be/" rel="noopener noreferrer">gegevensbeschermingsautoriteit.be</a>.</li>
      </ul>
    </li>

  </ul>

  <h2>Hoe lang ik gegevens bewaar</h2>
  <ul class="list-plain">
    <li>Ik bewaar alleen wat nodig is voor bovenstaande doelen.</li>
    <li><strong>Aanbod:</strong> standaard zichtbaar tot {{ (int) config('stekjes.aanbod_ttl_days', 60) }} dagen (verlengbaar via herinneringsmail); verlopen aanbod verdwijnt uit zoeken/kaart. Foto’s bewaar ik zolang het aanbod (of jouw account) bestaat; bij wissen verdwijnen de bestanden mee.</li>
    <li><strong>Zoeklocaties:</strong> max. {{ (int) config('stekjes.geocode_cache_retention_days', 90) }} dagen; oudere gegevens ruim ik automatisch op.</li>
    <li><strong>Misbruiklimieten:</strong> korte bewaartermijn (uren tot dagen), geen aparte “IP-database”.</li>
    <li><strong>Sessies:</strong> standaard {{ (int) config('session.lifetime', 120) }} minuten; “onthoud mij” houdt je langer ingelogd.</li>
    <li><strong>Inactieve accounts:</strong> na {{ (int) config('stekjes.inactive_account_months', 12) }} maanden zonder activiteit. Automatisch opschonen volgt later, als dat nodig is.</li>
    <li><strong>Backups:</strong> worden versleuteld gemaakt en bewaard met een beperkte bewaartermijn; de sleutel staat niet in de openbare broncode.</li>
  </ul>

  <h2>Cookies (alleen functioneel)</h2>
  <ul class="list-plain">
    <li><strong>Sessiecookie:</strong> houdt je ingelogd tijdens je bezoek (en langer bij “onthoud mij”).</li>
    <li><strong>Beveiligingscookie:</strong> beschermt formulieren tegen vervalste verzoeken.</li>
    <li>Geen trackingcookies. Geen externe formulierdienst. Geen reclame of advertentienetwerken.</li>
  </ul>

  <h2>Mails en hosting</h2>
  <ul class="list-plain">
    <li>Bevestigingsmails, wachtwoordreset, e-mailwijziging, “je hebt een nieuw bericht” (zonder berichtinhoud of andermans adres), aanbod-herinneringen en, alleen als ik dat aanzet, een felicitatiemail bij je eerste stekje gaan via mijn eigen mailbox bij de hoster.</li>
    <li>Organisatie-aanmelders krijgen een bevestiging; ik krijg een melding. Geen mail naar derden buiten de aanmelder.</li>
    <li>Verbinders die zich aanmelden via de pagina Help mee: dat gaat via contact (jij mailt zelf) of via feedback (alleen opgeslagen, zonder automatische bevestigingsmail).</li>
    <li>Gegevens staan op hosting in de EU die ik beheer. Mail gaat via diezelfde hoster.</li>
  </ul>

  <h2>Kaart en derden</h2>
  <p>De aanbodkaart gebruikt OpenStreetMap-tegels. Plaatsen en postcodes zoek ik in Nederland op via de gratis PDOK Locatieserver. Voor België (en als je in beide landen zoekt) gebruik ik Nominatim, de zoekdienst van OpenStreetMap, alleen voor Nederland en België. De uitkomsten bewaar ik tijdelijk op mijn eigen server. Op de kaart staan alleen punten bij benadering. OpenStreetMap/PDOK/Nominatim ontvangen daarbij de gebruikelijke technische verzoeken van je browser of mijn server (geen accountgegevens van {{ config('site.name') }}).</p>

  <h2>Melden en blokkeren</h2>
  <p>Je kunt een gebruiker melden of blokkeren. Meldingen bewaar ik voor controle. Er is geen openbare wachtrij van meldingen.</p>

  <h2>Gebruik en recht</h2>
  <p>Gratis planten is een gratis dienst. Ik verkoop niets en neem geen betalingen aan; planten en stekjes geef je onderling weg. Op deze site is Nederlands recht van toepassing. Woon je in België, dan houd je de bescherming die het Belgische recht je dwingend geeft. Ben je het ergens niet mee eens, mail me dan eerst, dan zoek ik met je naar een oplossing.</p>

  <h2>Wijzigingen</h2>
  <p>Versie van deze site: {{ config('site.version') }}. Laatst bijgewerkt: 31 oktober 2026. Bij inhoudelijke wijzigingen werk ik deze pagina bij.</p>
</section>
@endsection
