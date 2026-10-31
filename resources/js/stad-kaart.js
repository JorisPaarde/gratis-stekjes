/* Kaartuitsnede voor /gratis-planten/{stad}. */
(function () {
  var data = window.STEKJES_STAD;
  var el = document.getElementById("stad-kaart");
  if (!data || !el || typeof L === "undefined") {
    return;
  }

  var map = L.map(el).setView([data.lat, data.lng], 11);
  L.tileLayer("https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png", {
    maxZoom: 18,
    attribution: "&copy; OpenStreetMap",
  }).addTo(map);

  var radiusM = Math.max(1000, (Number(data.straal_km) || 12) * 1000);
  L.circle([data.lat, data.lng], {
    radius: radiusM,
    color: "#1f4d2b",
    weight: 1,
    fillColor: "#1f4d2b",
    fillOpacity: 0.06,
  }).addTo(map);

  L.circleMarker([data.lat, data.lng], {
    radius: 6,
    color: "#1f4d2b",
    fillColor: "#E8833A",
    fillOpacity: 1,
    weight: 2,
  })
    .bindPopup(data.naam || "Stad")
    .addTo(map);

  (data.aanbod || []).forEach(function (item) {
    if (item.lat == null || item.lon == null) {
      return;
    }
    // Div-icon i.p.v. Leaflet-default (CDN-bitmap; CSP img-src blokkeert dat).
    var aanbodIcon = L.divIcon({
      className: "partner-marker partner-marker--aanbod",
      html: '<span class="partner-marker-dot" style="background:#1f4d2b" title="Aanbod"></span>',
      iconSize: [18, 18],
      iconAnchor: [9, 9],
      popupAnchor: [0, -8],
    });
    L.marker([item.lat, item.lon], { icon: aanbodIcon })
      .bindPopup(
        "<strong>" +
          (item.voornaam || "Iemand") +
          "</strong><br>" +
          (item.tekst || "").replace(/</g, "&lt;") +
          "<br><span class='muted'>" +
          (item.locatie_label || "") +
          "</span>"
      )
      .addTo(map);
  });

  (data.deelplekken || []).forEach(function (plek) {
    if (plek.lat == null || plek.lng == null) {
      return;
    }
    var icon = L.divIcon({
      className: "partner-marker",
      html: '<span class="partner-marker-dot partner-marker-dot--deelplek" aria-hidden="true"></span>',
      iconSize: [16, 16],
      iconAnchor: [8, 8],
    });
    L.marker([plek.lat, plek.lng], { icon: icon })
      .bindPopup(
        "<strong>" +
          (plek.naam || "Deelplek") +
          "</strong><br>" +
          (plek.type_label || plek.type || "") +
          (plek.plaats ? " · " + plek.plaats : "")
      )
      .addTo(map);
  });
})();
