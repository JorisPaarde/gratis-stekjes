@push('scripts')
@vite(['resources/js/plantsoort-autocomplete.js'])
<script>
(function () {
  var terms = @json($invasieveTerms);
  var uitleg = @json($invasieveUitlegUrl);
  if (!terms || !terms.length) return;

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

  document.querySelectorAll('[data-invasieve-form]').forEach(function (form) {
    var input = form.querySelector('[data-invasieve-input]');
    var hint = form.querySelector('[data-invasieve-hint]');
    if (!input || !hint) return;
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
