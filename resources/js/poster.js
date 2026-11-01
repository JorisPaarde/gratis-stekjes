import QRCode from "qrcode";

// QR-code naar de pagina van de vereniging, als scherpe SVG (ook geprint).
document.querySelectorAll("[data-qr-url]").forEach(function (el) {
  QRCode.toString(el.getAttribute("data-qr-url"), {
    type: "svg",
    margin: 1,
    errorCorrectionLevel: "M",
    color: { dark: "#163820", light: "#ffffff" },
  })
    .then(function (svg) {
      el.innerHTML = svg;
      var s = el.querySelector("svg");
      if (s) {
        s.setAttribute("role", "img");
        s.setAttribute("aria-label", "QR-code naar " + el.getAttribute("data-qr-url"));
      }
    })
    .catch(function () {
      el.textContent = el.getAttribute("data-qr-url");
    });
});

document.querySelectorAll("[data-print]").forEach(function (btn) {
  btn.addEventListener("click", function () {
    window.print();
  });
});
