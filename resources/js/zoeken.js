/* Zoeken: kaart + lijst + partnermarkers. */
(function () {
  var form = document.getElementById("zoek-form");
  var list = document.getElementById("zoek-lijst");
  var status = document.getElementById("zoek-status");
  var mapEl = document.getElementById("kaart");
  var filterEl = document.getElementById("marker-filters");
  if (!form || !list || typeof L === "undefined") return;

  var map = L.map(mapEl).setView([52.1, 5.2], 7);
  L.tileLayer("https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png", {
    attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a>',
    maxZoom: 18,
  }).addTo(map);
  var aanbodLayer = L.layerGroup().addTo(map);
  var partnerLayer = L.layerGroup().addTo(map);

  var cfg = window.STEKJES_SITE || {};
  var api = cfg.api_zoeken || "/api/zoeken.php";
  var apiPartners = cfg.api_partners || "/api/partners.php";

  var partnerItems = [];
  var aanbodItems = [];
  var typeLabels = {
    partner: "Partner",
    voedselbos: "Voedselbos",
    plantenasiel: "Plantenasiel",
    plantenbieb: "Plantenbieb",
    ruilkast: "Ruilkast",
  };

  var markerColors = {
    partner: "#1f6b74",
    voedselbos: "#16555c",
    plantenasiel: "#c46b2d",
    plantenbieb: "#3d6b8a",
    ruilkast: "#6b4c2f",
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
        escapeHtml(item.beschrijving || "Volgt binnenkort") +
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
      li.innerHTML =
        '<span class="badge">' +
        escapeHtml(item.type) +
        "</span>" +
        "<h3>" +
        escapeHtml(item.voornaam) +
        (item.locatie_label ? " · " + escapeHtml(item.locatie_label) : "") +
        afstand +
        "</h3>" +
        "<p>" +
        escapeHtml(item.tekst) +
        "</p>" +
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
    var bounds = partnerInfo.bounds.slice();

    if (showAanbod()) {
      aanbodItems.forEach(function (item) {
        if (item.lat != null && item.lon != null) {
          var m = L.marker([item.lat, item.lon]).bindPopup(
            escapeHtml(item.voornaam) + " — " + escapeHtml(item.type)
          );
          aanbodLayer.addLayer(m);
          bounds.push([item.lat, item.lon]);
        }
      });
      renderAanbodList(aanbodItems);
    } else {
      renderAanbodList([]);
    }

    var parts = [];
    if (showAanbod() && aanbodItems.length) {
      parts.push(aanbodItems.length + " aanbod");
    }
    if (partnerInfo.count) {
      parts.push(partnerInfo.count + " plekken");
    }
    if (!parts.length && !aanbodItems.length) {
      status.textContent =
        partnerItems.length && !partnerInfo.count
          ? "Geen markers voor de gekozen filters."
          : status.dataset.empty ||
            "Vul een postcode of plaats in om aanbod te zoeken. Plekken (voedselbos e.d.) staan hieronder.";
    } else if (parts.length) {
      status.textContent = parts.join(" · ");
    }

    if (fitPartners && bounds.length) {
      map.fitBounds(bounds, { padding: [24, 24], maxZoom: 12 });
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
        refreshMarkers(partnerItems.length > 0 && aanbodItems.length === 0);
      })
      .catch(function () {
        partnerItems = [];
      });
  }

  if (filterEl) {
    filterEl.addEventListener("change", function () {
      refreshMarkers(false);
    });
  }

  form.addEventListener("submit", function (ev) {
    ev.preventDefault();
    var q = document.getElementById("zoek-q").value.trim();
    var type = document.getElementById("zoek-type").value;
    status.textContent = "Zoeken…";
    aanbodItems = [];
    aanbodLayer.clearLayers();
    list.querySelectorAll('[data-kind="aanbod"]').forEach(function (el) {
      el.remove();
    });

    var url = api + "?q=" + encodeURIComponent(q) + "&type=" + encodeURIComponent(type);
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
        if (meta && meta.zoek_lat != null) {
          map.setView([meta.zoek_lat, meta.zoek_lon], 11);
        }
        if (!aanbodItems.length) {
          status.dataset.empty =
            "Nog geen aanbod in de buurt. Volgt binnenkort — of zet zelf iets online.";
        } else {
          status.dataset.empty = "";
        }
        refreshMarkers(true);
        if (!aanbodItems.length && !partnerItems.length) {
          status.textContent =
            "Nog geen aanbod in de buurt. Volgt binnenkort — of zet zelf iets online.";
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
})();
