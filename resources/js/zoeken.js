/* Zoeken: kaart + lijst + initiatief-/kaartmarkers. */
(function () {
  var form = document.getElementById("zoek-form");
  var list = document.getElementById("zoek-lijst");
  var status = document.getElementById("zoek-status");
  var mapEl = document.getElementById("kaart");
  var filterEl = document.getElementById("marker-filters");
  if (!form || !list || typeof L === "undefined") return;

  // Beginuitsnede direct bij aanmaken (view nodig vóór whenReady/tiles).
  var nlBeBounds = [
    [49.45, 2.4],
    [53.55, 7.25],
  ];
  var nlBeFitOpts = { padding: [4, 4], maxZoom: 8 };
  // zoomSnap 0.25: tussenstappen tussen zoom 5/6/7.
  // fitNlBe: contain (inside=false) zodat heel NL+BE in beeld blijft,
  // strakker dan zoom 5 maar zonder afsnijden van noord/zuid.
  var map = L.map(mapEl, { zoomSnap: 0.25 }).fitBounds(nlBeBounds, nlBeFitOpts);
  L.tileLayer("https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png", {
    attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a>',
    maxZoom: 18,
  }).addTo(map);
  function fitNlBe() {
    map.invalidateSize();
    var bounds = L.latLngBounds(nlBeBounds);
    // inside=false: NL+BE past binnen viewport (contain) → geen afsnijden.
    var zoom = map.getBoundsZoom(bounds, false, L.point(4, 4));
    if (isFinite(zoom)) {
      map.setView(bounds.getCenter(), Math.min(zoom, 8));
    } else {
      map.fitBounds(nlBeBounds, nlBeFitOpts);
    }
  }
  // Na layout / late size: opnieuw contain-zoom — maar niet meer zodra er gezocht is
  // (anders springt de kaart bij binnenkomen via ?q= terug naar heel NL+BE).
  var heeftGezocht = false;
  function initialFit() {
    if (heeftGezocht) {
      map.invalidateSize();
      return;
    }
    fitNlBe();
  }
  map.whenReady(function () {
    setTimeout(initialFit, 50);
    setTimeout(initialFit, 400);
    setTimeout(initialFit, 1000);
  });
  var aanbodLayer = L.layerGroup().addTo(map);
  var partnerLayer = L.layerGroup().addTo(map);
  var deelplekLayer = L.layerGroup().addTo(map);

  var cfg = window.STEKJES_SITE || {};
  var api = cfg.api_zoeken || "/api/zoeken.php";
  var apiPartners = cfg.api_partners || "/api/partners.php";
  var apiDeelplekken = cfg.api_deelplekken || "/api/deelplekken";

  var partnerItems = [];
  var deelplekItems = [];
  var aanbodItems = [];
  var typeLabels = {
    partner: "Initiatief",
    voedselbos: "Voedselbos",
    plantenasiel: "Plantenasiel",
    plantenbieb: "Plantenbieb",
    ruilkast: "Ruilkast",
    stekjesruilpunt: "Stekjesruilpunt",
    zaadbibliotheek: "Zadenbibliotheek",
  };

  var markerColors = {
    partner: "#1f4d2b",
    voedselbos: "#163820",
    plantenasiel: "#e8833a",
    plantenbieb: "#3d6b8a",
    ruilkast: "#6b4c2f",
    stekjesruilpunt: "#5a6b3d",
    zaadbibliotheek: "#8a6a3a",
  };

  function enabledPartnerTypes() {
    if (!filterEl) return Object.keys(typeLabels);
    var boxes = filterEl.querySelectorAll('input[data-partner-type]');
    var on = [];
    boxes.forEach(function (box) {
      if (box.checked) on.push(box.getAttribute("data-partner-type"));
    });
    return on;
  }

  function showAanbod() {
    if (!filterEl) return true;
    var box = filterEl.querySelector('input[data-aanbod]');
    return !box || box.checked;
  }

  function partnerIcon(type) {
    var color = markerColors[type] || markerColors.partner;
    return L.divIcon({
      className: "partner-marker partner-marker--" + type,
      html:
        '<span class="partner-marker-dot" style="background:' +
        color +
        '" title="' +
        escapeHtml(typeLabels[type] || type) +
        '"></span>',
      iconSize: [18, 18],
      iconAnchor: [9, 9],
      popupAnchor: [0, -8],
    });
  }

  function deelplekIcon(type) {
    var color = markerColors[type] || markerColors.partner;
    return L.divIcon({
      className: "deelplek-marker deelplek-marker--" + type,
      html:
        '<span class="deelplek-marker-dot" style="background:' +
        color +
        '" title="' +
        escapeHtml(typeLabels[type] || type) +
        '"></span>',
      iconSize: [18, 18],
      iconAnchor: [9, 9],
      popupAnchor: [0, -8],
    });
  }

  // Div-icon i.p.v. Leaflet-default (CDN-bitmap; CSP img-src blokkeert dat).
  function aanbodIcon() {
    return L.divIcon({
      className: "partner-marker partner-marker--aanbod",
      html:
        '<span class="partner-marker-dot" style="background:' +
        markerColors.partner +
        '" title="Aanbod"></span>',
      iconSize: [18, 18],
      iconAnchor: [9, 9],
      popupAnchor: [0, -8],
    });
  }

  // —— Lijst: twee begrensde groepen (aanbod, plekken), dichtstbij eerst na zoeken ——
  var center = null; // { lat, lon, label } na een zoekopdracht
  var limits = { aanbod: 6, plekken: 6 };
  var STAP = { aanbod: 6, plekken: 8 };

  function distanceKm(lat1, lon1, lat2, lon2) {
    var r = 6371;
    var dLat = ((lat2 - lat1) * Math.PI) / 180;
    var dLon = ((lon2 - lon1) * Math.PI) / 180;
    var a =
      Math.sin(dLat / 2) * Math.sin(dLat / 2) +
      Math.cos((lat1 * Math.PI) / 180) *
        Math.cos((lat2 * Math.PI) / 180) *
        Math.sin(dLon / 2) *
        Math.sin(dLon / 2);
    return r * 2 * Math.atan2(Math.sqrt(a), Math.sqrt(1 - a));
  }

  function formatKm(km) {
    if (km == null || !isFinite(km)) return "";
    return (km < 10 ? km.toFixed(1) : Math.round(km).toString()).replace(".", ",") + " km";
  }

  function itemLon(item) {
    return item.lng != null ? item.lng : item.lon;
  }

  function visiblePlekken() {
    var allowed = enabledPartnerTypes();
    var plekken = partnerItems
      .map(function (item) {
        return Object.assign({ kind: "partner_marker" }, item);
      })
      .concat(
        deelplekItems.map(function (item) {
          return Object.assign({ kind: "deelplek" }, item);
        })
      )
      .filter(function (item) {
        return allowed.indexOf(item.type) !== -1;
      });
    if (center) {
      plekken.forEach(function (item) {
        var lon = itemLon(item);
        item.afstand_km =
          item.lat != null && lon != null ? distanceKm(center.lat, center.lon, item.lat, lon) : null;
      });
      plekken.sort(function (a, b) {
        if (a.afstand_km == null) return 1;
        if (b.afstand_km == null) return -1;
        return a.afstand_km - b.afstand_km;
      });
    }
    return plekken;
  }

  function plekPopup(item, label) {
    var popup =
      "<strong>" +
      escapeHtml(item.naam) +
      "</strong><br>" +
      escapeHtml(label) +
      (item.plaats ? " · " + escapeHtml(item.plaats) : "");
    if (item.kind === "deelplek") {
      if (item.adres) popup += "<br>" + escapeHtml(item.adres);
      if (item.bron_url) {
        popup +=
          '<br><a href="' +
          escapeHtml(item.bron_url) +
          '" rel="noopener noreferrer" target="_blank">Bron</a>';
      }
    } else if (item.beschrijving) {
      popup += "<br>" + escapeHtml(item.beschrijving);
    }
    return popup;
  }

  function renderPlekMarkers(plekken) {
    partnerLayer.clearLayers();
    deelplekLayer.clearLayers();
    var bounds = [];
    plekken.forEach(function (item) {
      var lon = itemLon(item);
      if (item.lat == null || lon == null) return;
      var label = item.type_label || typeLabels[item.type] || item.type;
      var isDeelplek = item.kind === "deelplek";
      var m = L.marker([item.lat, lon], {
        icon: isDeelplek ? deelplekIcon(item.type) : partnerIcon(item.type),
      }).bindPopup(plekPopup(item, label));
      (isDeelplek ? deelplekLayer : partnerLayer).addLayer(m);
      bounds.push([item.lat, lon]);
    });
    return bounds;
  }

  function plekListItem(item) {
    var li = document.createElement("li");
    li.className = "zoek-item " + (item.kind === "deelplek" ? "zoek-item--deelplek" : "zoek-item--partner");
    li.setAttribute("data-kind", item.kind);
    li.setAttribute("data-type", item.type || "");
    var label = item.type_label || typeLabels[item.type] || item.type;
    var afstand = item.afstand_km != null ? " · " + formatKm(item.afstand_km) : "";
    var tekst = item.kind === "deelplek" ? item.adres || item.plaats || "" : item.beschrijving || "";
    var linkUrl = item.kind === "deelplek" ? item.bron_url : item.url;
    var linkLabel = item.kind === "deelplek" ? "Bron" : "Website";
    li.innerHTML =
      '<span class="badge badge--' +
      escapeHtml(item.type) +
      '">' +
      escapeHtml(label) +
      "</span>" +
      "<h3>" +
      escapeHtml(item.naam) +
      (item.plaats ? " · " + escapeHtml(item.plaats) : "") +
      escapeHtml(afstand) +
      "</h3>" +
      (tekst ? "<p>" + escapeHtml(tekst) + "</p>" : "") +
      (linkUrl
        ? '<p><a href="' +
          escapeHtml(linkUrl) +
          '" rel="noopener noreferrer" target="_blank">' +
          linkLabel +
          "</a></p>"
        : "");
    return li;
  }

  function renderAanbodFotos(item) {
    var fotos = Array.isArray(item.fotos) ? item.fotos : [];
    if (!fotos.length) return "";
    var html = '<div class="zoek-item-fotos">';
    fotos.forEach(function (foto) {
      var src = foto.thumb_url || foto.url || "";
      if (!src) return;
      html +=
        '<img src="' +
        escapeHtml(src) +
        '" alt="' +
        escapeHtml(foto.alt || "Foto bij aanbod") +
        '" width="72" height="72" loading="lazy">';
    });
    html += "</div>";
    return html;
  }

  var aanbodTypeLabels = { uitdelen: "Te geef", ophalen: "Gezocht" };

  function aanbodListItem(item) {
    var li = document.createElement("li");
    li.className = "zoek-item";
    li.setAttribute("data-kind", "aanbod");
    var afstand = item.afstand_km != null ? " · " + formatKm(item.afstand_km) : "";
    var tekstHtml = item.tekst ? "<p>" + escapeHtml(item.tekst) + "</p>" : "";
    li.innerHTML =
      '<span class="badge">' +
      escapeHtml(aanbodTypeLabels[item.type] || item.type) +
      "</span>" +
      "<h3>" +
      escapeHtml(item.voornaam) +
      (item.locatie_label ? " · " + escapeHtml(item.locatie_label) : "") +
      escapeHtml(afstand) +
      "</h3>" +
      renderAanbodFotos(item) +
      tekstHtml +
      (cfg.bericht_binnenkort
        ? '<p class="muted"><em>Een bericht sturen kan hier nog niet</em></p>'
        : '<p><a class="btn btn-secondary" href="' +
          (cfg.bericht_starten || "/account/bericht-starten") +
          "?aanbod_id=" +
          encodeURIComponent(item.id) +
          "&user_id=" +
          encodeURIComponent(item.user_id) +
          '">Stuur bericht</a></p>');
    return li;
  }

  function groepKop(titel, aantal) {
    var li = document.createElement("li");
    li.className = "zoek-groep-kop";
    li.innerHTML =
      "<h2>" +
      escapeHtml(titel) +
      (aantal != null ? ' <span class="zoek-groep-aantal">(' + aantal + ")</span>" : "") +
      "</h2>";
    return li;
  }

  function meerKnop(soort, rest) {
    var li = document.createElement("li");
    li.className = "zoek-meer";
    var tekst = soort === "aanbod" ? "Toon meer aanbod" : "Toon meer plekken";
    li.innerHTML =
      '<button type="button" class="btn btn-secondary" data-meer="' +
      soort +
      '">' +
      tekst +
      " (nog " +
      rest +
      ")</button>";
    return li;
  }

  function renderList(plekken) {
    list.innerHTML = "";
    var aanbod = showAanbod() ? aanbodItems : [];

    if (aanbod.length) {
      // Zonder zoekopdracht is «recent» begrensd op de server: geen (misleidend) totaal tonen.
      list.appendChild(groepKop(center ? "Aanbod, dichtstbij eerst" : "Net aangeboden", center ? aanbod.length : null));
      aanbod.slice(0, limits.aanbod).forEach(function (item) {
        list.appendChild(aanbodListItem(item));
      });
      if (aanbod.length > limits.aanbod) {
        list.appendChild(meerKnop("aanbod", aanbod.length - limits.aanbod));
      }
    }

    if (plekken.length) {
      list.appendChild(groepKop(center ? "Plekken, dichtstbij eerst" : "Deelplekken en initiatieven", plekken.length));
      plekken.slice(0, limits.plekken).forEach(function (item) {
        list.appendChild(plekListItem(item));
      });
      if (plekken.length > limits.plekken) {
        list.appendChild(meerKnop("plekken", plekken.length - limits.plekken));
      }
    }
  }

  function resetLimits() {
    var hasAanbod = showAanbod() && aanbodItems.length > 0;
    limits.aanbod = STAP.aanbod;
    // Weinig of geen aanbod: plekken krijgen meer ruimte; veel aanbod: plekken compact.
    limits.plekken = hasAanbod ? 4 : 8;
  }

  list.addEventListener("click", function (ev) {
    var btn = ev.target.closest ? ev.target.closest("[data-meer]") : null;
    if (!btn) return;
    var soort = btn.getAttribute("data-meer");
    limits[soort] += STAP[soort];
    renderList(visiblePlekken());
  });

  function updateStatus(plekCount) {
    var aanbodCount = showAanbod() ? aanbodItems.length : 0;
    if (!aanbodCount && !plekCount) {
      status.textContent =
        (partnerItems.length || deelplekItems.length)
          ? "Geen plekken bij de gekozen filters."
          : status.dataset.empty || "Vul een postcode of plaats in om te zien wat er dichtbij is.";
      return;
    }
    var parts = [];
    if (aanbodCount) parts.push(center ? aanbodCount + " aanbod" : "nieuw aanbod");
    if (plekCount) parts.push(plekCount + (plekCount === 1 ? " plek" : " plekken"));
    if (center) {
      status.textContent = "Rond " + center.label + ": " + parts.join(" · ") + ", dichtstbij eerst.";
    } else {
      status.textContent = parts.join(" · ") + ". Vul een postcode of plaats in om te zien wat dichtbij is.";
    }
  }

  function refreshMarkers(fitPartners) {
    aanbodLayer.clearLayers();
    var plekken = visiblePlekken();
    var bounds = renderPlekMarkers(plekken);

    if (showAanbod()) {
      aanbodItems.forEach(function (item) {
        if (item.lat != null && item.lon != null) {
          var popup =
            "<strong>" +
            escapeHtml(item.voornaam) +
            "</strong> · " +
            escapeHtml(aanbodTypeLabels[item.type] || item.type);
          if (item.tekst) {
            popup += "<br>" + escapeHtml(item.tekst);
          }
          if (item.fotos && item.fotos[0] && item.fotos[0].thumb_url) {
            popup +=
              '<br><img src="' +
              escapeHtml(item.fotos[0].thumb_url) +
              '" alt="' +
              escapeHtml((item.fotos[0].alt || "Foto") ) +
              '" width="120" height="90" style="margin-top:0.35rem;object-fit:cover;border-radius:0.25rem">';
          }
          var m = L.marker([item.lat, item.lon], { icon: aanbodIcon() }).bindPopup(popup);
          aanbodLayer.addLayer(m);
          bounds.push([item.lat, item.lon]);
        }
      });
    }

    renderList(plekken);
    updateStatus(plekken.length);

    if (fitPartners && bounds.length) {
      map.fitBounds(bounds, { padding: [24, 24], maxZoom: 12 });
      // Clamp: nooit verder uit dan NL+BE.
      var minZoom = map.getBoundsZoom(L.latLngBounds(nlBeBounds), false, L.point(16, 16));
      if (map.getZoom() < minZoom) {
        fitNlBe();
      }
    }
  }

  function loadPartners() {
    return fetch(apiPartners, {
      credentials: "same-origin",
      headers: { Accept: "application/json" },
    })
      .then(function (r) {
        return r.json().then(function (data) {
          if (!r.ok) throw new Error((data && data.error) || "De kaartplekken laden is niet gelukt");
          return data;
        });
      })
      .then(function (data) {
        if (data && data.types) {
          typeLabels = Object.assign(typeLabels, data.types);
        }
        partnerItems = (data && data.items) || [];
        // Geen fitBounds bij eerste load: beginuitsnede blijft NL + België.
        refreshMarkers(false);
      })
      .catch(function () {
        partnerItems = [];
      });
  }

  function loadDeelplekken() {
    return fetch(apiDeelplekken, {
      credentials: "same-origin",
      headers: { Accept: "application/json" },
    })
      .then(function (r) {
        return r.json().then(function (data) {
          if (!r.ok) throw new Error((data && data.error) || "De deelplekken laden is niet gelukt");
          return data;
        });
      })
      .then(function (data) {
        if (data && data.types) {
          typeLabels = Object.assign(typeLabels, data.types);
        }
        deelplekItems = (data && data.items) || [];
        // Geen fitBounds bij eerste load: beginuitsnede blijft NL + België.
        refreshMarkers(false);
      })
      .catch(function () {
        deelplekItems = [];
      });
  }

  if (filterEl) {
    filterEl.addEventListener("change", function () {
      refreshMarkers(false);
    });
  }

  function loadRecentAanbod() {
    var type = document.getElementById("zoek-type");
    var url = api + "?recent=1" + (type && type.value ? "&type=" + encodeURIComponent(type.value) : "");
    return fetch(url, { credentials: "same-origin", headers: { Accept: "application/json" } })
      .then(function (r) {
        return r.ok ? r.json() : { items: [] };
      })
      .then(function (data) {
        if (center) return; // intussen gezocht: zoekresultaat wint
        aanbodItems = (data && data.items) || [];
        resetLimits();
        refreshMarkers(false);
      })
      .catch(function () {});
  }

  function zoek(q) {
    heeftGezocht = true;
    var plantEl = document.getElementById("zoek-plant");
    var plant =
      (plantEl && plantEl.value.trim()) ||
      (cfg.plant_filter ? String(cfg.plant_filter) : "");
    var type = document.getElementById("zoek-type").value;
    status.textContent = "Zoeken…";
    status.dataset.empty = "";

    var url =
      api +
      "?q=" +
      encodeURIComponent(q) +
      "&type=" +
      encodeURIComponent(type) +
      (plant ? "&plant=" + encodeURIComponent(plant) : "");
    fetch(url, { credentials: "same-origin", headers: { Accept: "application/json" } })
      .then(function (r) {
        return r.json().then(function (data) {
          if (!r.ok) throw new Error((data && data.error) || "Zoeken is niet gelukt");
          return data;
        });
      })
      .then(function (data) {
        aanbodItems = (data && data.items) || [];
        var meta = data && data.meta;
        center =
          meta && meta.zoek_lat != null
            ? { lat: meta.zoek_lat, lon: meta.zoek_lon, label: meta.zoek_label || q }
            : null;
        if (!aanbodItems.length) {
          status.dataset.empty =
            "Nog geen aanbod in de buurt. Bekijk de plekken op de kaart, of zet zelf iets online via een account.";
        }
        resetLimits();
        // Markers tekenen zonder landelijke fitBounds; zoom rond zoekresultaat.
        refreshMarkers(false);
        if (meta && meta.zoek_lat != null) {
          map.setView([meta.zoek_lat, meta.zoek_lon], 11);
        } else if (aanbodItems.length) {
          var resultBounds = [];
          aanbodItems.forEach(function (item) {
            if (item.lat != null && item.lon != null) {
              resultBounds.push([item.lat, item.lon]);
            }
          });
          if (resultBounds.length) {
            map.fitBounds(resultBounds, { padding: [24, 24], maxZoom: 12 });
            var minZ = map.getBoundsZoom(L.latLngBounds(nlBeBounds), false, L.point(16, 16));
            if (map.getZoom() < minZ) {
              fitNlBe();
            }
          }
        }
        if (!meta) {
          status.textContent =
            "Ik kon “" + q + "” niet vinden. Probeer een postcode of een andere plaatsnaam.";
        }
      })
      .catch(function (err) {
        status.textContent = err.message || "Zoeken is niet gelukt";
      });
  }

  form.addEventListener("submit", function (ev) {
    var qEl = document.getElementById("zoek-q");
    var q = qEl ? qEl.value.trim() : "";
    // Zonder plaats: server-side plantfilter (werkt zonder JS / progressive enhancement)
    if (!q) {
      return;
    }
    ev.preventDefault();
    if (window.history && window.history.replaceState) {
      var params = new URLSearchParams(window.location.search);
      params.set("q", q);
      window.history.replaceState(null, "", window.location.pathname + "?" + params.toString());
    }
    zoek(q);
  });

  function escapeHtml(s) {
    return String(s)
      .replace(/&/g, "&amp;")
      .replace(/</g, "&lt;")
      .replace(/>/g, "&gt;")
      .replace(/"/g, "&quot;");
  }

  loadPartners();
  loadDeelplekken();

  // Binnenkomen met ?q= (zoekveld op de homepage): direct zoeken; anders nieuwste aanbod tonen.
  var startQ = new URLSearchParams(window.location.search).get("q");
  var startQEl = document.getElementById("zoek-q");
  if (startQ && startQ.trim()) {
    if (startQEl) startQEl.value = startQ.trim();
    zoek(startQ.trim());
  } else {
    loadRecentAanbod();
  }
})();
