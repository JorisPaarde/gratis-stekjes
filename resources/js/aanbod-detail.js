/* Gedeeld detailvenster voor aanbodregels en deelplekken (lijst, stad, kaartpopup). */
const TYPE_LABELS = { uitdelen: "Te geef", ophalen: "Gezocht" };
const MAANDEN = [
  "januari",
  "februari",
  "maart",
  "april",
  "mei",
  "juni",
  "juli",
  "augustus",
  "september",
  "oktober",
  "november",
  "december",
];

const aanbodItems = {};
const plekItems = {};

let detailDlg = null;
let detailTrigger = null;
let detailHistory = false;
let detailFotos = [];
let bound = false;

export function escapeHtml(s) {
  return String(s ?? "")
    .replace(/&/g, "&amp;")
    .replace(/</g, "&lt;")
    .replace(/>/g, "&gt;")
    .replace(/"/g, "&quot;");
}

export function siteCfg() {
  return window.STEKJES_SITE || {};
}

export function formatKm(km) {
  if (km == null || !isFinite(km)) return "";
  return (km < 10 ? km.toFixed(1) : Math.round(km).toString()).replace(".", ",") + " km";
}

export function formatDatum(iso) {
  if (!iso) return "";
  const p = String(iso).slice(0, 10).split("-");
  if (p.length !== 3) return "";
  const d = parseInt(p[2], 10);
  const m = parseInt(p[1], 10);
  if (!d || !m || !MAANDEN[m - 1]) return "";
  return d + " " + MAANDEN[m - 1] + " " + p[0];
}

export function berichtUrl(item, cfg) {
  const c = cfg || siteCfg();
  return (
    (c.bericht_starten || "/account/bericht-starten") +
    "?aanbod_id=" +
    encodeURIComponent(item.id) +
    "&user_id=" +
    encodeURIComponent(item.user_id)
  );
}

export function aantalTekst(item) {
  return item && item.aantal > 1 ? item.aantal + " stuks" : "";
}

export function typeLabel(item) {
  return TYPE_LABELS[item.type] || item.type || "";
}

export function registerAanbod(items) {
  (Array.isArray(items) ? items : [items]).forEach(function (item) {
    if (item && item.id != null) aanbodItems[String(item.id)] = item;
  });
}

export function registerPlek(items) {
  (Array.isArray(items) ? items : [items]).forEach(function (item) {
    if (!item) return;
    plekItems[plekKey(item)] = item;
  });
}

export function plekKey(item) {
  if (!item) return "";
  if (item.key) return String(item.key);
  const kind = item.kind || item.soort || "plek";
  if (item.id != null) return kind + ":" + item.id;
  return kind + ":" + (item.naam || "");
}

export function lidVanHtml(verenigingen) {
  if (!verenigingen || !verenigingen.length) return "";
  return (
    '<p class="zoek-lid">Lid van ' +
    verenigingen
      .map(function (v) {
        return '<a href="' + escapeHtml(v.url) + '">' + escapeHtml(v.naam) + "</a>";
      })
      .join(", ") +
    "</p>"
  );
}

export function regelHtml(item, cfg) {
  registerAanbod(item);
  const c = cfg || siteCfg();
  const fotos = Array.isArray(item.fotos) ? item.fotos : [];
  const foto = fotos[0] && (fotos[0].thumb_url || fotos[0].url);
  const label = typeLabel(item);
  const aantal = aantalTekst(item);
  const bericht = c.bericht_binnenkort
    ? ""
    : '<a class="zoek-regel-bericht" href="' + berichtUrl(item, c) + '">Stuur bericht</a>';
  return (
    '<li class="zoek-regel">' +
    '<button type="button" class="zoek-regel-open" data-aanbod-open="' +
    escapeHtml(String(item.id)) +
    '" aria-haspopup="dialog" aria-label="' +
    escapeHtml(label + " " + (item.tekst || "") + (aantal ? ", " + aantal : "") + ": bekijk foto en info") +
    '">' +
    (foto
      ? '<img src="' + escapeHtml(foto) + '" alt="" width="56" height="56" loading="lazy">'
      : "") +
    '<span class="zoek-regel-tekst"><span class="badge">' +
    escapeHtml(label) +
    "</span> " +
    escapeHtml(item.tekst || "") +
    (item.aantal > 1 ? ' <span class="aanbod-aantal">(' + item.aantal + " stuks)</span>" : "") +
    '<span class="zoek-regel-meer" aria-hidden="true">' +
    (foto ? "Foto en info" : "Meer info") +
    "</span></span>" +
    "</button>" +
    bericht +
    "</li>"
  );
}

export function popupAanbodHtml(group, cfg) {
  registerAanbod(group.items || []);
  const titel = (group.voornaam || "") + (group.locatie_label ? " · " + group.locatie_label : "");
  return (
    '<div class="gp-popup gp-popup--aanbod">' +
    '<strong class="gp-popup-titel">' +
    escapeHtml(titel) +
    "</strong>" +
    '<ul class="zoek-regels zoek-regels--popup">' +
    (group.items || []).map(function (item) {
      return regelHtml(item, cfg);
    }).join("") +
    "</ul></div>"
  );
}

function ensureDialog() {
  if (detailDlg) return detailDlg;
  detailDlg = document.createElement("dialog");
  detailDlg.className = "aanbod-detail";
  detailDlg.setAttribute("aria-labelledby", "aanbod-detail-titel");
  detailDlg.addEventListener("click", function (ev) {
    if (ev.target === detailDlg) detailDlg.close();
    const sluit = ev.target.closest && ev.target.closest("[data-detail-sluit]");
    if (sluit) detailDlg.close();
    const thumb = ev.target.closest && ev.target.closest("[data-detail-foto]");
    if (thumb) toonDetailFoto(parseInt(thumb.getAttribute("data-detail-foto"), 10));
  });
  detailDlg.addEventListener("close", function () {
    document.documentElement.classList.remove("aanbod-detail-open");
    if (detailHistory) {
      detailHistory = false;
      if (history.state && history.state.gpAanbodDetail) history.back();
    }
    if (detailTrigger && detailTrigger.isConnected && detailTrigger.focus) detailTrigger.focus();
    detailTrigger = null;
  });
  window.addEventListener("popstate", function () {
    if (detailDlg.open) {
      detailHistory = false;
      detailDlg.close();
    }
  });
  document.body.appendChild(detailDlg);
  return detailDlg;
}

function toonDetailFoto(i) {
  const f = detailFotos[i];
  const img = detailDlg && detailDlg.querySelector(".aanbod-detail-foto img");
  if (!f || !img) return;
  img.src = f.url;
  img.alt = f.alt || "Foto bij aanbod";
  detailDlg.querySelectorAll("[data-detail-foto]").forEach(function (b) {
    b.setAttribute("aria-current", String(parseInt(b.getAttribute("data-detail-foto"), 10) === i));
  });
}

function rij(label, inhoudHtml) {
  if (!inhoudHtml || !String(inhoudHtml).trim()) return "";
  return (
    '<div class="aanbod-detail-rij"><dt>' +
    escapeHtml(label) +
    "</dt><dd>" +
    inhoudHtml +
    "</dd></div>"
  );
}

function fotosHtml(fotos, legeAlt) {
  if (!fotos.length) return "";
  const eerste = fotos[0];
  let html =
    '<div class="aanbod-detail-foto"><img src="' +
    escapeHtml(eerste.url || eerste.thumb_url) +
    '" alt="' +
    escapeHtml(eerste.alt || legeAlt) +
    '"></div>';
  if (fotos.length > 1) {
    html +=
      '<ul class="aanbod-detail-thumbs">' +
      fotos
        .map(function (f, i) {
          return (
            '<li><button type="button" data-detail-foto="' +
            i +
            '" aria-label="Foto ' +
            (i + 1) +
            " van " +
            fotos.length +
            '" aria-current="' +
            (i === 0) +
            '"><img src="' +
            escapeHtml(f.thumb_url || f.url) +
            '" alt="" width="56" height="56"></button></li>'
          );
        })
        .join("") +
      "</ul>";
  }
  return html;
}

function soortHtml(soort) {
  if (!soort || !soort.naam) return "";
  const naam = soort.url
    ? '<a href="' + escapeHtml(soort.url) + '">' + escapeHtml(soort.naam) + "</a>"
    : escapeHtml(soort.naam);
  const andere = Array.isArray(soort.andere) ? soort.andere.filter(Boolean) : [];
  return naam + (andere.length ? '<span class="aanbod-detail-ook">Ook wel: ' + escapeHtml(andere.join(", ")) + "</span>" : "");
}

export function openAanbodDetail(item, trigger, cfg) {
  const c = cfg || siteCfg();
  const dlg = ensureDialog();
  const fotos = Array.isArray(item.fotos) ? item.fotos : [];
  detailFotos = fotos;
  const label = typeLabel(item);
  const titel = (item.tekst || "").trim() || (item.soort && item.soort.naam) || label;
  const wie = [item.voornaam, item.locatie_label].filter(Boolean).join(" · ");
  const afstand = formatKm(item.afstand_km);
  const geplaatst = item.geplaatst_label || formatDatum(item.geplaatst);
  const verloopt = item.verloopt_label || formatDatum(item.verloopt);
  const rijen =
    rij("Aantal", item.aantal > 0 ? escapeHtml(item.aantal === 1 ? "1 stuk" : item.aantal + " stuks") : "") +
    rij("Soort", soortHtml(item.soort)) +
    rij("Van", wie ? escapeHtml(wie) : "") +
    rij("Afstand", afstand ? escapeHtml(afstand) : "") +
    rij("Geplaatst", geplaatst ? escapeHtml(geplaatst) : "") +
    rij("Beschikbaar tot", verloopt ? escapeHtml(verloopt) : "");
  dlg.innerHTML =
    '<div class="aanbod-detail-kop">' +
    '<span class="badge">' +
    escapeHtml(label) +
    "</span>" +
    '<button type="button" class="aanbod-detail-x" data-detail-sluit aria-label="Sluiten"><span aria-hidden="true">×</span></button></div>' +
    fotosHtml(fotos, "Foto bij aanbod: " + titel) +
    '<h2 id="aanbod-detail-titel" class="aanbod-detail-titel" tabindex="-1">' +
    escapeHtml(titel) +
    "</h2>" +
    (rijen ? '<dl class="aanbod-detail-dl">' + rijen + "</dl>" : "") +
    lidVanHtml(item.verenigingen) +
    '<div class="aanbod-detail-acties">' +
    (c.bericht_binnenkort ? "" : '<a class="btn aanbod-detail-bericht" href="' + berichtUrl(item, c) + '">Stuur bericht</a>') +
    '<button type="button" class="btn btn-secondary" data-detail-sluit>Sluiten</button></div>';
  openDialog(dlg, trigger);
}

export function openPlekDetail(item, trigger) {
  const dlg = ensureDialog();
  detailFotos = [];
  const label = item.type_label || item.type || "";
  const evenement = item.kind === "evenement" || item.soort === "evenement" || item.tijdelijk;
  const afstand = formatKm(item.afstand_km);
  const beschrijving = (item.beschrijving || item.omschrijving || "").trim();
  const bestaandeOpening = (item.openingstijden || item.openingtijden || item.wanneer || "").trim();
  let openRegel = (item.open_regel || "").trim();
  if (/^open:\s*/i.test(openRegel)) {
    openRegel = openRegel.replace(/^open:\s*/i, "").trim();
  }
  const opening = bestaandeOpening || openRegel;
  const rijen =
    rij("Type", label ? escapeHtml(label) + (evenement ? ' <span class="badge badge--tijdelijk">Tijdelijk</span>' : "") : "") +
    rij("Waar", escapeHtml([item.plaats, item.provincie].filter(Boolean).join(" · "))) +
    rij("Adres", item.adres ? escapeHtml(item.adres) : "") +
    rij("Afstand", afstand ? escapeHtml(afstand) : "") +
    rij("Wanneer", item.datum_label ? escapeHtml(item.datum_label) : "") +
    rij("Openingstijden", opening ? escapeHtml(opening) : "");
  const link = item.bron_url
    ? '<a class="btn aanbod-detail-bericht" href="' +
      escapeHtml(item.bron_url) +
      '" rel="noopener noreferrer" target="_blank">' +
      escapeHtml(item.bron_label || "Meer informatie") +
      "</a>"
    : "";
  dlg.innerHTML =
    '<div class="aanbod-detail-kop">' +
    (label ? '<span class="badge">' + escapeHtml(label) + "</span>" : "<span></span>") +
    '<button type="button" class="aanbod-detail-x" data-detail-sluit aria-label="Sluiten"><span aria-hidden="true">×</span></button></div>' +
    '<h2 id="aanbod-detail-titel" class="aanbod-detail-titel" tabindex="-1">' +
    escapeHtml(item.naam || "Deelplek") +
    "</h2>" +
    (beschrijving ? '<p class="aanbod-detail-beschrijving">' + escapeHtml(beschrijving) + "</p>" : "") +
    (rijen ? '<dl class="aanbod-detail-dl">' + rijen + "</dl>" : "") +
    '<div class="aanbod-detail-acties">' +
    link +
    '<button type="button" class="btn btn-secondary" data-detail-sluit>Sluiten</button></div>';
  openDialog(dlg, trigger);
}

function openDialog(dlg, trigger) {
  detailTrigger = trigger || null;
  document.documentElement.classList.add("aanbod-detail-open");
  dlg.showModal();
  dlg.scrollTop = 0;
  const titel = dlg.querySelector("#aanbod-detail-titel");
  if (titel && titel.focus) titel.focus();
  history.pushState({ gpAanbodDetail: true }, "", location.href);
  detailHistory = true;
}

export function openDetail(item, trigger, cfg) {
  if (!item) return;
  const kind = item.kind || item.soort || "";
  if (kind === "deelplek" || kind === "evenement" || kind === "partner" || kind === "partner_marker") {
    openPlekDetail(item, trigger);
    return;
  }
  openAanbodDetail(item, trigger, cfg);
}

export function initAanbodDetail() {
  if (bound || typeof document === "undefined") return;
  bound = true;
  document.querySelectorAll("[data-aanbod-items]").forEach(function (el) {
    try {
      registerAanbod(JSON.parse(el.textContent || "[]"));
    } catch (e) {
      /* ongeldige JSON negeren */
    }
  });
  document.querySelectorAll("[data-plek-items]").forEach(function (el) {
    try {
      registerPlek(JSON.parse(el.textContent || "[]"));
    } catch (e) {
      /* ongeldige JSON negeren */
    }
  });
  document.addEventListener("click", function (ev) {
    const target = ev.target.closest ? ev.target : null;
    if (!target) return;
    const aanbodBtn = target.closest("[data-aanbod-open]");
    if (aanbodBtn) {
      const item = aanbodItems[aanbodBtn.getAttribute("data-aanbod-open")];
      if (item) {
        ev.preventDefault();
        openAanbodDetail(item, aanbodBtn);
      }
      return;
    }
    const plekBtn = target.closest("[data-plek-open]");
    if (plekBtn) {
      const item = plekItems[plekBtn.getAttribute("data-plek-open")];
      if (item) {
        ev.preventDefault();
        openPlekDetail(item, plekBtn);
      }
    }
  });
}

if (typeof document !== "undefined") {
  if (document.readyState === "loading") {
    document.addEventListener("DOMContentLoaded", initAanbodDetail);
  } else {
    initAanbodDetail();
  }
}
