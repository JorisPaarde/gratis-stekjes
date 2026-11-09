@extends('layouts.site')

@section('title', $title)
@section('meta_description', $metaDescription)
@section('canonical', $canonical)

@section('content')
<section class="section wrap">
  <h1 class="page-title">Profiel</h1>
  <p class="section-intro">Anderen zien alleen je voornaam of bijnaam en je postcodegebied met plaats, zoals «1326 Almere». Je adres en telefoonnummer zien ze pas als jullie in een gesprek allebei “ja” zeggen.</p>

  @if(! $user->heeftGekozenNaam())
    <p class="flash flash-ok" role="status">Welkom! Kies hieronder een voornaam of bijnaam. Tot die tijd zien anderen je als «Lid». Daarna kun je iets aanbieden of een gesprek starten.</p>
  @endif

  @if(session('status'))
    <p class="ok" role="status">{{ session('status') }}</p>
  @endif

  @if($errors->any())
    <p class="error" role="alert">{{ $errors->first() }}</p>
  @endif

  <form class="form" method="post" action="{{ route('account.profiel') }}" data-profiel-locatie>
    @csrf
    <label>Voornaam of bijnaam *
      <input name="voornaam" required maxlength="80" autocomplete="nickname" value="{{ old('voornaam', $user->name) }}">
    </label>
    @include('partials.naam-check')
    <fieldset class="adres-blok" data-adres>
      <legend>Je adres</legend>
      <p class="hint">Op de kaart en bij je aanbod staat alleen je postcodegebied en plaats, nooit je straat of huisnummer.</p>
      <label>Land *
        <select name="country" required data-adres-land>
          @foreach($countries as $code => $label)
            <option value="{{ $code }}" @selected(old('country', $user->country ?? 'NL') === $code)>{{ $label }}</option>
          @endforeach
        </select>
      </label>

      <input type="hidden" name="locatie_modus" value="{{ $locatieModus }}" data-locatie-modus>

      <div data-modus-postcode @if($locatieModus === 'plaats') hidden @endif>
        <div class="adres-rij">
          <label>Postcode *
            @php
              $pcWaarde = old('locatie');
              if ($pcWaarde === null) {
                  if (! $user->isUnreadable('adres_postcode') && $user->adres_postcode) {
                      $pcWaarde = $user->adres_postcode;
                  } elseif ($user->postcode_gebied) {
                      $pcWaarde = $user->postcode_gebied;
                  } else {
                      $pcWaarde = '';
                  }
              }
            @endphp
            <input name="locatie" maxlength="10" autocomplete="postal-code" data-adres-postcode data-locatie-postcode value="{{ $pcWaarde }}" placeholder="{{ old('country', $user->country ?? 'NL') === 'BE' ? '9000' : '2011 AB' }}" @if($locatieModus === 'postcode') required @endif>
          </label>
          <label>Huisnummer
            <input name="huisnummer" maxlength="12" autocomplete="off" inputmode="text" data-adres-nummer value="{{ old('huisnummer', $user->isUnreadable('adres_huisnummer') ? '' : $user->adres_huisnummer) }}" placeholder="12A">
          </label>
        </div>
        <p class="hint" data-plaats-bevestiging role="status" aria-live="polite"
           data-preview-url="{{ route('account.locatie-preview') }}"
           @if($user->woonplaats && $locatieModus === 'postcode') data-bekend="1" @endif>
          @if($user->woonplaats && $locatieModus === 'postcode')
            Plaats: {{ $user->woonplaats }}@if($user->postcode_gebied) ({{ $user->postcode_gebied }} {{ $user->woonplaats }})@endif
          @endif
        </p>
        <p class="hint"><a href="#" data-naar-plaats>Weet je je postcode niet?</a> Vul dan je plaatsnaam in.</p>
      </div>

      <div data-modus-plaats @if($locatieModus !== 'plaats') hidden @endif>
        <label>Plaats *
          <input name="locatie" maxlength="120" autocomplete="address-level2" data-locatie-plaats value="{{ old('locatie', $locatieModus === 'plaats' ? ($user->woonplaats ?: $user->locatie_label) : '') }}" placeholder="Almere" @if($locatieModus === 'plaats') required @else disabled @endif>
        </label>
        <p class="hint">Op de kaart staat dan alleen je plaatsnaam, zonder postcode. <a href="#" data-naar-postcode>Ik weet mijn postcode</a></p>
      </div>

      <p class="hint adres-status" data-adres-status data-url="{{ route('account.adres-zoeken') }}" role="status" aria-live="polite"></p>
      <div class="adres-rij">
        <label>Straat
          <input name="straat" maxlength="120" autocomplete="address-line1" data-adres-straat value="{{ old('straat', $user->isUnreadable('adres_straat') ? '' : $user->adres_straat) }}">
        </label>
        <label>Plaats (adres)
          <input name="plaats" maxlength="120" autocomplete="address-level2" data-adres-plaats value="{{ old('plaats', $user->isUnreadable('adres_plaats') ? '' : $user->adres_plaats) }}">
        </label>
      </div>
      <input type="hidden" name="adres_delen" value="0">
      <label class="checkbox">
        <input type="checkbox" name="adres_delen" value="1" @checked(old('adres_delen', $user->adres_delen !== false ? '1' : '0') === '1')>
        Deel mijn adres in een gesprek zodra we allebei “ja” zeggen
      </label>
      <p class="hint">Adres liever niet invullen? Laat huisnummer en straat leeg.</p>
    </fieldset>
    <label>Telefoon (optioneel)
      @if($user->isUnreadable('telefoon') && old('telefoon') === null)
        <input name="telefoon" maxlength="40" value="" autocomplete="tel" placeholder="{{ \App\Casts\SafeEncrypted::VIEW_FALLBACK }}">
        <span class="hint">Je telefoonnummer kon ik niet lezen. Vul het opnieuw in.</span>
      @else
        <input name="telefoon" maxlength="40" value="{{ old('telefoon', $user->telefoon) }}" autocomplete="tel">
      @endif
    </label>
    <label class="checkbox">
      <input type="hidden" name="berichtmail" value="0">
      <input type="checkbox" name="berichtmail" value="1" @checked(old('berichtmail', $user->berichtmail !== false ? '1' : '0') === '1')>
      Stuur me een mail als iemand me een bericht stuurt
    </label>
    <label class="checkbox">
      <input type="hidden" name="activatieherinnering" value="0">
      <input type="checkbox" name="activatieherinnering" value="1" @checked(old('activatieherinnering', $user->activatieherinnering !== false ? '1' : '0') === '1')>
      Stuur me een tipmail als ik nog geen aanbod heb geplaatst
    </label>
    @foreach($aanmeldVinkjes ?? [] as $vink)
      <label class="checkbox">
        <input type="hidden" name="{{ $vink['param'] }}" value="0">
        <input type="checkbox" name="{{ $vink['param'] }}" value="1" @checked(old($vink['param'], $user->{$vink['param']} ? '1' : '0') === '1')>
        <span>{{ $vink['label'] }}</span>
      </label>
    @endforeach
    <button class="btn" type="submit">Opslaan</button>
  </form>

  <p class="muted" style="margin-top:1rem">
    <a href="{{ route('account.index') }}">Terug naar account</a>
    · <a href="{{ route('account.aanbod') }}">Mijn aanbod</a>
  </p>
</section>
@endsection

@push('scripts')
<script>
  (function () {
    var form = document.querySelector('[data-profiel-locatie]');
    if (!form) return;
    var root = form.querySelector('[data-adres]');
    var modusInput = form.querySelector('[data-locatie-modus]');
    var blokPc = form.querySelector('[data-modus-postcode]');
    var blokPlaats = form.querySelector('[data-modus-plaats]');
    var inputPc = form.querySelector('[data-locatie-postcode]');
    var inputPlaats = form.querySelector('[data-locatie-plaats]');
    var bevestiging = form.querySelector('[data-plaats-bevestiging]');
    var previewUrl = bevestiging && bevestiging.getAttribute('data-preview-url');
    var land = root.querySelector('[data-adres-land]');
    var nr = root.querySelector('[data-adres-nummer]');
    var status = root.querySelector('[data-adres-status]');
    var url = status.getAttribute('data-url');
    var straat = root.querySelector('[data-adres-straat]');
    var plaats = root.querySelector('[data-adres-plaats]');
    var timer = null, laatste = '', bezig = null, previewTimer = null, previewLaatste = '';

    function setModus(m) {
      modusInput.value = m;
      var isPc = m === 'postcode';
      blokPc.hidden = !isPc;
      blokPlaats.hidden = isPc;
      if (isPc) {
        inputPc.disabled = false;
        inputPc.required = true;
        inputPc.setAttribute('name', 'locatie');
        inputPlaats.disabled = true;
        inputPlaats.required = false;
        inputPlaats.removeAttribute('name');
      } else {
        inputPlaats.disabled = false;
        inputPlaats.required = true;
        inputPlaats.setAttribute('name', 'locatie');
        inputPc.disabled = true;
        inputPc.required = false;
        inputPc.removeAttribute('name');
        if (bevestiging) bevestiging.textContent = '';
      }
    }

    form.querySelectorAll('[data-naar-plaats]').forEach(function (a) {
      a.addEventListener('click', function (e) { e.preventDefault(); setModus('plaats'); inputPlaats.focus(); });
    });
    form.querySelectorAll('[data-naar-postcode]').forEach(function (a) {
      a.addEventListener('click', function (e) { e.preventDefault(); setModus('postcode'); inputPc.focus(); });
    });

    function previewPlaats() {
      if (!previewUrl || !bevestiging || modusInput.value !== 'postcode') return;
      clearTimeout(previewTimer);
      var p = inputPc.value.trim();
      var c = land.value;
      if (!p) { bevestiging.textContent = ''; return; }
      var sleutel = c + '|' + p;
      if (sleutel === previewLaatste && bevestiging.getAttribute('data-bekend') === '1') return;
      previewTimer = setTimeout(function () {
        fetch(previewUrl + '?country=' + encodeURIComponent(c) + '&postcode=' + encodeURIComponent(p), {
          headers: { Accept: 'application/json' }, credentials: 'same-origin'
        })
          .then(function (r) { return r.json(); })
          .then(function (d) {
            previewLaatste = sleutel;
            if (!d.ok) {
              bevestiging.textContent = d.error || '';
              bevestiging.removeAttribute('data-bekend');
              return;
            }
            bevestiging.textContent = d.woonplaats
              ? ('Plaats: ' + d.woonplaats + (d.label ? ' · op de kaart: ' + d.label : ''))
              : (d.label ? ('Op de kaart: ' + d.label) : '');
            bevestiging.setAttribute('data-bekend', '1');
            if (d.woonplaats && plaats && !plaats.value) plaats.value = d.woonplaats;
          })
          .catch(function () { /* stil: server bepaalt bij opslaan */ });
      }, 400);
    }

    // Zodra postcode + huisnummer kloppen: automatisch straat en plaats opzoeken (alleen NL).
    function check() {
      clearTimeout(timer);
      var p = inputPc.value.replace(/\s+/g, '').toUpperCase();
      var n = nr.value.trim();
      if (land.value !== 'NL' || !/^\d{4}[A-Z]{2}$/.test(p) || !/^\d{1,5}/.test(n)) { status.textContent = ''; return; }
      var sleutel = p + '|' + n;
      if (sleutel === laatste) return;
      timer = setTimeout(function () { zoek(p, n, sleutel); }, 350);
    }

    function zoek(p, n, sleutel) {
      if (bezig) bezig.abort();
      bezig = new AbortController();
      status.textContent = 'Adres zoeken…';
      fetch(url + '?postcode=' + encodeURIComponent(p) + '&huisnummer=' + encodeURIComponent(n), {
        headers: { Accept: 'application/json' }, credentials: 'same-origin', signal: bezig.signal
      })
        .then(function (r) { return r.json(); })
        .then(function (d) {
          laatste = sleutel;
          if (!d.ok) { status.textContent = d.error || 'Niet gevonden. Vul straat en plaats zelf in.'; return; }
          straat.value = d.straat; plaats.value = d.plaats; inputPc.value = d.postcode;
          status.textContent = '✓ ' + d.straat + ', ' + d.plaats + (d.toevoegingen && d.toevoegingen.length ? ' · toevoegingen op dit nummer: ' + d.toevoegingen.join(', ') : '');
          previewPlaats();
        })
        .catch(function (e) { if (e.name !== 'AbortError') status.textContent = 'Opzoeken lukt nu niet. Vul straat en plaats zelf in.'; });
    }

    inputPc.addEventListener('input', function () { previewPlaats(); check(); });
    inputPc.addEventListener('blur', previewPlaats);
    nr.addEventListener('input', check);
    land.addEventListener('change', function () {
      previewLaatste = '';
      inputPc.placeholder = land.value === 'BE' ? '9000' : '2011 AB';
      previewPlaats();
      check();
    });

    // Init: zorg dat maar één locatie-input name=locatie heeft.
    setModus(modusInput.value === 'plaats' ? 'plaats' : 'postcode');
  })();
</script>
@endpush
