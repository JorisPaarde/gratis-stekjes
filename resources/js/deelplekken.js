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
      zaadbibliotheek: "Zadenbibliotheek",
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
    setTimeout(function () { if (!heeftGezoomd) fitNlBe(); else map.invalidateSize(); }, 50);
    setTimeout(function () { if (!heeftGezoomd) fitNlBe(); else map.invalidateSize(); }, 400);
    setTimeout(function () { if (!heeftGezoomd) fitNlBe(); else map.invalidateSize(); }, 1000);
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

  var markers = [];
  var heeftGezoomd = false;
  var heeftFilter = !!(cfg.type || cfg.provincie || cfg.plaats);

  // «Op de kaart» in de lijst: inzoomen en popup openen.
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
      if (d < bestD) { bestD = d; best = m; }
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
            '" rel="noopener noreferrer" target="_blank">Website</a>';
        }
        var m = L.marker([lat, lon], { icon: deelplekIcon(item.type) }).bindPopup(
          popup
        );
        layer.addLayer(m);
        markers.push(m);
        bounds.push([lat, lon]);
      });
      if (status) {
        status.textContent =
          items.length + (items.length === 1 ? " plek" : " plekken");
      }
      // Zonder filter: beginuitsnede NL + België. Met filter: naar de gevonden plekken.
      if (heeftFilter && bounds.length) {
        heeftGezoomd = true;
        map.fitBounds(bounds, { padding: [30, 30], maxZoom: 12 });
      }
    })
    .catch(function () {
      if (status) status.textContent = "De plekken op de kaart laden is niet gelukt.";
    });
})();
