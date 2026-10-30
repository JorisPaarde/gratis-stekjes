/* site — v0.1.0 */
(function () {
  function escapeHtml(s) {
    return String(s)
      .replace(/&/g, "&amp;")
      .replace(/</g, "&lt;")
      .replace(/>/g, "&gt;")
      .replace(/"/g, "&quot;");
  }

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

  var nuBlocks = document.querySelectorAll("[data-nu-te-stekken]");
  if (nuBlocks.length) {
    var nuCfg = window.STEKJES_SITE || {};
    var nuUrl = nuCfg.api_nu_te_stekken || "/api/nu-te-stekken";
    fetch(nuUrl, { credentials: "same-origin" })
      .then(function (r) {
        return r.json();
      })
      .then(function (data) {
        if (!data || !data.ok || !Array.isArray(data.groepen) || !data.groepen.length) {
          return;
        }
        var methodeLabels = {
          stek: "Stek",
          scheut: "Scheut",
          deling: "Deling",
          zaad: "Zaad",
        };
        nuBlocks.forEach(function (section) {
          var intro = section.querySelector("[data-nu-te-stekken-intro]");
          var lijst = section.querySelector("[data-nu-te-stekken-lijst]");
          var meer = section.querySelector("[data-nu-te-stekken-meer]");
          if (intro) {
            intro.textContent = data.intro || "";
          }
          if (lijst) {
            lijst.innerHTML = data.groepen
              .map(function (g, idx) {
                var planten = (g.planten || [])
                  .map(function (p) {
                    if (p.stek_url) {
                      return (
                        '<a class="nu-te-stekken-plant" href="' +
                        escapeHtml(p.stek_url) +
                        '">' +
                        escapeHtml(p.naam) +
                        "</a>"
                      );
                    }
                    return '<span class="nu-te-stekken-plant is-plain">' + escapeHtml(p.naam) + "</span>";
                  })
                  .join("");
                var bron = "";
                if (g.bron && g.bron.url && g.bron.naam) {
                  bron =
                    '<p class="nu-te-stekken-bron">Bron: <a href="' +
                    escapeHtml(g.bron.url) +
                    '" rel="noopener noreferrer" target="_blank">' +
                    escapeHtml(g.bron.naam) +
                    "</a></p>";
                }
                var label = methodeLabels[g.methode] || g.methode;
                return (
                  '<article class="nu-te-stekken-card" style="--i:' +
                  idx +
                  '">' +
                  '<p class="nu-te-stekken-methode"><span class="badge">' +
                  escapeHtml(label) +
                  "</span></p>" +
                  "<h3>" +
                  escapeHtml(g.titel || "") +
                  "</h3>" +
                  "<p>" +
                  escapeHtml(g.tip || "") +
                  "</p>" +
                  '<p class="nu-te-stekken-planten">' +
                  planten +
                  "</p>" +
                  bron +
                  "</article>"
                );
              })
              .join("");
          }
          if (meer && data.stektips_index) {
            meer.innerHTML =
              'Meer soorten: <a href="' +
              escapeHtml(data.stektips_index) +
              '">alle stektips</a>.';
          }
          section.hidden = false;
        });
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
