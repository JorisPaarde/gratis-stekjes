/* site — v0.1.0 */
(function () {
  var toggle = document.querySelector("[data-nav-toggle]");
  var nav = document.querySelector("[data-nav]");
  if (toggle && nav) {
    toggle.addEventListener("click", function () {
      var open = nav.classList.toggle("is-open");
      toggle.setAttribute("aria-expanded", open ? "true" : "false");
    });
  }

  var tellerEl = document.querySelector("[data-meedoen-teller]");
  if (tellerEl) {
    var cfg = window.STEKJES_SITE || {};
    var url = cfg.api_teller || "/api/teller.php";
    fetch(url, { credentials: "same-origin" })
      .then(function (r) {
        return r.json();
      })
      .then(function (data) {
        if (data && data.show && typeof data.count === "number") {
          tellerEl.hidden = false;
          tellerEl.textContent = data.count + " mensen doen al mee";
        }
      })
      .catch(function () {
        /* stil */
      });
  }

  if ("serviceWorker" in navigator) {
    var ver = (window.STEKJES_SITE && window.STEKJES_SITE.version) || "0";
    navigator.serviceWorker.register("/sw.js?v=" + encodeURIComponent(ver)).catch(function () {});
  }
})();
