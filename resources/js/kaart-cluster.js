/**
 * Leaflet.markercluster-opties voor deelplekmarkers op /aanbod en /deelplekken.
 *
 * maxClusterRadius 40 (was 55): op de beginzoom van Nederland (mobiel zoom 6)
 * zie je zo meer losse bolletjes, zonder dat ze onleesbaar over elkaar vallen.
 * Geen disableClusteringAtZoom: evenementen op dezelfde coördinaat als een
 * deelplek blijven bij maximale zoom uitspreiden (spiderfyOnMaxZoom).
 */
export function deelplekClusterOptions(iconCreateFunction, extra) {
  var options = {
    showCoverageOnHover: false,
    zoomToBoundsOnClick: true,
    spiderfyOnMaxZoom: true,
    maxClusterRadius: 40,
    chunkedLoading: true,
    iconCreateFunction: iconCreateFunction,
  };
  if (extra) {
    Object.assign(options, extra);
  }
  return options;
}
