/* Zoeken: kaart + lijst. Versie 0.1.0 */
(function () {
  var form = document.getElementById("zoek-form");
  var list = document.getElementById("zoek-lijst");
  var status = document.getElementById("zoek-status");
  var mapEl = document.getElementById("kaart");
  if (!form || !list || typeof L === "undefined") return;

  var map = L.map(mapEl).setView([52.1, 5.2], 7);
  L.tileLayer("https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png", {
    attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a>',
    maxZoom: 18,
  }).addTo(map);
  var layer = L.layerGroup().addTo(map);

  var cfg = window.STEKJES_SITE || {};
  var api = cfg.api_zoeken || "/api/zoeken.php";

  form.addEventListener("submit", function (ev) {
    ev.preventDefault();
    var q = document.getElementById("zoek-q").value.trim();
    var type = document.getElementById("zoek-type").value;
    status.textContent = "Zoeken…";
    list.innerHTML = "";
    layer.clearLayers();

    var url = api + "?q=" + encodeURIComponent(q) + "&type=" + encodeURIComponent(type);
    fetch(url, { credentials: "same-origin", headers: { Accept: "application/json" } })
      .then(function (r) {
        return r.json().then(function (data) {
          if (!r.ok) throw new Error((data && data.error) || "Zoeken mislukt");
          return data;
        });
      })
      .then(function (data) {
        var items = (data && data.items) || [];
        var meta = data && data.meta;
        if (meta && meta.zoek_lat != null) {
          map.setView([meta.zoek_lat, meta.zoek_lon], 11);
        }
        if (!items.length) {
          status.textContent = "Nog geen aanbod in de buurt. Volgt binnenkort — of zet zelf iets online.";
          return;
        }
        status.textContent = items.length + " resultaten";
        var bounds = [];
        items.forEach(function (item) {
          var li = document.createElement("li");
          li.className = "zoek-item";
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
            '<p><a class="btn btn-secondary" href="/account/bericht-starten.php?aanbod_id=' +
            encodeURIComponent(item.id) +
            "&user_id=" +
            encodeURIComponent(item.user_id) +
            '">Stuur bericht</a></p>';
          list.appendChild(li);
          if (item.lat != null && item.lon != null) {
            var m = L.marker([item.lat, item.lon]).bindPopup(
              escapeHtml(item.voornaam) + " — " + escapeHtml(item.type)
            );
            layer.addLayer(m);
            bounds.push([item.lat, item.lon]);
          }
        });
        if (bounds.length) map.fitBounds(bounds, { padding: [24, 24], maxZoom: 12 });
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
})();
