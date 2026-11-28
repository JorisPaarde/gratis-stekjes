import { escapeHtml, popupAanbodHtml, registerPlek, plekKey as detailPlekKey } from "./aanbod-detail.js";

/* Kaartuitsnede voor /gratis-planten/{stad}. */
(function () {
  var data = window.STEKJES_STAD;
  var el = document.getElementById("stad-kaart");
  if (!data || !el || typeof L === "undefined") {
    return;
  }

  var map = L.map(el).setView([data.lat, data.lng], 11);
  // Zelfde popupgedrag als op /deelplekken: ruimte t.o.v. de zoomknoppen (linksboven) en de rand,
  // en niet hoger dan de (kleine) stadskaart.
  var popupOpts = {
    className: "gp-leaflet-popup gp-leaflet-popup--stad",
    autoPan: true,
    autoPanPaddingTopLeft: L.point(50, 56),
    autoPanPaddingBottomRight: L.point(24, 24),
    offset: L.point(0, -6),
    maxWidth: 320,
    // Geen maxHeight: alleen de aanbodlijst scrolt (zie .gp-leaflet-popup--stad in aanbod.css),
    // titel en sluitknop blijven staan.
  };
  L.tileLayer("https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png", {
    maxZoom: 18,
    attribution: "&copy; OpenStreetMap",
  }).addTo(map);

  // Lange aanbodlijst: alleen de lijst scrolt; een zachte fade onderaan zolang er nog meer is.
  // De popupinhoud wordt vooraf als element gebouwd, zodat Leaflet de juiste hoogte meet (autoPan).
  function aanbodPopupEl(group) {
    var box = document.createElement("div");
    box.innerHTML = popupAanbodHtml(group);
    var ul = box.querySelector(".zoek-regels--popup");
    if (!ul) return box;
    var wrap = document.createElement("div");
    wrap.className = "gp-popup-lijst";
    ul.parentNode.insertBefore(wrap, ul);
    wrap.appendChild(ul);
    ul.setAttribute("tabindex", "0");
    ul.setAttribute("role", "group");
    ul.setAttribute("aria-label", "Aanbod, scroll voor meer");
    function update() {
      wrap.classList.toggle("heeft-meer", ul.scrollHeight - ul.scrollTop - ul.clientHeight > 2);
    }
    ul.addEventListener("scroll", update, { passive: true });
    if (typeof ResizeObserver !== "undefined") new ResizeObserver(update).observe(ul);
    box._gpUpdate = update;
    return box;
  }
  map.on("popupopen", function (ev) {
    var c = ev.popup.getContent && ev.popup.getContent();
    if (c && c._gpUpdate) window.requestAnimationFrame(c._gpUpdate);
  });

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
  var markers = [];
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
    markers.push(
      L.marker([group.lat, group.lon], { icon: aanbodIcon })
        .bindPopup(aanbodPopupEl(group), popupOpts)
        .addTo(map)
    );
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
    markers.push(
      L.marker([plek.lat, plek.lng], { icon: icon })
        .bindPopup(
          '<div class="gp-popup"><strong class="gp-popup-titel">' +
            escapeHtml(plek.naam || "Deelplek") +
            '</strong><p class="gp-popup-meta">' +
            escapeHtml(plek.type_label || plek.type || "") +
            (plek.plaats ? " · " + escapeHtml(plek.plaats) : "") +
            '</p><p class="gp-popup-acties"><button type="button" class="zoek-regel-meer-knop" data-plek-open="' +
            escapeHtml(detailPlekKey(plek)) +
            '" aria-haspopup="dialog">Meer info</button></p></div>',
          popupOpts
        )
        .addTo(map)
    );
  });

  // Alle markers in beeld (met rand), ook op de kleine kaart van 390 px; niet verder inzoomen dan 14.
  if (markers.length) {
    var bounds = L.latLngBounds([[data.lat, data.lng]]);
    markers.forEach(function (m) {
      bounds.extend(m.getLatLng());
    });
    map.fitBounds(bounds, {
      paddingTopLeft: L.point(56, 40),
      paddingBottomRight: L.point(32, 32),
      maxZoom: 14,
    });
  }
})();
