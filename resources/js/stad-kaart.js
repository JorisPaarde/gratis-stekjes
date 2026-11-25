import { escapeHtml, popupAanbodHtml, registerPlek, plekKey as detailPlekKey } from "./aanbod-detail.js";

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

  var aanbodIcon = L.divIcon({
    className: "partner-marker partner-marker--aanbod",
    html: '<span class="partner-marker-dot" style="background:#1f4d2b" title="Aanbod"></span>',
    iconSize: [18, 18],
    iconAnchor: [9, 9],
    popupAnchor: [0, -8],
  });
  var aanbodGroepen = {};
  (data.aanbod || []).forEach(function (item) {
    if (item.lat == null || item.lon == null) return;
    var key = String(item.user_id || item.id);
    if (!aanbodGroepen[key]) {
      aanbodGroepen[key] = {
        voornaam: item.voornaam,
        locatie_label: item.locatie_label,
        lat: item.lat,
        lon: item.lon,
        items: [],
      };
    }
    aanbodGroepen[key].items.push(item);
  });
  Object.keys(aanbodGroepen).forEach(function (key) {
    var group = aanbodGroepen[key];
    L.marker([group.lat, group.lon], { icon: aanbodIcon })
      .bindPopup(popupAanbodHtml(group), { className: "gp-leaflet-popup", maxWidth: 320, maxHeight: 320 })
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
    registerPlek(plek);
    L.marker([plek.lat, plek.lng], { icon: icon })
      .bindPopup(
        "<strong>" +
          escapeHtml(plek.naam || "Deelplek") +
          "</strong><br>" +
          escapeHtml(plek.type_label || plek.type || "") +
          (plek.plaats ? " · " + escapeHtml(plek.plaats) : "") +
          '<p class="gp-popup-acties"><button type="button" class="zoek-regel-meer-knop" data-plek-open="' +
          escapeHtml(detailPlekKey(plek)) +
          '" aria-haspopup="dialog">Meer info</button></p>',
        { className: "gp-leaflet-popup", maxWidth: 280, maxHeight: 280 }
      )
      .addTo(map);
  });
})();
