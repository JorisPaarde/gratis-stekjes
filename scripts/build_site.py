#!/usr/bin/env python3
"""Bouw statische pagina's en stekpagina's uit build-config + soorten JSON. Versie 0.1.0
SITE_NAME / BASE_URL via env of build-config — geen productnaam hardcoden.
"""

from __future__ import annotations

import json
import os
import sys
from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]
CONFIG = json.loads((ROOT / "build-config.json").read_text(encoding="utf-8"))
SITE = (os.environ.get("SITE_NAME") or CONFIG.get("site_name") or "").strip()
URL = (os.environ.get("BASE_URL") or CONFIG.get("site_url") or "http://127.0.0.1").rstrip("/")
BASE = CONFIG.get("base_path") or ""
VER = CONFIG.get("version", "0.1.0")
DESC = CONFIG.get("description") or "Planten gratis delen en ophalen. De enige tegenprestatie is een praatje."
if not SITE:
    # CI zonder secret: neutrale placeholder (geen productnaam)
    if os.environ.get("CI") or os.environ.get("GITHUB_ACTIONS"):
        SITE = "CI build"
    else:
        print("SITE_NAME ontbreekt (env of build-config.json).", file=sys.stderr)
        sys.exit(1)


def path(p: str) -> str:
    if not p.startswith("/"):
        p = "/" + p
    return f"{BASE}{p}"


def abs_url(p: str) -> str:
    return URL + path(p)


def e(s: str) -> str:
    return (
        s.replace("&", "&amp;")
        .replace("<", "&lt;")
        .replace(">", "&gt;")
        .replace('"', "&quot;")
    )


def layout(title: str, body: str, canonical: str = "/", description: str | None = None, body_class: str = "") -> str:
    desc = description or DESC
    full_title = f"{title} · {SITE}" if title != SITE else SITE
    return f"""<!DOCTYPE html>
<html lang="nl">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>{e(full_title)}</title>
  <meta name="description" content="{e(desc)}">
  <meta name="robots" content="noindex, nofollow">
  <link rel="canonical" href="{e(abs_url(canonical))}">
  <link rel="stylesheet" href="{e(path('/assets/css/styles.css'))}?v={e(VER)}">
  <link rel="manifest" href="{e(path('/manifest.webmanifest'))}">
  <meta name="theme-color" content="#2f6b45">
  <link rel="icon" href="{e(path('/assets/icons/icon-192.png'))}" sizes="192x192">
  <script src="{e(path('/assets/js/site-config.js'))}"></script>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Fraunces:opsz,wght@9..144,500;9..144,700&family=Figtree:wght@400;560;700&display=swap" rel="stylesheet">
</head>
<body class="{e(body_class)}">
  <a class="skip-link" href="#inhoud">Naar inhoud</a>
  <header class="site-header">
    <div class="wrap header-inner">
      <a class="brand" href="{e(path('/'))}">{e(SITE)}</a>
      <button type="button" class="nav-toggle" aria-expanded="false" aria-controls="hoofdmenu" data-nav-toggle>Menu</button>
      <nav id="hoofdmenu" class="site-nav" data-nav>
        <a href="{e(path('/hoe-werkt-het/'))}">Hoe werkt het</a>
        <a href="{e(path('/aanbod/'))}">Aanbod</a>
        <a href="{e(path('/stektips/'))}">Stektips</a>
        <a href="{e(path('/hulpmiddelen/'))}">Hulpmiddelen</a>
        <a href="{e(path('/links/'))}">Partners</a>
        <a href="{e(path('/over/'))}">Over</a>
        <a class="nav-cta" href="{e(path('/doe-mee/'))}">Doe mee</a>
        <a href="{e(path('/account/inloggen.php'))}">Inloggen</a>
      </nav>
    </div>
  </header>
  <main id="inhoud">
{body}
  </main>
  <footer class="site-footer">
    <div class="wrap footer-inner">
      <p><strong>{e(SITE)}</strong> — planten delen, een praatje als enige prijs.</p>
      <p class="footer-links">
        <a href="{e(path('/privacy/'))}">Privacy</a>
        <a href="{e(path('/over/'))}">Over</a>
        <a href="{e(path('/doe-mee/'))}">Doe mee</a>
      </p>
      <p class="footer-meta">Versie {e(VER)} · noindex</p>
    </div>
  </footer>
  <script src="{e(path('/assets/js/main.js'))}?v={e(VER)}" defer></script>
</body>
</html>
"""


def write(rel: str, html: str) -> None:
    out = ROOT / rel
    out.parent.mkdir(parents=True, exist_ok=True)
    out.write_text(html, encoding="utf-8")
    print("wrote", rel)


# ---------- pagina's ----------

home = layout(
    SITE,
    f"""
    <section class="hero wrap">
      <h1 class="hero-brand">{e(SITE)}</h1>
      <p class="hero-lead">Mensen stekken hun eetbare planten en geven de stekjes gratis weg. Je haalt ze alleen fysiek op, en de enige ‘prijs’ is een praatje. Heel Nederland mag meedoen.</p>
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
          <p>Meer eetbare planten in gewone tuinen helpt de natuur een handje — biodiversiteit en voedsel dichter bij waar je woont, zonder groot gebaar.</p>
        </article>
        <article class="waarom-card">
          <h3>Meedoen aan iets dat groeit</h3>
          <p>Zelf kweken en voedselbossen trekken steeds meer mensen. Dit is een eenvoudige manier om daarin mee te doen: stekken, weggeven, ophalen.</p>
        </article>
      </div>
      <div class="cta-panel" id="doe-mee">
        <h2>Doe mee</h2>
        <p>Kies wat bij je past. Registreren is gratis. Stekjes blijven gratis — geen verkoop.</p>
        <div class="cta-choices">
          <a class="cta-choice" href="{e(path('/account/registreren.php?rol=uitdelen'))}">
            <strong>Ik deel stekjes uit</strong>
            <span>Maak een account en zet wat je hebt op de kaart.</span>
          </a>
          <a class="cta-choice" href="{e(path('/account/registreren.php?rol=ophalen'))}">
            <strong>Ik wil ophalen</strong>
            <span>Zoek in de buurt en stuur een bericht via de site.</span>
          </a>
          <a class="cta-choice" href="{e(path('/doe-mee/#helpen'))}">
            <strong>Ik help op een andere manier</strong>
            <span>Tips, foto’s, een regio trekken — kort aanmelden als helper.</span>
          </a>
        </div>
        <p class="teller" data-meedoen-teller hidden></p>
      </div>
    </section>
    <section class="section wrap">
      <h2>Verder kijken</h2>
      <div class="grid-cards">
        <a class="soft-card" href="{e(path('/hoe-werkt-het/'))}"><h3>Hoe werkt het</h3><p>Spelregels in het kort.</p></a>
        <a class="soft-card" href="{e(path('/aanbod/'))}"><h3>Aanbod</h3><p>Zoek op postcode of plaats.</p></a>
        <a class="soft-card" href="{e(path('/stektips/'))}"><h3>Stektips</h3><p>Praktische tips per soort.</p></a>
      </div>
    </section>
    """,
    canonical="/",
    body_class="page-home",
)
write("index.html", home)

hoe = layout(
    "Hoe werkt het",
    f"""
    <section class="section wrap prose">
      <h1>Hoe werkt het</h1>
      <p>Drie spelregels: <strong>gratis</strong>, alleen <strong>fysiek ophalen</strong>, en de enige tegenprestatie is een <strong>praatje</strong>.</p>
      <ol class="list-plain">
        <li>Je maakt een account (of meldt je aan als helper).</li>
        <li>Uitdelers zetten op de site wat ze gratis weggeven, met een benaderde plek (postcode of plaats).</li>
        <li>Ophalers zoeken in de buurt en sturen een bericht via de site. E-mailadressen blijven verborgen.</li>
        <li>Jullie spreken onderling een ophaalmoment af. Exacte adressen deel je zelf, als je dat wilt.</li>
        <li>In elk gesprek kies je of je verder contact wilt. Alleen bij wederzijds ja zien jullie elkaars contactgegevens.</li>
      </ol>
      <p><a class="btn" href="{e(path('/doe-mee/'))}">Doe mee</a></p>
    </section>
    """,
    canonical="/hoe-werkt-het/",
)
write("hoe-werkt-het/index.html", hoe)

doe = layout(
    "Doe mee",
    f"""
    <section class="section wrap">
      <h1>Doe mee</h1>
      <p class="section-intro">Wil je stekjes uitdelen of ophalen? Maak een account. Wil je op een andere manier helpen? Dat kan zonder volledig account.</p>
      <div class="cta-choices" style="margin-bottom:2rem">
        <a class="cta-choice" style="background:var(--card);color:var(--ink);border-color:var(--line)" href="{e(path('/account/registreren.php?rol=uitdelen'))}">
          <strong>Ik deel stekjes uit</strong>
          <span>Account + aanbod op de kaart.</span>
        </a>
        <a class="cta-choice" style="background:var(--card);color:var(--ink);border-color:var(--line)" href="{e(path('/account/registreren.php?rol=ophalen'))}">
          <strong>Ik wil ophalen</strong>
          <span>Account + zoeken en berichten.</span>
        </a>
      </div>
      <div id="helpen" class="stack">
        <h2>Ik help op een andere manier</h2>
        <p class="muted">Geen account nodig. Na bevestiging via e-mail komt je aanmelding bij Joris in de helperslijst.</p>
        <form class="form" method="post" action="{e(path('/helpers/aanmelden.php'))}">
          <p class="hint">Velden met * zijn verplicht.</p>
          <label>Naam of bijnaam *
            <input name="naam" required maxlength="80" autocomplete="nickname">
          </label>
          <label>E-mail *
            <input type="email" name="email" required maxlength="254" autocomplete="email">
          </label>
          <label>Wat wil je doen? *
            <textarea name="wat" required maxlength="2000" placeholder="Bijv. tips schrijven, foto’s maken, een regio trekken…"></textarea>
          </label>
          <label class="checkbox">
            <input type="checkbox" name="privacy" value="1" required>
            <span>Ik ga akkoord met de <a href="{e(path('/privacy/'))}">privacyverklaring</a>.</span>
          </label>
          <!-- CSRF + honeypot + timing worden server-side gezet via helpers/aanmelden GET redirect of partial; zie PHP -->
          <p class="muted">Dit formulier werkt via de server. Open <a href="{e(path('/helpers/aanmelden.php'))}">het helperformulier</a> om je aan te melden (met beveiligingsvelden).</p>
          <a class="btn" href="{e(path('/helpers/aanmelden.php'))}">Naar helperformulier</a>
        </form>
      </div>
    </section>
    """,
    canonical="/doe-mee/",
)
write("doe-mee/index.html", doe)

aanbod = layout(
    "Aanbod",
    f"""
    <section class="section wrap">
      <h1>Aanbod</h1>
      <p class="section-intro">Zoek op postcode (4 cijfers) of plaats in Nederland. Je ziet benaderde plekken op de kaart en een lijst gesorteerd op afstand. Exacte adressen staan niet op de site.</p>
      <form class="form" id="zoek-form" style="max-width:100%;margin-bottom:1rem">
        <div style="display:grid;gap:.75rem;grid-template-columns:1fr;align-items:end">
          <label>Postcode of plaats
            <input type="text" name="q" id="zoek-q" maxlength="120" placeholder="Bijv. 2011 of Haarlem" required>
          </label>
          <label>Aanbodtype
            <select name="type" id="zoek-type">
              <option value="">Alles</option>
              <option value="uitdelen">Uitdelen</option>
              <option value="ophalen">Ophalen / zoeken</option>
            </select>
          </label>
          <button class="btn" type="submit">Zoeken</button>
        </div>
      </form>
      <fieldset class="marker-filters" id="marker-filters">
        <legend>Toon op kaart en in lijst</legend>
        <label class="check"><input type="checkbox" data-aanbod checked> Aanbod</label>
        <label class="check"><input type="checkbox" data-partner-type="partner" checked> Partner</label>
        <label class="check"><input type="checkbox" data-partner-type="voedselbos" checked> Voedselbos</label>
        <label class="check"><input type="checkbox" data-partner-type="plantenasiel" checked> Plantenasiel</label>
        <label class="check"><input type="checkbox" data-partner-type="plantenbieb" checked> Plantenbieb</label>
        <label class="check"><input type="checkbox" data-partner-type="ruilkast" checked> Ruilkast</label>
      </fieldset>
      <div class="search-layout">
        <div id="kaart" role="img" aria-label="Kaart met benaderde ophaalplekken en partnermarkers"></div>
        <div>
          <h2 class="visually-hidden" style="position:absolute;left:-9999px">Resultaten</h2>
          <ul class="zoek-lijst" id="zoek-lijst"></ul>
          <p class="muted" id="zoek-status">Plekken (voedselbos e.d.) laden automatisch. Vul een postcode of plaats in om aanbod te zoeken.</p>
        </div>
      </div>
      <p class="muted" style="margin-top:1rem">Kaarttegels: © OpenStreetMap-bijdragers. Locaties via PDOK Locatieserver (benaderd).</p>
    </section>
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" integrity="sha256-p4NxAoJBhIIN+hmNHrzRCf9tD/miZyoHS5obTRR9BMY=" crossorigin="">
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js" integrity="sha256-20nQCchB9co0qIjJZRGuk2/Z9VM+kNiyxNV1lvTlZBo=" crossorigin=""></script>
    <script src="{e(path('/assets/js/zoeken.js'))}?v={e(VER)}" defer></script>
    """,
    canonical="/aanbod/",
)
write("aanbod/index.html", aanbod)

hulp = layout(
    "Hulpmiddelen",
    f"""
    <section class="section wrap prose">
      <h1>Hulpmiddelen</h1>
      <div class="stack">
        <article class="soft-card"><h3>Wilgenwater</h3><p>Knip jonge wilgentakjes in stukjes, zet ze 24–48 uur in koud (regen)water of laat ze trekken in kokend water. Het bevat IBA (een wortelhormoon) en salicylzuur, maar de hoeveelheid is laag en onbekend, dus het helpt een beetje en is geen garantie.</p></article>
        <article class="soft-card"><h3>Stekpoeder</h3><p>Talk met een wortelhormoon (meestal IBA). Het helpt vooral bij lastige soorten. Bij bessen is het niet nodig.</p></article>
        <article class="soft-card"><h3>Bakjes en potten</h3><p>Gebruik hergebruikte bakjes met gaatjes in de bodem. Neem 1–2 L voor vijg en moerbei; een bakje met een plastic zak eroverheen werkt als minikas.</p></article>
        <article class="soft-card"><h3>Substraat</h3><p>Stekgrond is gezeefde potgrond zonder mest. Meng er scherp zand door voor drainage. Niet te nat, anders gaan stekken rotten.</p></article>
        <article class="soft-card"><h3>Gereedschap</h3><p>Een scherpe, schone schaar of een mes, en labels met de soort en de datum. Zet stekken altijd met de goede kant naar boven.</p></article>
      </div>
    </section>
    """,
    canonical="/hulpmiddelen/",
)
write("hulpmiddelen/index.html", hulp)

links_body = f"""
    <section class="section wrap">
      <h1>Partners</h1>
      <p class="section-intro">Wij bouwen niet alles zelf. Hieronder staan initiatieven waar we naast willen staan. Waar we iets nog niet zeker weten, staat <em>Volgt binnenkort</em>.</p>

      <div class="stack" style="margin-bottom:2rem">
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
      <div class="grid-cards">
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
    </section>
"""
write("links/index.html", layout("Partners", links_body, canonical="/links/"))

over = layout(
    "Over",
    f"""
    <section class="section wrap prose">
      <h1>Over {e(SITE)}</h1>
      <p>Ik ben Joris. Ik wil toetsen of mensen stekjes gaan uitdelen en ophalen — gratis, met een praatje als enige tegenprestatie. Eerst gericht op mensen die met voedselbossen en eetbare tuinen bezig zijn.</p>
      <p>De regio is open: heel Nederland. De sitenaam en het adres zijn configureerbaar; dit is de testfase (noindex).</p>
      <p>Volgt binnenkort: meer over mij en dit project.</p>
      <p><a href="{e(path('/privacy/'))}">Privacyverklaring</a> · <a href="{e(path('/doe-mee/'))}">Doe mee</a></p>
    </section>
    """,
    canonical="/over/",
)
write("over/index.html", over)

privacy = layout(
    "Privacy",
    f"""
    <section class="section wrap prose">
      <h1>Privacyverklaring</h1>
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
        <li><strong>Inzien / export:</strong> ingelogd via <a href="{e(path('/account/export.php'))}">Gegevens downloaden</a> (JSON).</li>
        <li><strong>Wissen (account):</strong> <a href="{e(path('/account/verwijderen.php'))}">Account verwijderen</a>.</li>
        <li><strong>Wissen (helper zonder account):</strong> <a href="{e(path('/helpers/verwijderen.php'))}">Helper-aanmelding verwijderen</a>.</li>
        <li>Vragen of klachten: Volgt binnenkort (contact). Je kunt ook naar de Autoriteit Persoonsgegevens.</li>
      </ul>

      <h2>Bewaren en dataminimalisatie</h2>
      <ul class="list-plain">
        <li>We bewaren alleen wat nodig is voor bovenstaande doelen.</li>
        <li>Rate-limitgegevens (gehashte IP’s): standaard max. 48 uur (configureerbaar).</li>
        <li>Geocode-cache: standaard max. 90 dagen (configureerbaar).</li>
        <li>Inactieve accounts: beleid voorstel 12 maanden zonder activiteit — Volgt binnenkort (automatische purge).</li>
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
      <p>Volgt binnenkort (datum van deze versie).</p>
    </section>
    """,
    canonical="/privacy/",
)
write("privacy/index.html", privacy)

# ---------- soorten ----------
PLACEHOLDER = "Volgt binnenkort"

SOORTEN = [
    # slug, naam, latijn, wanneer, hoe, kans, extra fields from plan where known
    {
        "slug": "zwarte-bes",
        "naam": "Zwarte bes",
        "latijn": "Ribes nigrum",
        "waarom": "Struiklaag, verdraagt halfschaduw",
        "methode": "Winterstek (houtstek)",
        "wanneer": "November tot februari, niet bij vorst of regen",
        "welk_hout": "Eenjarige tak zo dik als een potlood, zonder het zachte topje",
        "lengte_snit": "20 cm; onder net onder een knop schuin, boven recht",
        "water_grond": "Grond: 2/3 diep inkuilen of in een pot",
        "hulpmiddel": "Stekpoeder niet nodig",
        "kans": "Makkelijk",
        "klaar": "Najaar na het stekken",
        "ervaringen": PLACEHOLDER,
        "bronnen": PLACEHOLDER,
        "kort": "Winterstek van 20 cm, in grond of pot",
    },
    {"slug": "rode-bes", "naam": "Rode bes", "wanneer": "Nov–feb", "kort": "Winterstek van 20 cm, kan in een pot; lichte vorst is geen probleem", "kans": "Makkelijk"},
    {"slug": "jostabes", "naam": "Jostabes", "wanneer": "Nov–feb", "kort": "Winterstek, mag tot 1 m lang zijn", "kans": "Makkelijk"},
    {"slug": "kruisbes", "naam": "Kruisbes", "wanneer": "Okt–nov", "kort": "Winterstek van 15–25 cm, of afleggen; later gestekt slaagt minder vaak", "kans": "Gemiddeld"},
    {"slug": "zwarte-vlier", "naam": "Zwarte vlier", "wanneer": "Nov–feb", "kort": "Winterstek van eenjarig hout", "kans": "Makkelijk"},
    {"slug": "wilg", "naam": "Wilg", "wanneer": "Nov–feb", "kort": "Lange stekken (0,5–2 m) direct in de volle grond; ook de bron van wilgenwater", "kans": "Makkelijk"},
    {"slug": "vijg", "naam": "Vijg", "wanneer": "Nov, vóór vorst", "kort": "Meerjarig hout direct in een pot van 1–2 L; de top afdekken met wondbalsem", "kans": "Gemiddeld"},
    {"slug": "moerbei", "naam": "Moerbei", "wanneer": "Nov, vóór vorst", "kort": "Meerjarig hout in een pot van 1–2 L", "kans": "Gemiddeld"},
    {"slug": "druif", "naam": "Druif", "wanneer": "Nov–feb", "kort": "Winterstek: onder net onder een knop, boven 3 cm boven een knop", "kans": "Gemiddeld"},
    {"slug": "kiwibes", "naam": "Kiwibes", "wanneer": "Nov of zomer", "kort": "Winterstek, of zomerstek onder hoge luchtvochtigheid", "kans": "Gemiddeld"},
    {"slug": "framboos", "naam": "Framboos", "wanneer": "Herfst", "kort": "Worteluitlopers afsteken en verplanten", "kans": "Makkelijk"},
    {"slug": "aardbei", "naam": "Aardbei", "wanneer": "Aug–sep", "kort": "Uitlopers vastzetten in grond of een potje, loszetten bij nieuw blad", "kans": "Makkelijk"},
    {"slug": "munt", "naam": "Munt", "wanneer": "Lente–zomer", "kort": "Top van 10 cm in water; na ongeveer een week wortels", "kans": "Makkelijk"},
    {"slug": "salie", "naam": "Salie", "wanneer": "Voorjaar", "kort": "Jonge scheuten in stekgrond, afdekken", "kans": "Gemiddeld"},
    {"slug": "rozemarijn", "naam": "Rozemarijn", "wanneer": "Late lente–zomer", "kort": "Halfverhoute stek van 10–15 cm in stekgrond; 3–4 weken", "kans": "Gemiddeld"},
    {"slug": "herfstolijfwilg", "naam": "Herfstolijfwilg", "wanneer": "Okt–feb", "kort": "Winterstek of afleggen; zomerstek wordt afgeraden", "kans": "Lastig"},
]

fields = [
    ("latijn", "Latijnse naam"),
    ("waarom", "Waarom in een voedselbos"),
    ("methode", "Methode"),
    ("wanneer", "Wanneer"),
    ("welk_hout", "Welk hout"),
    ("lengte_snit", "Lengte en snit"),
    ("water_grond", "Water of grond"),
    ("hulpmiddel", "Hulpmiddel nodig?"),
    ("kans", "Slagingskans"),
    ("klaar", "Wanneer klaar om uit te delen"),
    ("ervaringen", "Ervaringen deelnemers"),
    ("bronnen", "Bronnen"),
]

soorten_dir = ROOT / "soorten"
soorten_dir.mkdir(exist_ok=True)

for s in SOORTEN:
    data = {
        "slug": s["slug"],
        "naam": s["naam"],
        "latijn": s.get("latijn", PLACEHOLDER),
        "waarom": s.get("waarom", PLACEHOLDER),
        "methode": s.get("methode", PLACEHOLDER),
        "wanneer": s.get("wanneer", PLACEHOLDER),
        "welk_hout": s.get("welk_hout", PLACEHOLDER),
        "lengte_snit": s.get("lengte_snit", PLACEHOLDER),
        "water_grond": s.get("water_grond", PLACEHOLDER),
        "hulpmiddel": s.get("hulpmiddel", PLACEHOLDER),
        "kans": s.get("kans", PLACEHOLDER),
        "klaar": s.get("klaar", PLACEHOLDER),
        "ervaringen": s.get("ervaringen", PLACEHOLDER),
        "bronnen": s.get("bronnen", PLACEHOLDER),
        "kort": s.get("kort", PLACEHOLDER),
    }
    # vul methode/kort uit startlijst waar geen sjabloonvelden zijn
    if data["methode"] == PLACEHOLDER and data["kort"] != PLACEHOLDER:
        data["methode"] = data["kort"]
    (soorten_dir / f"{s['slug']}.json").write_text(json.dumps(data, ensure_ascii=False, indent=2) + "\n", encoding="utf-8")

# overzicht + per-soort HTML
cards = []
for s in SOORTEN:
    data = json.loads((soorten_dir / f"{s['slug']}.json").read_text(encoding="utf-8"))
    cards.append(
        f'<a class="soft-card" href="{e(path(f"/stektips/{data["slug"]}/"))}"><h3>{e(data["naam"])}</h3><p>{e(data["wanneer"])} · {e(data["kans"])}</p></a>'
    )
    rows = "".join(
        f"<tr><th scope=\"row\">{e(label)}</th><td>{e(str(data.get(key, PLACEHOLDER)))}</td></tr>"
        for key, label in fields
    )
    body = f"""
    <section class="section wrap prose">
      <h1>{e(data['naam'])}</h1>
      <p class="muted">{e(data.get('kort', ''))}</p>
      <table class="table"><tbody>{rows}</tbody></table>
      <p style="margin-top:1.5rem"><a class="btn" href="{e(path('/aanbod/'))}">Naar het aanbod</a>
      <a class="btn btn-secondary" href="{e(path('/doe-mee/'))}">Doe mee</a></p>
    </section>
    """
    write(f"stektips/{data['slug']}/index.html", layout(data["naam"], body, canonical=f"/stektips/{data['slug']}/"))

olijf = """
      <aside class="soft-card" style="margin-top:1.5rem">
        <h3>Joris' eigen proef</h3>
        <p>50 olijfwilgstekken in wilgenwater zijn een mooie eerste case. Zet de helft in vochtige stekgrond in een koude bak, noteer per bakje wat er gebeurt, en publiceer de uitkomst in het voorjaar.</p>
      </aside>
"""
write(
    "stektips/index.html",
    layout(
        "Stektips",
        f"""
    <section class="section wrap">
      <h1>Stektips</h1>
      <p class="section-intro">Zestien soorten om mee te beginnen. De slagingskans is een eigen indicatie op basis van de bronnen in het plan; die geven geen percentages. Ontbrekende details: Volgt binnenkort.</p>
      <div class="grid-cards">
        {''.join(cards)}
      </div>
      {olijf}
      <p style="margin-top:1.5rem"><a href="{e(path('/hulpmiddelen/'))}">Hulpmiddelen</a></p>
    </section>
    """,
        canonical="/stektips/",
    ),
)

# site config for JS
(ROOT / "assets/js/site-config.js").write_text(
    "window.STEKJES_SITE = " + json.dumps({
        "site_name": SITE,
        "site_url": URL,
        "base_path": BASE,
        "version": VER,
        "api_zoeken": path("/api/zoeken.php"),
        "api_partners": path("/api/partners.php"),
        "api_teller": path("/api/teller.php"),
    }, ensure_ascii=False) + ";\n",
    encoding="utf-8",
)
print("done", VER)
