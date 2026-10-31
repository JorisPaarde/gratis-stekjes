/* Gratis planten — service worker (handgeschreven, geen dependency).
 * Cacheversie via ?v= uit VERSION (registratie in app.js).
 * Nooit: account (incl. ervaringen plaatsen), berichten, beheer (incl. agenda),
 * POST, /cron, /deploy, /media* (aanbod-/ervaringfoto’s), /media-uploads.
 */
const GP_SW_VERSION = new URL(self.location.href).searchParams.get("v") || "0";
const SHELL_CACHE = "gp-shell-v" + GP_SW_VERSION;
const ASSET_CACHE = "gp-assets-v" + GP_SW_VERSION;
const CACHE_PREFIX = "gp-";

const SHELL_URLS = [
  "/",
  "/hoe-werkt-het",
  "/aanbod",
  "/offline.html",
  "/manifest.webmanifest",
  "/assets/icons/icon-192.png",
  "/assets/icons/icon-512.png",
  "/assets/icons/icon-192-maskable.png",
  "/assets/icons/icon-512-maskable.png",
  "/assets/icons/apple-touch-icon.png",
];

self.addEventListener("install", (event) => {
  event.waitUntil(
    caches
      .open(SHELL_CACHE)
      .then((cache) =>
        Promise.all(
          SHELL_URLS.map((url) =>
            cache.add(url).catch(function () {
              /* individuele shell-URL mag falen zonder hele install te blokkeren */
            })
          )
        )
      )
      .then(function () {
        return self.skipWaiting();
      })
  );
});

self.addEventListener("activate", (event) => {
  event.waitUntil(
    caches
      .keys()
      .then(function (keys) {
        return Promise.all(
          keys
            .filter(function (key) {
              if (key === SHELL_CACHE || key === ASSET_CACHE) {
                return false;
              }
              return key.indexOf(CACHE_PREFIX) === 0 || key.indexOf("stekjes-shell-") === 0;
            })
            .map(function (key) {
              return caches.delete(key);
            })
        );
      })
      .then(function () {
        return self.clients.claim();
      })
  );
});

function isPrivateOrSensitive(pathname) {
  if (
    pathname.indexOf("/account") === 0 ||
    pathname.indexOf("/beheer") === 0 ||
    pathname.indexOf("/cron") === 0 ||
    pathname.indexOf("/deploy") === 0 ||
    pathname.indexOf("/media") === 0 ||
    pathname.indexOf("/api/") === 0 ||
    pathname.indexOf("/feedback") === 0
  ) {
    return true;
  }
  // Gebruikersuploads via publieke ervaringen-foto-route (niet /ervaringen-lijst).
  if (pathname.indexOf("/ervaringen/") === 0 && pathname.indexOf("/foto") !== -1) {
    return true;
  }
  return false;
}

function isHashedViteAsset(pathname) {
  return pathname.indexOf("/build/assets/") === 0;
}

function isHtmlNavigation(request) {
  if (request.mode === "navigate") {
    return true;
  }
  var accept = request.headers.get("accept") || "";
  return accept.indexOf("text/html") !== -1;
}

function networkFirstHtml(request) {
  return fetch(request)
    .then(function (response) {
      if (response && response.ok) {
        var copy = response.clone();
        caches.open(SHELL_CACHE).then(function (cache) {
          cache.put(request, copy);
        });
      }
      return response;
    })
    .catch(function () {
      return caches.match(request).then(function (cached) {
        return cached || caches.match("/offline.html");
      });
    });
}

function cacheFirstAsset(request) {
  return caches.match(request).then(function (cached) {
    if (cached) {
      return cached;
    }
    return fetch(request).then(function (response) {
      if (response && response.ok) {
        var copy = response.clone();
        caches.open(ASSET_CACHE).then(function (cache) {
          cache.put(request, copy);
        });
      }
      return response;
    });
  });
}

self.addEventListener("fetch", (event) => {
  var request = event.request;

  if (request.method !== "GET") {
    return;
  }

  var url;
  try {
    url = new URL(request.url);
  } catch (e) {
    return;
  }

  if (url.origin !== self.location.origin) {
    return;
  }

  if (isPrivateOrSensitive(url.pathname)) {
    return;
  }

  if (isHtmlNavigation(request)) {
    event.respondWith(networkFirstHtml(request));
    return;
  }

  if (isHashedViteAsset(url.pathname) || url.pathname.indexOf("/fonts/") === 0) {
    event.respondWith(cacheFirstAsset(request));
    return;
  }

  if (
    url.pathname.indexOf("/assets/") === 0 ||
    url.pathname === "/manifest.webmanifest" ||
    url.pathname === "/offline.html" ||
    url.pathname === "/favicon.ico"
  ) {
    event.respondWith(cacheFirstAsset(request));
  }
});
