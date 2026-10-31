/* Deelplekken: eigen kaart + markers (ruit-icoon). */
(function () {
  var mapEl = document.getElementById("deelplekken-kaart");
  var status = document.getElementById("deelplekken-status");
  if (!mapEl || typeof L === "undefined") return;

  var cfg = window.STEKJES_DEELPLEKKEN || {};
  var typeLabels = Object.assign(
    {
      plantenasiel: "Plantenasiel",
      plantenbieb: "Plantenbieb",
      ruilkast: "Ruilkast",
      stekjesruilpunt: "Stekjesruilpunt",
      zaadbibliotheek: "Zaadbibliotheek",
    },
    cfg.types || {}
  );

  var markerColors = {
    plantenasiel: "#e8833a",
    plantenbieb: "#3d6b8a",
    ruilkast: "#6b4c2f",
    stekjesruilpunt: "#5a6b3d",
    zaadbibliotheek: "#8a6a3a",
  };

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
    attribution:
      '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a>',
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
  map.whenReady(function () {
    // Na layout / late size: opnieuw contain-zoom.
    setTimeout(fitNlBe, 50);
    setTimeout(fitNlBe, 400);
    setTimeout(fitNlBe, 1000);
  });
  var layer = L.layerGroup().addTo(map);

  function deelplekIcon(type) {
    var color = markerColors[type] || "#1f4d2b";
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

  fetch(queryUrl(), {
    credentials: "same-origin",
    headers: { Accept: "application/json" },
  })
    .then(function (r) {
      return r.json().then(function (data) {
        if (!r.ok) throw new Error((data && data.error) || "Laden mislukt");
        return data;
      });
    })
    .then(function (data) {
      if (data && data.types) {
        typeLabels = Object.assign(typeLabels, data.types);
      }
      var items = (data && data.items) || [];
      var bounds = [];
      items.forEach(function (item) {
        var lat = item.lat;
        var lon = item.lng != null ? item.lng : item.lon;
        if (lat == null || lon == null) return;
        var label = item.type_label || typeLabels[item.type] || item.type;
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
        var m = L.marker([lat, lon], { icon: deelplekIcon(item.type) }).bindPopup(
          popup
        );
        layer.addLayer(m);
        bounds.push([lat, lon]);
      });
      if (status) {
        status.textContent =
          items.length + (items.length === 1 ? " plek" : " plekken");
      }
      // Bewaar beginuitsnede NL + België; zoom niet naar alleen NL-markers.
    })
    .catch(function () {
      if (status) status.textContent = "Kaartmarkers laden mislukt.";
    });
})();
