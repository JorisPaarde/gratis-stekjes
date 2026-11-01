{{-- Live melding als de gekozen naam al bestaat, met klikbare suggesties. --}}
<p class="hint naam-check" data-naam-check data-url="{{ route('naam-check') }}" role="status" aria-live="polite" hidden></p>
@push('scripts')
<script>
  (function () {
    var box = document.querySelector('[data-naam-check]');
    var input = document.querySelector('input[name=voornaam]');
    if (!box || !input) return;
    var timer = null, ctrl = null;
    function toon(d) {
      if (d.vrij) { box.hidden = true; box.textContent = ''; return; }
      box.hidden = false;
      box.textContent = 'Er is al iemand met deze naam. Kies bijvoorbeeld: ';
      (d.suggesties || []).forEach(function (s) {
        var b = document.createElement('button');
        b.type = 'button'; b.className = 'naam-suggestie'; b.textContent = s;
        b.addEventListener('click', function () { input.value = s; box.hidden = true; input.focus(); });
        box.appendChild(b);
      });
      box.appendChild(document.createTextNode(' of voeg een letter toe, zoals «' + input.value.trim() + ' B.».'));
    }
    input.addEventListener('input', function () {
      clearTimeout(timer);
      var v = input.value.trim();
      if (v.length < 2) { box.hidden = true; return; }
      timer = setTimeout(function () {
        if (ctrl) ctrl.abort();
        ctrl = new AbortController();
        fetch(box.getAttribute('data-url') + '?naam=' + encodeURIComponent(v), { headers: { Accept: 'application/json' }, credentials: 'same-origin', signal: ctrl.signal })
          .then(function (r) { return r.ok ? r.json() : { vrij: true }; })
          .then(toon)
          .catch(function () {});
      }, 400);
    });
  })();
</script>
@endpush
