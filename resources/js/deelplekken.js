/* Deelplekken: eigen kaart + markers (ruit-icoon) + tijdelijke evenementen. */
(function () {
  var mapEl = document.getElementById("deelplekken-kaart");
  var status = document.getElementById("deelplekken-status");
  var listEl = document.getElementById("deelplekken-lijst");
  var meerBtn = document.getElementById("deelplekken-laad-meer");
  var meerStatus = document.getElementById("deelplekken-meer-status");

  // Lijst: eerst 12, knop «Laad meer» toont de volgende 12. Kaartpins blijven allemaal.
  if (meerBtn && listEl) {
    meerBtn.addEventListener("click", function () {
      var stap = parseInt(meerBtn.getAttribute("data-stap") || "12", 10) || 12;
      var hidden = listEl.querySelectorAll(".zoek-item.is-lijst-verborgen, .zoek-item[hidden]");
      var shown = 0;
      var firstNew = null;
      hidden.forEach(function (li) {
        if (shown >= stap) return;
        li.hidden = false;
        li.classList.remove("is-lijst-verborgen");
        if (!firstNew) firstNew = li;
        shown++;
      });
      var rest = listEl.querySelectorAll(".zoek-item.is-lijst-verborgen, .zoek-item[hidden]").length;
      if (meerStatus) {
        meerStatus.textContent =
          shown > 0
            ? shown + (shown === 1 ? " plek extra getoond." : " plekken extra getoond.")
            : "";
      }
      if (firstNew) {
        var focusTarget = firstNew.querySelector("h2, a, button") || firstNew;
        if (focusTarget.getAttribute && !focusTarget.getAttribute("tabindex") && focusTarget.tagName === "H2") {
          focusTarget.setAttribute("tabindex", "-1");
        }
        if (focusTarget.focus) focusTarget.focus();
      }
      if (rest === 0) {
        meerBtn.hidden = true;
        meerBtn.setAttribute("aria-hidden", "true");
        var wrap = meerBtn.closest(".deelplekken-meer");
        if (wrap) wrap.hidden = true;
      }
    });
  }

  if (!mapEl || typeof L === "undefined") return;

  var cfg = window.STEKJES_DEELPLEKKEN || {};
  var typeLabels = Object.assign(
    {
      plantenasiel: "Plantenasiel",
      plantenbieb: "Plantenbieb",
      ruilkast: "Ruilkast",
      stekjesruilpunt: "Stekjesruilpunt",
      zaadbibliotheek: "Zadenbibliotheek",
      voedselbos: "Voedselbos",
      tuinvereniging: "Tuinvereniging",
      bomenhub: "Bomen-uitdeeldag",
      stekchallenge: "STEKchallenge",
    },
    cfg.types || {}
  );

  var markerColors = {
    plantenasiel: "#e8833a",
    plantenbieb: "#3d6b8a",
    ruilkast: "#6b4c2f",
    stekjesruilpunt: "#5a6b3d",
    zaadbibliotheek: "#8a6a3a",
    voedselbos: "#163820",
    tuinvereniging: "#3d6b4a",
    bomenhub: "#b54a2a",
    stekchallenge: "#b54a2a",
  };

  var nlBeBounds = [
    [49.45, 2.4],
    [53.55, 7.25],
  ];
  var nlBeFitOpts = { padding: [4, 4], maxZoom: 8 };
  var map = L.map(mapEl, { zoomSnap: 0.25 }).fitBounds(nlBeBounds, nlBeFitOpts);
  L.tileLayer("https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png", {
    attribution:
      '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a>',
    maxZoom: 18,
  }).addTo(map);
  function fitNlBe() {
    map.invalidateSize();
    var bounds = L.latLngBounds(nlBeBounds);
    var zoom = map.getBoundsZoom(bounds, false, L.point(4, 4));
    if (isFinite(zoom)) {
      map.setView(bounds.getCenter(), Math.min(zoom, 8));
    } else {
      map.fitBounds(nlBeBounds, nlBeFitOpts);
    }
  }
  map.whenReady(function () {
    setTimeout(function () {
      if (!heeftGezoomd) fitNlBe();
      else map.invalidateSize();
    }, 50);
    setTimeout(function () {
      if (!heeftGezoomd) fitNlBe();
      else map.invalidateSize();
    }, 400);
    setTimeout(function () {
      if (!heeftGezoomd) fitNlBe();
      else map.invalidateSize();
    }, 1000);
  });
  var layer = L.layerGroup().addTo(map);

  function isEvenement(item) {
    return item && (item.soort === "evenement" || item.kind === "evenement" || item.tijdelijk);
  }

  function deelplekIcon(type, evenement) {
    var color = evenement ? "#b54a2a" : markerColors[type] || "#1f4d2b";
    var mod = evenement ? "evenement" : type;
    return L.divIcon({
      className: "deelplek-marker deelplek-marker--" + mod,
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

  function escapeHtml(s) {
    return String(s)
      .replace(/&/g, "&amp;")
      .replace(/</g, "&lt;")
      .replace(/>/g, "&gt;")
      .replace(/"/g, "&quot;");
  }

  function queryUrl() {
    var params = new URLSearchParams();
    if (cfg.type) params.set("type", cfg.type);
    if (cfg.provincie) params.set("provincie", cfg.provincie);
    if (cfg.plaats) params.set("plaats", cfg.plaats);
    var q = params.toString();
    return (cfg.api || "/api/deelplekken") + (q ? "?" + q : "");
  }

  function buildPopup(item) {
    var evenement = isEvenement(item);
    var label = item.type_label || typeLabels[item.type] || item.type;
    var html = "<strong>" + escapeHtml(item.naam) + "</strong>";
    if (evenement) {
      html += '<br><span class="deelplek-popup-badge">Tijdelijk</span>';
      if (item.datum_label) {
        html += "<br>" + escapeHtml(item.datum_label);
      }
    }
    html +=
      "<br>" +
      escapeHtml(label) +
      (item.plaats ? " · " + escapeHtml(item.plaats) : "");
    if (item.adres) {
      html += "<br>" + escapeHtml(item.adres);
    }
    if (evenement && item.omschrijving) {
      html += "<br>" + escapeHtml(item.omschrijving);
    }
    if (item.bron_url) {
      html +=
        '<br><a href="' +
        escapeHtml(item.bron_url) +
        '" rel="noopener noreferrer" target="_blank">' +
        (evenement ? "Bron" : "Website") +
        "</a>";
    }
    return html;
  }

  var markers = [];
  var heeftGezoomd = false;
  var heeftFilter = !!(cfg.type || cfg.provincie || cfg.plaats);

  document.addEventListener("click", function (ev) {
    var btn = ev.target.closest ? ev.target.closest(".zoek-op-kaart[data-lat]") : null;
    if (!btn) return;
    var lat = parseFloat(btn.getAttribute("data-lat"));
    var lng = parseFloat(btn.getAttribute("data-lng"));
    if (!isFinite(lat) || !isFinite(lng)) return;
    heeftGezoomd = true;
    var best = null;
    var bestD = Infinity;
    markers.forEach(function (m) {
      var p = m.getLatLng();
      var d = Math.abs(p.lat - lat) + Math.abs(p.lng - lng);
      if (d < bestD) {
        bestD = d;
        best = m;
      }
    });
    map.setView([lat, lng], 14);
    if (best && bestD < 0.01) best.openPopup();
    if (mapEl && mapEl.scrollIntoView) mapEl.scrollIntoView({ behavior: "smooth", block: "center" });
  });

  fetch(queryUrl(), {
    credentials: "same-origin",
    headers: { Accept: "application/json" },
  })
    .then(function (r) {
      return r.json().then(function (data) {
        if (!r.ok) throw new Error((data && data.error) || "Laden is niet gelukt");
        return data;
      });
    })
    .then(function (data) {
      if (data && data.types) {
        typeLabels = Object.assign(typeLabels, data.types);
      }
      if (data && data.evenement_types) {
        typeLabels = Object.assign(typeLabels, data.evenement_types);
      }
      var items = (data && data.items) || [];
      var bounds = [];
      items.forEach(function (item) {
        var lat = item.lat;
        var lon = item.lng != null ? item.lng : item.lon;
        if (lat == null || lon == null) return;
        var evenement = isEvenement(item);
        var m = L.marker([lat, lon], {
          icon: deelplekIcon(item.type, evenement),
          zIndexOffset: evenement ? 200 : 0,
        }).bindPopup(buildPopup(item));
        layer.addLayer(m);
        markers.push(m);
        bounds.push([lat, lon]);
      });
      if (status) {
        status.textContent = items.length
          ? "Deelplekken op de kaart geladen"
          : "Geen deelplekken gevonden";
      }
      if (heeftFilter && bounds.length) {
        heeftGezoomd = true;
        map.fitBounds(bounds, { padding: [30, 30], maxZoom: 12 });
      }
    })
    .catch(function () {
      if (status) status.textContent = "De plekken op de kaart laden is niet gelukt.";
    });
})();
