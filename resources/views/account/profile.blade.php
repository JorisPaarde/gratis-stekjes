@extends('layouts.site')

@section('title', $title)
@section('meta_description', $metaDescription)
@section('canonical', $canonical)

@section('content')
<section class="section wrap">
  <h1 class="page-title">Profiel</h1>
  <p class="section-intro">Anderen zien alleen je voornaam of bijnaam en je postcodegebied. Je adres en telefoonnummer zien ze pas als jullie in een gesprek allebei “ja” zeggen.</p>

  @if(session('status'))
    <p class="ok" role="status">{{ session('status') }}</p>
  @endif

  @if($errors->any())
    <p class="error" role="alert">{{ $errors->first() }}</p>
  @endif

  <form class="form" method="post" action="{{ route('account.profiel') }}">
    @csrf
    <label>Voornaam of bijnaam *
      <input name="voornaam" required maxlength="80" autocomplete="nickname" value="{{ old('voornaam', $user->name) }}">
    </label>
    <fieldset class="adres-blok" data-adres>
      <legend>Je adres</legend>
      <p class="hint">Op de kaart en bij je aanbod staat alleen je postcodegebied, zoals {{ ($user->country ?? 'NL') === 'BE' ? '9000' : '2011' }}. Je volledige adres ziet alleen iemand met wie je allebei “ja” zegt in een gesprek, zodat je kunt afspreken.</p>
      <label>Land *
        <select name="country" required data-adres-land>
          @foreach($countries as $code => $label)
            <option value="{{ $code }}" @selected(old('country', $user->country ?? 'NL') === $code)>{{ $label }}</option>
          @endforeach
        </select>
      </label>
      <div class="adres-rij">
        <label>Postcode *
          <input name="locatie" required maxlength="120" autocomplete="postal-code" data-adres-postcode value="{{ old('locatie', (! $user->isUnreadable('adres_postcode') && $user->adres_postcode) ? $user->adres_postcode : $user->locatie_label) }}" placeholder="2011 AB">
        </label>
        <label>Huisnummer
          <input name="huisnummer" maxlength="12" autocomplete="off" inputmode="text" data-adres-nummer value="{{ old('huisnummer', $user->isUnreadable('adres_huisnummer') ? '' : $user->adres_huisnummer) }}" placeholder="12A">
        </label>
      </div>
      <p class="adres-zoek-rij">
        <button type="button" class="btn btn-secondary" data-adres-zoek data-url="{{ route('account.adres-zoeken') }}">Zoek mijn adres</button>
        <span class="hint" data-adres-status role="status" aria-live="polite"></span>
      </p>
      <div class="adres-rij">
        <label>Straat
          <input name="straat" maxlength="120" autocomplete="address-line1" data-adres-straat value="{{ old('straat', $user->isUnreadable('adres_straat') ? '' : $user->adres_straat) }}">
        </label>
        <label>Plaats
          <input name="plaats" maxlength="120" autocomplete="address-level2" data-adres-plaats value="{{ old('plaats', $user->isUnreadable('adres_plaats') ? '' : $user->adres_plaats) }}">
        </label>
      </div>
      <input type="hidden" name="adres_delen" value="0">
      <label class="checkbox">
        <input type="checkbox" name="adres_delen" value="1" @checked(old('adres_delen', $user->adres_delen !== false ? '1' : '0') === '1')>
        Deel mijn adres in een gesprek zodra we allebei “ja” zeggen
      </label>
      <p class="hint">Adres liever niet invullen? Laat huisnummer en straat leeg. Je mag bij Postcode ook alleen je woonplaats zetten.</p>
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
    var root = document.querySelector('[data-adres]');
    if (!root) return;
    var knop = root.querySelector('[data-adres-zoek]');
    var status = root.querySelector('[data-adres-status]');
    var land = root.querySelector('[data-adres-land]');
    var pc = root.querySelector('[data-adres-postcode]');
    var nr = root.querySelector('[data-adres-nummer]');
    var straat = root.querySelector('[data-adres-straat]');
    var plaats = root.querySelector('[data-adres-plaats]');
    function sync() { knop.hidden = land.value !== 'NL'; }
    land.addEventListener('change', sync); sync();
    function zoek() {
      if (land.value !== 'NL' || !pc.value.trim() || !nr.value.trim()) return;
      status.textContent = 'Even zoeken…';
      var url = knop.getAttribute('data-url') + '?postcode=' + encodeURIComponent(pc.value) + '&huisnummer=' + encodeURIComponent(nr.value);
      fetch(url, { headers: { Accept: 'application/json' }, credentials: 'same-origin' })
        .then(function (r) { return r.json(); })
        .then(function (d) {
          if (!d.ok) { status.textContent = d.error || 'Niet gevonden. Vul straat en plaats zelf in.'; return; }
          straat.value = d.straat; plaats.value = d.plaats; pc.value = d.postcode;
          status.textContent = 'Gevonden: ' + d.straat + ', ' + d.plaats + (d.toevoegingen && d.toevoegingen.length ? ' (toevoegingen: ' + d.toevoegingen.join(', ') + ')' : '') + '.';
        })
        .catch(function () { status.textContent = 'Opzoeken lukt nu niet. Vul straat en plaats zelf in.'; });
    }
    knop.addEventListener('click', zoek);
    nr.addEventListener('change', function () { if (!straat.value) zoek(); });
  })();
</script>
@endpush
