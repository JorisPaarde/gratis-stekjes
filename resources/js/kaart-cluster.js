/**
 * Leaflet.markercluster-opties voor deelplekmarkers op /aanbod en /deelplekken.
 *
 * Beginuitsnede: NL + België, strak om de plekken (niet half Duitsland/Frankrijk).
 * Op die zoom (~6.5) is maxClusterRadius 52 px geografisch vergelijkbaar met
 * 40 px op de oude zoom 6: meer losse regio's dan de oude 6 bolletjes, zonder
 * een volle stapel. Vanaf zoom 8 weer 40 px. Clustering blijft tot de maximale
 * zoom aan, zodat evenementen op dezelfde coördinaat uitspreiden (spiderfy).
 *
 * Iconen zijn op smalle schermen 32–44 px (was 36–54) met een dunnere
 * evenement-ring, zodat ze in de clusterstraal passen.
 */
export var NL_BE_BOUNDS = [
  [50.28, 2.92],
  [53.58, 7.16],
];
export var NL_BE_FIT = { padding: [4, 4], maxZoom: 8 };
export var NL_BE_ZOOM_PAD = [4, 4];

export function clusterIconPx(count) {
  var narrow = typeof window !== "undefined" && window.innerWidth < 900;
  if (narrow) {
    return count < 10 ? 32 : count < 40 ? 38 : 44;
  }
  return count < 10 ? 36 : count < 40 ? 42 : 48;
}

export function deelplekClusterOptions(iconCreateFunction, extra) {
  var options = {
    showCoverageOnHover: false,
    zoomToBoundsOnClick: true,
    spiderfyOnMaxZoom: true,
    maxClusterRadius: function (zoom) {
      if (zoom >= 8) return 40;
      if (zoom >= 7) return 48;
      return 52;
    },
    chunkedLoading: true,
    iconCreateFunction: iconCreateFunction,
  };
  if (extra) {
    Object.assign(options, extra);
  }
  return options;
}

/**
 * Schuift zichtbare markers/clusters een paar pixels uit elkaar als ze
 * elkaar overlappen (inclusief de evenement-ring). Leaflet-positie blijft
 * leidend; alleen margin, en opnieuw bij zoom/pan.
 */
export function separateMapIcons(map) {
  if (!map || typeof map.getContainer !== "function") return;
  var root = map.getContainer();
  var icons = root.querySelectorAll(".leaflet-marker-pane .leaflet-marker-icon");
  var i;
  for (i = 0; i < icons.length; i++) {
    icons[i].style.marginLeft = "0px";
    icons[i].style.marginTop = "0px";
  }
  var GAP = 2;
  var iter;
  for (iter = 0; iter < 6; iter++) {
    var items = [];
    for (i = 0; i < icons.length; i++) {
      var el = icons[i];
      var r = el.getBoundingClientRect();
      if (!r.width) continue;
      var ev = el.querySelector && el.querySelector(".gp-cluster-bubble--evenement");
      var extra = ev ? 2 : 0;
      items.push({
        el: el,
        cx: r.left + r.width / 2,
        cy: r.top + r.height / 2,
        rad: Math.max(r.width, r.height) / 2 + extra,
      });
    }
    var moved = false;
    var a, b, j, dx, dy, dist, minDist, push, ux, uy;
    for (i = 0; i < items.length; i++) {
      for (j = i + 1; j < items.length; j++) {
        a = items[i];
        b = items[j];
        dx = b.cx - a.cx;
        dy = b.cy - a.cy;
        dist = Math.hypot(dx, dy) || 0.01;
        minDist = a.rad + b.rad + GAP;
        if (dist >= minDist) continue;
        push = (minDist - dist) / 2;
        ux = dx / dist;
        uy = dy / dist;
        a.el.style.marginLeft =
          parseFloat(a.el.style.marginLeft || "0") - ux * push + "px";
        a.el.style.marginTop =
          parseFloat(a.el.style.marginTop || "0") - uy * push + "px";
        b.el.style.marginLeft =
          parseFloat(b.el.style.marginLeft || "0") + ux * push + "px";
        b.el.style.marginTop =
          parseFloat(b.el.style.marginTop || "0") + uy * push + "px";
        moved = true;
      }
    }
    if (!moved) break;
  }
  clampIconsToMap(root, icons);
}

function clampIconsToMap(root, icons) {
  var box = root.getBoundingClientRect();
  var inset = 2;
  var i;
  for (i = 0; i < icons.length; i++) {
    var el = icons[i];
    var r = el.getBoundingClientRect();
    if (!r.width) continue;
    var dx = 0;
    var dy = 0;
    if (r.left < box.left + inset) dx += box.left + inset - r.left;
    if (r.right > box.right - inset) dx -= r.right - (box.right - inset);
    if (r.top < box.top + inset) dy += box.top + inset - r.top;
    if (r.bottom > box.bottom - inset) dy -= r.bottom - (box.bottom - inset);
    if (dx) el.style.marginLeft = parseFloat(el.style.marginLeft || "0") + dx + "px";
    if (dy) el.style.marginTop = parseFloat(el.style.marginTop || "0") + dy + "px";
  }
}

export function bindIconSeparation(map, clusterLayer) {
  if (!map) return;
  var run = function () {
    separateMapIcons(map);
  };
  map.on("zoomend moveend", run);
  if (clusterLayer && typeof clusterLayer.on === "function") {
    clusterLayer.on("animationend", run);
  }
  setTimeout(run, 0);
  setTimeout(run, 450);
  setTimeout(run, 1100);
}
