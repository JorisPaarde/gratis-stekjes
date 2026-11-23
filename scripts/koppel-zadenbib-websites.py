#!/usr/bin/env python3
"""
Koppel eigen websites aan zadenbibliotheken die nu een overzichts-URL hebben.

Bron: Agile Store Locator op zadenbibliotheeknederland.nl
  POST wp-admin/admin-ajax.php  action=asl_load_stores

Daarnaast handmatige overrides voor gedeelde overzichtspagina's
(Antwerpen, Namen, Alkmaar). Noord-Limburg heeft op de overzichtspagina
geen per-vestiging-sites → blijft overzicht als «Bron».

Gebruik:
  python3 scripts/koppel-zadenbib-websites.py           # dry-run + rapport
  python3 scripts/koppel-zadenbib-websites.py --apply   # schrijf data/deelplekken.json

Schrijft ook data/imports/zadenbib-website-koppeling.json (herbruikbaar rapport).
Geen PII (geen e-mail/telefoon) in output.
"""

from __future__ import annotations

import argparse
import html
import json
import re
import ssl
import time
import unicodedata
import urllib.error
import urllib.parse
import urllib.request
from collections import Counter
from pathlib import Path
from typing import Any

ROOT = Path(__file__).resolve().parents[1]
DEELPLEKKEN = ROOT / "data" / "deelplekken.json"
RAPPORT = ROOT / "data" / "imports" / "zadenbib-website-koppeling.json"

OVERZICHT_NL = "https://zadenbibliotheeknederland.nl/locaties/"
ASL_AJAX = "https://zadenbibliotheeknederland.nl/wp-admin/admin-ajax.php"

UA_BOT = "GratisPlantenBot/0.81 (+https://gratisplanten.nl; zadenbib-koppeling)"
UA_BROWSER = (
    "Mozilla/5.0 (Windows NT 10.0; Win64; x64) "
    "AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36"
)

# Gedeelde overzicht-URL's → per id een eigen site (alleen HTTP-200-geverifieerd).
# Antwerpen.bibliotheek.be geeft vanuit deze omgeving Cloudflare 403; alleen
# Conscience (200) en overige bronnen met 200 worden gezet.
MANUAL_OVERRIDES: dict[str, str] = {
    # Namen
    "be-namen-flawinne-grainotheque": "https://bibliotheques.namur.be/bibliotheques-du-reseau/flawinne",
    "be-namen-malonne-grainotheque": "https://bibliotheques.namur.be/bibliotheques-du-reseau/malonne",
    "be-namen-celestine-grainotheque": "https://bibliotheques.namur.be/bibliotheques-du-reseau/la-celestine",
    # Alkmaar (ASL + bibliotheekkennemerwaard)
    "alkmaar-zadenbieb-centrum": "https://www.bibliotheekkennemerwaard.nl/openingstijden#199258",
    "alkmaar-zadenbieb-de-mare": "https://www.bibliotheekkennemerwaard.nl/alkmaar-de-mare",
    # Antwerpen — alleen wat we op HTTP 200 kunnen bevestigen
    "be-antwerpen-conscience-zadenbib": "https://www.consciencebibliotheek.be/",
    # Aalten: ASL-URL redirect naar poortvangelre (gewenste canonieke URL)
    "aalten-zadenbibliotheek": "https://www.poortvangelre.nl/vestigingen-en-openingstijden/detail.11221888.html",
}

# URL's die we nooit als «eigen site» accepteren.
BLOCKED_HOST_SNIPPETS = (
    "bing.com",
    "google.com/maps",
    "goo.gl/maps",
    "maps.google",
    "facebook.com",
    "instagram.com",
    "zadenbibliotheeknederland.nl/locaties",
    "cylex.nl",
    "openingstijden.nl/",  # generieke aggregator zonder eigen bib-pagina
    "openingstijden.com/",
)


def norm(s: str) -> str:
    s = html.unescape(s or "")
    s = s.replace("’", "'").replace("‘", "'").replace("ʻ", "'")
    s = unicodedata.normalize("NFKD", s)
    s = "".join(c for c in s if not unicodedata.combining(c))
    s = s.lower()
    s = re.sub(r"[^a-z0-9]+", " ", s)
    s = re.sub(r"\s+", " ", s).strip()
    # normalize den bosch variants
    s = re.sub(r"\bs hertogenbosch\b", "den bosch", s)
    s = re.sub(r"\bs hertogenbosch\b", "den bosch", s)
    return s


def plaats_kern(plaats: str) -> str:
    p = norm(plaats)
    # drop common suffixes in parentheses already stripped by norm
    return p


def naam_zoektermen(naam: str, plaats: str) -> list[str]:
    n = norm(naam)
    for prefix in (
        "zadenbibliotheek ",
        "zadenbieb ",
        "zadenbib ",
        "zaadbibliotheek ",
        "grainotheque ",
        "grainothèque ",
    ):
        if n.startswith(prefix.strip()):
            n = n[len(prefix) :].strip()
            break
    # strip leading "bibliotheek "
    if n.startswith("bibliotheek "):
        n = n[len("bibliotheek ") :]
    terms = []
    if n:
        terms.append(n)
    pk = plaats_kern(plaats)
    if pk and pk not in terms:
        terms.append(pk)
    # last significant token of name (e.g. "klepel" from "buurthuis de klepel")
    tokens = [t for t in n.split() if t not in {"de", "het", "den", "van", "en", "bib", "a"}]
    if tokens:
        terms.append(tokens[-1])
        if len(tokens) >= 2:
            terms.append(" ".join(tokens[-2:]))
    # unique preserve order
    seen: set[str] = set()
    out: list[str] = []
    for t in terms:
        if t and t not in seen:
            seen.add(t)
            out.append(t)
    return out


def fetch_asl_stores() -> list[dict[str, Any]]:
    data = urllib.parse.urlencode({"action": "asl_load_stores"}).encode()
    req = urllib.request.Request(
        ASL_AJAX,
        data=data,
        headers={"User-Agent": UA_BROWSER, "Content-Type": "application/x-www-form-urlencoded"},
        method="POST",
    )
    with urllib.request.urlopen(req, timeout=60, context=ssl.create_default_context()) as resp:
        raw = resp.read()
    stores = json.loads(raw.decode("utf-8"))
    if not isinstance(stores, list):
        raise RuntimeError("ASL response is geen lijst")
    return stores


def store_website(store: dict[str, Any]) -> str:
    return (store.get("website") or store.get("link") or "").strip()


def is_blocked_url(url: str) -> bool:
    low = url.lower()
    return any(s in low for s in BLOCKED_HOST_SNIPPETS)


def shared_asl_websites(stores: list[dict[str, Any]]) -> set[str]:
    """Exact dezelfde website-URL bij meerdere ASL-steden → vaak fout of overzicht."""
    by_url: dict[str, set[str]] = {}
    for s in stores:
        w = store_website(s)
        if not w:
            continue
        key = w.split("#")[0].rstrip("/").lower()
        city = norm(html.unescape(str(s.get("city", ""))))
        by_url.setdefault(key, set()).add(city)
    return {u for u, cities in by_url.items() if len(cities) > 1}


def check_url(url: str) -> tuple[bool, str, int]:
    """Return (ok, final_url, status). Retry once with browser UA on 403."""

    def one(ua: str) -> tuple[int, str]:
        req = urllib.request.Request(
            url,
            method="HEAD",
            headers={"User-Agent": ua, "Accept": "text/html,application/xhtml+xml,*/*"},
        )
        try:
            with urllib.request.urlopen(req, timeout=25, context=ssl.create_default_context()) as resp:
                return int(resp.status), resp.geturl()
        except urllib.error.HTTPError as e:
            # Sommige hosts weigeren HEAD → probeer GET
            if e.code in (403, 405, 501):
                req2 = urllib.request.Request(
                    url,
                    method="GET",
                    headers={"User-Agent": ua, "Accept": "text/html,application/xhtml+xml,*/*"},
                )
                try:
                    with urllib.request.urlopen(req2, timeout=25, context=ssl.create_default_context()) as resp:
                        # lees weinig
                        resp.read(1024)
                        return int(resp.status), resp.geturl()
                except urllib.error.HTTPError as e2:
                    return int(e2.code), url
                except Exception:
                    return 0, url
            return int(e.code), url
        except Exception:
            # HEAD faalt soms hard → GET
            req2 = urllib.request.Request(
                url,
                method="GET",
                headers={"User-Agent": ua, "Accept": "text/html,application/xhtml+xml,*/*"},
            )
            try:
                with urllib.request.urlopen(req2, timeout=25, context=ssl.create_default_context()) as resp:
                    resp.read(1024)
                    return int(resp.status), resp.geturl()
            except urllib.error.HTTPError as e2:
                return int(e2.code), url
            except Exception:
                return 0, url

    status, final = one(UA_BOT)
    if status == 403:
        time.sleep(0.3)
        status, final = one(UA_BROWSER)
    ok = 200 <= status < 300
    # Strip fragment for storage? Keep fragment (Alkmaar #anchor).
    return ok, final, status


def match_store(
    plek: dict[str, Any], stores: list[dict[str, Any]]
) -> tuple[dict[str, Any] | None, str]:
    """Return (store, reason)."""
    plaats = plaats_kern(str(plek.get("plaats", "")))
    terms = naam_zoektermen(str(plek.get("naam", "")), str(plek.get("plaats", "")))
    adres = norm(str(plek.get("adres", "")))

    candidates: list[tuple[int, dict[str, Any]]] = []
    for s in stores:
        title = norm(html.unescape(str(s.get("title", ""))))
        city = norm(html.unescape(str(s.get("city", ""))))
        street = norm(html.unescape(str(s.get("street", ""))))
        desc = norm(html.unescape(re.sub(r"<[^>]+>", " ", str(s.get("description", "")))))
        score = 0
        # city match
        if plaats and (plaats == city or plaats in city or city in plaats):
            score += 50
        for t in terms:
            if not t:
                continue
            if t == title or t == city:
                score += 40
            elif t in title or t in city or t in desc:
                score += 25
            elif title in t:
                score += 20
        # street token overlap with our adres
        if street and adres:
            st_tokens = set(street.split())
            ad_tokens = set(adres.split())
            overlap = st_tokens & ad_tokens
            if len(overlap) >= 2:
                score += 30
            elif len(overlap) == 1 and next(iter(overlap)) not in {"straat", "laan", "weg", "plein"}:
                score += 10
        if score >= 50:
            candidates.append((score, s))

    if not candidates:
        return None, "geen-match"
    candidates.sort(key=lambda x: (-x[0], x[1].get("id", "")))
    best_score, best = candidates[0]
    # ambiguous if top two close and different websites
    if len(candidates) > 1 and candidates[0][0] - candidates[1][0] < 15:
        w0 = store_website(candidates[0][1])
        w1 = store_website(candidates[1][1])
        if w0 and w1 and w0 != w1:
            # prefer street overlap
            if best_score < 70:
                return None, "ambigu"
    return best, f"score={best_score}"


def main() -> int:
    parser = argparse.ArgumentParser()
    parser.add_argument("--apply", action="store_true", help="Schrijf data/deelplekken.json")
    parser.add_argument("--skip-http", action="store_true", help="Sla HTTP-checks over (alleen matchen)")
    args = parser.parse_args()

    data = json.loads(DEELPLEKKEN.read_text(encoding="utf-8"))
    plekken: list[dict[str, Any]] = data["plekken"]
    zb = [p for p in plekken if p.get("type") == "zaadbibliotheek"]

    print(f"ASL ophalen…", flush=True)
    stores = fetch_asl_stores()
    print(f"  {len(stores)} stores, {len(zb)} zadenbibliotheken in JSON", flush=True)
    asl_shared = shared_asl_websites(stores)
    print(f"  {len(asl_shared)} ASL-websites gedeeld over meerdere steden (overslaan)", flush=True)

    results: list[dict[str, Any]] = []
    updates: dict[str, str] = {}
    http_cache: dict[str, tuple[bool, str, int]] = {}

    # 1) Manual overrides first
    for pid, url in MANUAL_OVERRIDES.items():
        plek = next((p for p in plekken if p.get("id") == pid), None)
        if plek is None:
            results.append({"id": pid, "status": "override-missing"})
            continue
        if args.skip_http:
            ok, final, status = True, url, 200
        else:
            if url not in http_cache:
                print(f"  HTTP {url}", flush=True)
                http_cache[url] = check_url(url)
                time.sleep(0.2)
            ok, final, status = http_cache[url]
        if ok:
            # Prefer canonical override URL if redirect landed on same resource
            chosen = url if "poortvangelre.nl" in url else (final or url)
            # Keep fragment from override
            if "#" in url and "#" not in chosen:
                chosen = url
            updates[pid] = chosen
            results.append(
                {
                    "id": pid,
                    "naam": plek["naam"],
                    "plaats": plek["plaats"],
                    "status": "override-ok",
                    "oud": plek.get("bron_url"),
                    "nieuw": chosen,
                    "http": status,
                }
            )
        else:
            results.append(
                {
                    "id": pid,
                    "naam": plek["naam"],
                    "plaats": plek["plaats"],
                    "status": "override-http-fail",
                    "url": url,
                    "http": status,
                }
            )

    # 2) ASL match for overzicht-NL
    overzicht_plekken = [
        p
        for p in zb
        if p.get("bron_url") == OVERZICHT_NL and p.get("id") not in updates
    ]
    print(f"ASL-match voor {len(overzicht_plekken)} overzicht-URL plekken…", flush=True)

    for plek in overzicht_plekken:
        pid = str(plek["id"])
        store, reason = match_store(plek, stores)
        if store is None:
            results.append(
                {
                    "id": pid,
                    "naam": plek["naam"],
                    "plaats": plek["plaats"],
                    "status": "blijft-overzicht",
                    "reden": reason,
                }
            )
            continue
        website = store_website(store)
        website_key = website.split("#")[0].rstrip("/").lower() if website else ""
        if (
            not website
            or is_blocked_url(website)
            or website_key in asl_shared
        ):
            results.append(
                {
                    "id": pid,
                    "naam": plek["naam"],
                    "plaats": plek["plaats"],
                    "status": "blijft-overzicht",
                    "reden": (
                        "asl-gedeelde-website"
                        if website_key in asl_shared
                        else "geen-bruikbare-website"
                    ),
                    "asl_title": html.unescape(str(store.get("title", ""))),
                    "url": website or None,
                }
            )
            continue
        if args.skip_http:
            ok, final, status = True, website, 200
        else:
            if website not in http_cache:
                print(f"  HTTP {website}", flush=True)
                http_cache[website] = check_url(website)
                time.sleep(0.15)
            ok, final, status = http_cache[website]
        if not ok:
            results.append(
                {
                    "id": pid,
                    "naam": plek["naam"],
                    "plaats": plek["plaats"],
                    "status": "blijft-overzicht",
                    "reden": f"http-{status}",
                    "url": website,
                }
            )
            continue
        updates[pid] = final or website
        results.append(
            {
                "id": pid,
                "naam": plek["naam"],
                "plaats": plek["plaats"],
                "status": "gekoppeld",
                "oud": OVERZICHT_NL,
                "nieuw": updates[pid],
                "asl_title": html.unescape(str(store.get("title", ""))),
                "reden": reason,
                "http": status,
            }
        )

    # 3) Shared overviews without override → note as blijft-bron
    shared_targets = {
        "https://antwerpen.bibliotheek.be/zadenbib",
        "https://www.zadenbibnoordlimburg.be/",
        "https://bibliotheques.namur.be/services/grainotheques",
        "https://zadenbibliotheeknederland.nl/zadenbibliotheken-in-alkmaar-in-het-zonnetje/",
    }
    for p in zb:
        pid = str(p["id"])
        if pid in updates:
            continue
        if p.get("bron_url") in shared_targets:
            results.append(
                {
                    "id": pid,
                    "naam": p["naam"],
                    "plaats": p["plaats"],
                    "status": "blijft-gedeeld-overzicht",
                    "url": p.get("bron_url"),
                }
            )

    # Apply
    changed = 0
    if args.apply:
        for p in plekken:
            pid = str(p.get("id", ""))
            if pid in updates and p.get("bron_url") != updates[pid]:
                p["bron_url"] = updates[pid]
                changed += 1
        DEELPLEKKEN.write_text(
            json.dumps(data, ensure_ascii=False, indent=2) + "\n",
            encoding="utf-8",
        )

    # Stats
    gekoppeld = [r for r in results if r["status"] in {"gekoppeld", "override-ok"}]
    blijft = [
        r
        for r in results
        if r["status"] in {"blijft-overzicht", "blijft-gedeeld-overzicht", "override-http-fail"}
    ]

    rapport = {
        "meta": {
            "bron": ASL_AJAX,
            "asl_stores": len(stores),
            "zadenbibliotheken": len(zb),
            "updates": len(updates),
            "applied": bool(args.apply),
            "changed_rows": changed if args.apply else len(updates),
        },
        "updates": {k: v for k, v in sorted(updates.items())},
        "results": results,
        "blijft_als_bron": [
            {"naam": r["naam"], "plaats": r["plaats"], "url": r.get("url") or r.get("oud") or OVERZICHT_NL}
            for r in blijft
            if "naam" in r
        ],
    }
    RAPPORT.parent.mkdir(parents=True, exist_ok=True)
    RAPPORT.write_text(json.dumps(rapport, ensure_ascii=False, indent=2) + "\n", encoding="utf-8")

    print(f"\nGekoppeld/override: {len(gekoppeld)}")
    print(f"Blijft overzicht/Bron: {len(blijft)}")
    print(f"Rapport: {RAPPORT.relative_to(ROOT)}")
    if args.apply:
        print(f"Geschreven: {changed} rijen in {DEELPLEKKEN.relative_to(ROOT)}")
    else:
        print("Dry-run — gebruik --apply om te schrijven")

    return 0


if __name__ == "__main__":
    raise SystemExit(main())
