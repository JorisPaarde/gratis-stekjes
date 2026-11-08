@push('scripts')
@vite(['resources/js/plantsoort-autocomplete.js'])
<script>
(function () {
  var terms = @json($invasieveTerms);
  var uitleg = @json($invasieveUitlegUrl);
  var naamFout = @json(\App\Support\AanbodTekst::FOUT_VERPLICHT);

  function unicodeTrim(text) {
    return String(text || '').replace(/^\s+|\s+$/gu, '');
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

  document.querySelectorAll('form[data-invasieve-form], form[data-aanbod-foto-form]').forEach(function (form) {
    var input = form.querySelector('[data-aanbod-tekst], [data-invasieve-input]');
    var foutEl = form.querySelector('[data-aanbod-tekst-fout]');
    var hint = form.querySelector('[data-invasieve-hint]');

    if (input) {
      form.addEventListener('submit', function (ev) {
        if (unicodeTrim(input.value) === '') {
          ev.preventDefault();
          setNaamFout(input, foutEl, true);
          try { input.focus(); } catch (e) {}
          return;
        }
        setNaamFout(input, foutEl, false);
      });
      input.addEventListener('input', function () {
        if (unicodeTrim(input.value) !== '') setNaamFout(input, foutEl, false);
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
