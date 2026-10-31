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
  // Eerste view via fitBounds (contain); fitNlBe gebruikt cover zodat
  // een breed vak niet half West-Europa toont.
  var map = L.map(mapEl, { zoomSnap: 0.25 }).fitBounds(nlBeBounds, nlBeFitOpts);
  L.tileLayer("https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png", {
    attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a>',
    maxZoom: 18,
  }).addTo(map);
  function fitNlBe() {
    map.invalidateSize();
    var bounds = L.latLngBounds(nlBeBounds);
    // inside=true: viewport past binnen NL+BE (cover) → nauwelijks buurlanden.
    var zoom = map.getBoundsZoom(bounds, true, L.point(4, 4));
    if (isFinite(zoom)) {
      map.setView(bounds.getCenter(), Math.min(zoom, 8));
    } else {
      map.fitBounds(nlBeBounds, nlBeFitOpts);
    }
  }
  map.whenReady(function () {
    // Na layout: cover-zoom (contain-fitBounds alleen is te wijd op breed vak).
    setTimeout(fitNlBe, 50);
    setTimeout(fitNlBe, 400);
    setTimeout(fitNlBe, 1000);
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
    zaadbibliotheek: "Zaadbibliotheek",
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

  function renderPartners() {
    partnerLayer.clearLayers();
    var allowed = enabledPartnerTypes();
    var bounds = [];
    var visible = partnerItems.filter(function (item) {
      return allowed.indexOf(item.type) !== -1;
    });

    // Verwijder oude partner-rijen uit de lijst
    list.querySelectorAll('[data-kind="partner_marker"]').forEach(function (el) {
      el.remove();
    });

    visible.forEach(function (item) {
      var li = document.createElement("li");
      li.className = "zoek-item zoek-item--partner";
      li.setAttribute("data-kind", "partner_marker");
      li.setAttribute("data-type", item.type || "");
      var label = item.type_label || typeLabels[item.type] || item.type;
      var link =
        item.url
          ? '<p><a href="' +
            escapeHtml(item.url) +
            '" rel="noopener noreferrer" target="_blank">Website</a></p>'
          : "";
      li.innerHTML =
        '<span class="badge badge--' +
        escapeHtml(item.type) +
        '">' +
        escapeHtml(label) +
        "</span>" +
        "<h3>" +
        escapeHtml(item.naam) +
        (item.plaats ? " · " + escapeHtml(item.plaats) : "") +
        "</h3>" +
        "<p>" +
        escapeHtml(item.beschrijving || "") +
        "</p>" +
        link;
      list.appendChild(li);

      if (item.lat != null && item.lon != null) {
        var popup =
          "<strong>" +
          escapeHtml(item.naam) +
          "</strong><br>" +
          escapeHtml(label) +
          (item.plaats ? " · " + escapeHtml(item.plaats) : "");
        if (item.beschrijving) {
          popup += "<br>" + escapeHtml(item.beschrijving);
        }
        var m = L.marker([item.lat, item.lon], { icon: partnerIcon(item.type) }).bindPopup(popup);
        partnerLayer.addLayer(m);
        bounds.push([item.lat, item.lon]);
      }
    });

    return { count: visible.length, bounds: bounds };
  }

  function renderDeelplekken() {
    deelplekLayer.clearLayers();
    var allowed = enabledPartnerTypes();
    var bounds = [];
    var visible = deelplekItems.filter(function (item) {
      return allowed.indexOf(item.type) !== -1;
    });

    list.querySelectorAll('[data-kind="deelplek"]').forEach(function (el) {
      el.remove();
    });

    visible.forEach(function (item) {
      var li = document.createElement("li");
      li.className = "zoek-item zoek-item--deelplek";
      li.setAttribute("data-kind", "deelplek");
      li.setAttribute("data-type", item.type || "");
      var label = item.type_label || typeLabels[item.type] || item.type;
      var bron =
        item.bron_url
          ? '<p><a href="' +
            escapeHtml(item.bron_url) +
            '" rel="noopener noreferrer" target="_blank">Bron</a></p>'
          : "";
      li.innerHTML =
        '<span class="badge badge--' +
        escapeHtml(item.type) +
        '">' +
        escapeHtml(label) +
        "</span>" +
        "<h3>" +
        escapeHtml(item.naam) +
        (item.plaats ? " · " + escapeHtml(item.plaats) : "") +
        "</h3>" +
        "<p>" +
        escapeHtml(item.adres || item.plaats || "Deelplek") +
        "</p>" +
        bron;
      list.appendChild(li);

      var lon = item.lng != null ? item.lng : item.lon;
      if (item.lat != null && lon != null) {
        var popup =
          "<strong>" +
          escapeHtml(item.naam) +
          "</strong><br>" +
          escapeHtml(label) +
          (item.plaats ? " · " + escapeHtml(item.plaats) : "");
        if (item.adres) {
          popup += "<br>" + escapeHtml(item.adres);
        }
        if (item.bron_url) {
          popup +=
            '<br><a href="' +
            escapeHtml(item.bron_url) +
            '" rel="noopener noreferrer" target="_blank">Bron</a>';
        }
        var m = L.marker([item.lat, lon], { icon: deelplekIcon(item.type) }).bindPopup(popup);
        deelplekLayer.addLayer(m);
        bounds.push([item.lat, lon]);
      }
    });

    return { count: visible.length, bounds: bounds };
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

  function renderAanbodList(items) {
    list.querySelectorAll('[data-kind="aanbod"]').forEach(function (el) {
      el.remove();
    });
    if (!showAanbod()) return;
    items.forEach(function (item) {
      var li = document.createElement("li");
      li.className = "zoek-item";
      li.setAttribute("data-kind", "aanbod");
      var afstand =
        item.afstand_km != null ? " · " + item.afstand_km + " km" : "";
      var tekstHtml = item.tekst
        ? "<p>" + escapeHtml(item.tekst) + "</p>"
        : "";
      li.innerHTML =
        '<span class="badge">' +
        escapeHtml(item.type) +
        "</span>" +
        "<h3>" +
        escapeHtml(item.voornaam) +
        (item.locatie_label ? " · " + escapeHtml(item.locatie_label) : "") +
        afstand +
        "</h3>" +
        renderAanbodFotos(item) +
        tekstHtml +
        (cfg.bericht_binnenkort
          ? '<p class="muted"><em>Bericht sturen: binnenkort</em></p>'
          : '<p><a class="btn btn-secondary" href="' +
            (cfg.bericht_starten || "/account/bericht-starten") +
            "?aanbod_id=" +
            encodeURIComponent(item.id) +
            "&user_id=" +
            encodeURIComponent(item.user_id) +
            '">Stuur bericht</a></p>');
      list.insertBefore(li, list.firstChild);
    });
  }

  function refreshMarkers(fitPartners) {
    aanbodLayer.clearLayers();
    var partnerInfo = renderPartners();
    var deelplekInfo = renderDeelplekken();
    var bounds = partnerInfo.bounds.concat(deelplekInfo.bounds);

    if (showAanbod()) {
      aanbodItems.forEach(function (item) {
        if (item.lat != null && item.lon != null) {
          var popup =
            "<strong>" +
            escapeHtml(item.voornaam) +
            "</strong> — " +
            escapeHtml(item.type);
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
          var m = L.marker([item.lat, item.lon]).bindPopup(popup);
          aanbodLayer.addLayer(m);
          bounds.push([item.lat, item.lon]);
        }
      });
      renderAanbodList(aanbodItems);
    } else {
      renderAanbodList([]);
    }

    var plekCount = partnerInfo.count + deelplekInfo.count;
    var parts = [];
    if (showAanbod() && aanbodItems.length) {
      parts.push(aanbodItems.length + " aanbod");
    }
    if (plekCount) {
      parts.push(plekCount + " plekken");
    }
    if (!parts.length && !aanbodItems.length) {
      status.textContent =
        (partnerItems.length || deelplekItems.length) && !plekCount
          ? "Geen markers voor de gekozen filters."
          : status.dataset.empty ||
            "Vul een postcode of plaats in om aanbod te zoeken. Deelplekken staan hieronder.";
    } else if (parts.length) {
      status.textContent = parts.join(" · ");
    }

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
          if (!r.ok) throw new Error((data && data.error) || "Markers laden mislukt");
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
          if (!r.ok) throw new Error((data && data.error) || "Deelplekken laden mislukt");
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

  form.addEventListener("submit", function (ev) {
    var qEl = document.getElementById("zoek-q");
    var plantEl = document.getElementById("zoek-plant");
    var q = qEl ? qEl.value.trim() : "";
    var plant =
      (plantEl && plantEl.value.trim()) ||
      (cfg.plant_filter ? String(cfg.plant_filter) : "");
    var type = document.getElementById("zoek-type").value;
    // Zonder plaats: server-side plantfilter (werkt zonder JS / progressive enhancement)
    if (!q) {
      return;
    }
    ev.preventDefault();
    status.textContent = "Zoeken…";
    aanbodItems = [];
    aanbodLayer.clearLayers();
    list.querySelectorAll('[data-kind="aanbod"]').forEach(function (el) {
      el.remove();
    });

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
          if (!r.ok) throw new Error((data && data.error) || "Zoeken mislukt");
          return data;
        });
      })
      .then(function (data) {
        aanbodItems = (data && data.items) || [];
        var meta = data && data.meta;
        if (!aanbodItems.length) {
          status.dataset.empty =
            "Nog geen aanbod in de buurt. Bekijk deelplekken op de kaart, of zet zelf iets online via een account.";
        } else {
          status.dataset.empty = "";
        }
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
        if (!aanbodItems.length && !partnerItems.length && !deelplekItems.length) {
          status.textContent =
            "Nog geen aanbod in de buurt. Bekijk deelplekken op de kaart, of zet zelf iets online via een account.";
        }
      })
      .catch(function (err) {
        status.textContent = err.message || "Zoeken mislukt";
      });
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
})();
