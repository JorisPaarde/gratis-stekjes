/* Service worker — offline shell, nooit API/berichten cachen. Versie via query. */
const STEKJES_SW_VERSION = new URL(self.location.href).searchParams.get("v") || "0";
const SHELL_CACHE = "stekjes-shell-" + STEKJES_SW_VERSION;

const SHELL = [
  "/",
  "/index.html",
  "/hoe-werkt-het/",
  "/doe-mee/",
  "/aanbod/",
  "/stektips/",
  "/hulpmiddelen/",
  "/links/",
  "/over/",
  "/privacy/",
  "/assets/css/styles.css",
  "/assets/js/main.js",
  "/assets/js/site-config.js",
  "/manifest.webmanifest",
  "/offline.html",
];

self.addEventListener("install", (event) => {
  event.waitUntil(
    caches.open(SHELL_CACHE).then((cache) => cache.addAll(SHELL)).then(() => self.skipWaiting())
  );
});

self.addEventListener("activate", (event) => {
  event.waitUntil(
    caches.keys().then((keys) =>
      Promise.all(keys.filter((k) => k.startsWith("stekjes-shell-") && k !== SHELL_CACHE).map((k) => caches.delete(k)))
    ).then(() => self.clients.claim())
  );
});

function isApiOrPrivate(url) {
  const p = url.pathname;
  return (
    p.startsWith("/api/") ||
    p.startsWith("/account/") ||
    p.startsWith("/beheer/") ||
    p.startsWith("/helpers/") ||
    p.endsWith(".php") ||
    p === "/cron.php"
  );
}

self.addEventListener("fetch", (event) => {
  const req = event.request;
  if (req.method !== "GET") return;
  const url = new URL(req.url);
  if (url.origin !== self.location.origin) return;
  if (isApiOrPrivate(url)) return; // netwerk alleen, nooit cachen

  event.respondWith(
    caches.match(req).then((cached) => {
      const network = fetch(req)
        .then((res) => {
          if (res.ok && req.mode === "navigate") {
            const copy = res.clone();
            caches.open(SHELL_CACHE).then((c) => c.put(req, copy));
          }
          return res;
        })
        .catch(() => cached || caches.match("/offline.html"));
      return cached || network;
    })
  );
});
