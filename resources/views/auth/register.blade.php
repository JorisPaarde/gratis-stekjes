@extends('layouts.site')

@section('title', $title)
@section('meta_description', $metaDescription)
@section('canonical', $canonical)

@section('content')
<section class="section wrap">
  <h1 class="page-title">Account maken</h1>
  <p class="section-intro">Meedoen is gratis. Met één account kun je iets aanbieden én iets ophalen: planten, stekjes of zaden. Er wordt niets verkocht. Je krijgt een e-mail om je aanmelding te bevestigen.</p>

  @if($errors->any())
    <ul class="error-list" role="alert">
      @foreach($errors->all() as $error)
        <li class="error">{{ $error }}</li>
      @endforeach
    </ul>
  @endif

  <form class="form" method="post" action="{{ route('account.registreren') }}" novalidate data-meet-form="register">
    @csrf
    @include('partials.honeypot')

    <label>Voornaam of bijnaam *
      <input name="voornaam" required maxlength="80" autocomplete="nickname" value="{{ old('voornaam') }}">
    </label>
    @include('partials.naam-check')
    <label>E-mailadres *
      <input type="email" name="email" required maxlength="254" autocomplete="email" value="{{ old('email') }}">
    </label>
    <label>Wachtwoord * <span class="hint">Minstens 10 tekens</span>
      <input type="password" name="password" required minlength="10" maxlength="200" autocomplete="new-password">
    </label>
    <label>Wachtwoord opnieuw *
      <input type="password" name="password_confirmation" required minlength="10" maxlength="200" autocomplete="new-password">
    </label>

    <label class="checkbox">
      <input type="checkbox" name="gratis_akkoord" value="1" required @checked(old('gratis_akkoord'))>
      <span>Ik ga akkoord: ik bied alles <strong>gratis</strong> aan en verkoop niets via deze site.</span>
    </label>
    <label class="checkbox">
      <input type="checkbox" name="privacy" value="1" required @checked(old('privacy'))>
      <span>Ik ga akkoord met de <a href="{{ route('privacy') }}">privacyverklaring</a>.</span>
    </label>
    @foreach(($aanmeldVinkjes ?? [['param' => 'struikrover', 'label' => 'Ik ben Struikrover']]) as $vink)
      <label class="checkbox">
        <input type="checkbox" name="{{ $vink['param'] }}" value="1" @checked(old($vink['param'], ($aanmeldChecked[$vink['param']] ?? false) || (($vink['param'] === 'struikrover') && ($struikroverChecked ?? false))))>
        <span>{{ $vink['label'] }}</span>
      </label>
    @endforeach

    {{-- Los blok voor 0.76.0: blijft staan als het formulier kaal wordt. --}}
    <fieldset class="form-fieldset form-fieldset--meet" data-meet-found-via>
      <legend>Hoe heb je ons gevonden? <span class="hint">(optioneel)</span></legend>
      <label>
        <select name="acquisition_found_via" data-found-via-select>
          <option value="">— kies indien je wilt —</option>
          @foreach(($foundViaOptions ?? []) as $value => $label)
            <option value="{{ $value }}" @selected(old('acquisition_found_via') === $value)>{{ $label }}</option>
          @endforeach
        </select>
      </label>
      <label data-found-via-other @if(old('acquisition_found_via') !== 'anders') hidden @endif>
        Anders, namelijk
        <input type="text" name="acquisition_found_via_other" maxlength="120" value="{{ old('acquisition_found_via_other') }}" autocomplete="off">
      </label>
    </fieldset>

    <button class="btn" type="submit">Account maken</button>
  </form>

  <p class="muted">Al een account? <a href="{{ route('account.inloggen') }}">Inloggen</a></p>
</section>
@endsection

@push('scripts')
<script>
(function () {
  var form = document.querySelector('[data-meet-form="register"]');
  if (!form || !navigator.sendBeacon) return;
  var started = false;
  var endpoint = @json(route('meet.event'));
  var path = '/account/registreren';
  function beacon(type, extra) {
    try {
      var body = new URLSearchParams();
      body.set('type', type);
      body.set('path', path);
      if (extra) {
        Object.keys(extra).forEach(function (k) { body.set(k, extra[k]); });
      }
      navigator.sendBeacon(endpoint, body);
    } catch (e) {}
  }
  function onStart() {
    if (started) return;
    started = true;
    beacon('form_start');
  }
  form.addEventListener('focusin', onStart, { once: true });
  form.addEventListener('input', onStart, { once: true });

  var select = form.querySelector('[data-found-via-select]');
  var other = form.querySelector('[data-found-via-other]');
  if (select && other) {
    select.addEventListener('change', function () {
      var show = select.value === 'anders';
      other.hidden = !show;
    });
  }
})();
</script>
@endpush
