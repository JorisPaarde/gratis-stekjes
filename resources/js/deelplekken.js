/* Deelplekken: eigen kaart + markers (ruit-icoon) + tijdelijke evenementen + clustering. */
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

  function escapeHtml(s) {
    return String(s)
      .replace(/&/g, "&amp;")
      .replace(/</g, "&lt;")
      .replace(/>/g, "&gt;")
      .replace(/"/g, "&quot;");
  }

  function isEvenement(item) {
    return item && (item.soort === "evenement" || item.kind === "evenement" || item.tijdelijk);
  }

  function clusterLabel(count, evenementCount) {
    var label = count + (count === 1 ? " plek" : " plekken");
    if (evenementCount > 0) {
      label +=
        ", waarvan " +
        evenementCount +
        (evenementCount === 1 ? " evenement" : " evenementen");
    }
    return label;
  }

  function clusterIcon(cluster) {
    var markers = cluster.getAllChildMarkers();
    var count = markers.length;
    var evenementCount = 0;
    markers.forEach(function (m) {
      if (m.options && m.options.gpEvenement) evenementCount++;
    });
    var size = count < 10 ? "sm" : count < 40 ? "md" : "lg";
    var px = size === "sm" ? 36 : size === "md" ? 44 : 54;
    var label = clusterLabel(count, evenementCount);
    var classes =
      "gp-cluster-bubble gp-cluster-bubble--" +
      size +
      (evenementCount ? " gp-cluster-bubble--evenement" : "");
    return L.divIcon({
      html:
        '<span class="' +
        classes +
        '" role="img" aria-label="' +
        escapeHtml(label) +
        '" title="' +
        escapeHtml(label) +
        '"><span class="gp-cluster-count">' +
        count +
        "</span>" +
        (evenementCount
          ? '<span class="gp-cluster-event-badge" aria-hidden="true"></span>'
          : "") +
        "</span>",
      className: "gp-cluster",
      iconSize: L.point(px, px),
      iconAnchor: [px / 2, px / 2],
    });
  }

  function makeDeelplekClusterGroup() {
    if (typeof L.markerClusterGroup !== "function") {
      return L.layerGroup();
    }
    return L.markerClusterGroup({
      showCoverageOnHover: false,
      zoomToBoundsOnClick: true,
      spiderfyOnMaxZoom: true,
      maxClusterRadius: 55,
      chunkedLoading: true,
      // Anders haalt autoPan de pin uit beeld, verwijdert de cluster-laag hem en sluit de popup (touch).
      removeOutsideVisibleBounds: false,
      iconCreateFunction: clusterIcon,
    });
  }

  var layer = makeDeelplekClusterGroup().addTo(map);

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

  function queryUrl() {
    var params = new URLSearchParams();
    if (cfg.type) params.set("type", cfg.type);
    if (cfg.provincie) params.set("provincie", cfg.provincie);
    if (cfg.plaats) params.set("plaats", cfg.plaats);
    var q = params.toString();
    return (cfg.api || "/api/deelplekken") + (q ? "?" + q : "");
  }

  // Ruimte t.o.v. zoomcontrols (+/−, linksboven) zodat popups op smalle viewports
  // niet onder de knoppen vallen.
  var popupOpts = {
    className: "gp-leaflet-popup",
    autoPan: true,
    autoPanPaddingTopLeft: L.point(50, 56),
    autoPanPaddingBottomRight: L.point(24, 24),
    offset: L.point(0, -6),
    // Smal genoeg voor de 390-kaart (358px) incl. autoPan-padding; lange inhoud scrollt.
    maxWidth: 260,
    maxHeight: 280,
  };

  function buildPopup(item) {
    var evenement = isEvenement(item);
    var label = item.type_label || typeLabels[item.type] || item.type;
    var html = '<div class="gp-popup">';
    html += '<strong class="gp-popup-titel">' + escapeHtml(item.naam) + "</strong>";
    if (evenement) {
      html += '<span class="deelplek-popup-badge">Tijdelijk</span>';
      if (item.datum_label) {
        html += '<p class="gp-popup-meta">' + escapeHtml(item.datum_label) + "</p>";
      }
    }
    html +=
      '<p class="gp-popup-meta">' +
      escapeHtml(label) +
      (item.plaats ? " · " + escapeHtml(item.plaats) : "") +
      "</p>";
    if (item.adres) {
      html += '<p class="gp-popup-meta">' + escapeHtml(item.adres) + "</p>";
    }
    if (evenement && item.omschrijving) {
      html += '<p class="gp-popup-meta">' + escapeHtml(item.omschrijving) + "</p>";
    }
    if (item.bron_url) {
      html +=
        '<a class="gp-popup-link" href="' +
        escapeHtml(item.bron_url) +
        '" rel="noopener noreferrer" target="_blank">' +
        (evenement ? "Bron" : "Website") +
        "</a>";
    }
    html += "</div>";
    return html;
  }

  function openMarker(m) {
    if (!m) return;
    if (typeof layer.zoomToShowLayer === "function") {
      layer.zoomToShowLayer(m, function () {
        m.openPopup();
      });
      return;
    }
    map.setView(m.getLatLng(), Math.max(map.getZoom(), 14));
    m.openPopup();
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
    if (best && bestD < 0.01) {
      openMarker(best);
    } else {
      map.setView([lat, lng], 14);
    }
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
      var batch = [];
      items.forEach(function (item) {
        var lat = item.lat;
        var lon = item.lng != null ? item.lng : item.lon;
        if (lat == null || lon == null) return;
        var evenement = isEvenement(item);
        var m = L.marker([lat, lon], {
          icon: deelplekIcon(item.type, evenement),
          zIndexOffset: evenement ? 200 : 0,
          gpEvenement: !!evenement,
        }).bindPopup(buildPopup(item), popupOpts);
        batch.push(m);
        markers.push(m);
        bounds.push([lat, lon]);
      });
      if (typeof layer.addLayers === "function") {
        layer.addLayers(batch);
      } else {
        batch.forEach(function (m) {
          layer.addLayer(m);
        });
      }
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
