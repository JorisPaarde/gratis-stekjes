@push('scripts')
@vite(['resources/js/plantsoort-autocomplete.js'])
<script>
(function () {
  var terms = @json($invasieveTerms);
  var uitleg = @json($invasieveUitlegUrl);
  var naamFout = @json(\App\Support\AanbodTekst::FOUT_VERPLICHT);
  // Zelfde FORM-whitespace als AanbodTekst::FORM_WS_* (niet JS \s — mist U+0085 NEL).
  var formWsClass = @json(\App\Support\AanbodTekst::FORM_WS_JS);
  var formWsTrimRe = new RegExp('^[' + formWsClass + ']+|[' + formWsClass + ']+$', 'gu');

  function unicodeTrim(text) {
    return String(text || '').replace(formWsTrimRe, '');
  }

  function normalize(text) {
    return String(text || '')
      .normalize('NFD').replace(/[\u0300-\u036f]/g, '')
      .toLowerCase()
      .replace(/[×✕]/g, 'x')
      .replace(/['’‘ʼ`´]/g, '')
      .replace(/[^a-z0-9]+/g, ' ')
      .replace(/\s+/g, ' ')
      .trim();
  }

  function findMatch(text) {
    var hay = ' ' + normalize(text) + ' ';
    for (var i = 0; i < terms.length; i++) {
      if (hay.indexOf(' ' + terms[i] + ' ') !== -1) return terms[i];
    }
    return null;
  }

  function setNaamFout(input, foutEl, show) {
    if (!foutEl) return;
    if (show) {
      foutEl.hidden = false;
      foutEl.textContent = naamFout;
      input.setAttribute('aria-invalid', 'true');
    } else {
      foutEl.hidden = true;
      foutEl.textContent = '';
      input.removeAttribute('aria-invalid');
    }
  }

  function syncNaamValidity(input) {
    if (unicodeTrim(input.value) === '') {
      input.setCustomValidity(naamFout);
    } else {
      input.setCustomValidity('');
    }
  }

  document.querySelectorAll('form[data-invasieve-form], form[data-aanbod-foto-form]').forEach(function (form) {
    var input = form.querySelector('[data-aanbod-tekst]');
    if (!input) input = form.querySelector('[data-invasieve-input]');
    var foutEl = form.querySelector('[data-aanbod-tekst-fout]');
    var hint = form.querySelector('[data-invasieve-hint]');

    if (input) {
      // Progressive enhancement: required + customValidity alleen met JS.
      // NoJS lege submit → servervalidatie + exacte NL-inlinefout (geen browserlocale).
      input.required = true;
      syncNaamValidity(input);

      function focusNaam() {
        try { input.focus({ preventScroll: false }); } catch (e) {
          try { input.focus(); } catch (e2) {}
        }
        // Sommige browsers verplaatsen focus na native invalid; herstel in volgende tick.
        setTimeout(function () {
          try { input.focus(); } catch (e3) {}
        }, 0);
      }

      input.addEventListener('invalid', function (ev) {
        ev.preventDefault();
        syncNaamValidity(input);
        setNaamFout(input, foutEl, true);
        focusNaam();
      });

      input.addEventListener('input', function () {
        syncNaamValidity(input);
        if (unicodeTrim(input.value) !== '') setNaamFout(input, foutEl, false);
      });

      form.addEventListener('submit', function (ev) {
        syncNaamValidity(input);
        if (!input.checkValidity()) {
          ev.preventDefault();
          // checkValidity triggert invalid; toon/focus alsnog expliciet.
          setNaamFout(input, foutEl, true);
          focusNaam();
          return;
        }
        setNaamFout(input, foutEl, false);
      });
    }

    if (!terms || !terms.length || !input || !hint) return;
    function check() {
      var hit = findMatch(input.value);
      if (hit) {
        hint.hidden = false;
        hint.textContent = 'Let op: dit lijkt op een invasieve soort (“' + hit + '”). Die mag je niet aanbieden, dus ik blokkeer dat. Uitleg: ' + uitleg;
      } else {
        hint.hidden = true;
        hint.textContent = '';
      }
    }
    input.addEventListener('input', check);
    check();
  });
})();
</script>
@endpush
