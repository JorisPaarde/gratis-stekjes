@extends('layouts.site')

@section('title', $title)
@section('meta_description', $metaDescription)
@section('canonical', $canonical)

@section('content')
<section class="section wrap">
  <h1 class="page-title">Account maken</h1>
  <p class="section-intro">Meedoen is gratis. Met één account kun je iets aanbieden én iets ophalen: planten, stekjes of zaden. Er wordt niets verkocht. Je krijgt een e-mail om je aanmelding te bevestigen. Je voornaam of bijnaam vul je daarna in op je profiel.</p>

  @if($errors->any())
    <ul class="error-list" role="alert">
      @foreach($errors->all() as $error)
        <li class="error">{{ $error }}</li>
      @endforeach
    </ul>
  @elseif(!empty($foutMessage))
    {{-- Vangnet zonder sessie-flash (file-session race op staging). --}}
    <ul class="error-list" role="alert">
      <li class="error">{{ $foutMessage }}</li>
    </ul>
  @endif

  <form class="form" method="post" action="{{ route('account.registreren') }}" novalidate data-meet-form="register" autocomplete="on">
    @csrf
    @include('partials.honeypot')
    <input type="hidden" name="next" value="{{ $next ?? '/account' }}">

    <label for="reg-email">E-mailadres *
      <input
        type="email"
        id="reg-email"
        name="email"
        required
        maxlength="254"
        autocomplete="email"
        inputmode="email"
        autocapitalize="off"
        spellcheck="false"
        value="{{ old('email', $queryEmail ?? '') }}"
      >
    </label>
    <label for="reg-password">Wachtwoord * <span class="hint">Minstens 10 tekens</span>
      <span class="wachtwoord-veld">
        <input
          type="password"
          name="password"
          id="reg-password"
          required
          minlength="10"
          maxlength="200"
          autocomplete="new-password"
          passwordrules="minlength: 10;"
        >
        <button type="button" class="btn btn-secondary btn-klein" data-wachtwoord-toggle aria-controls="reg-password" aria-pressed="false">Toon</button>
      </span>
    </label>

    <label for="reg-country">Land *
      <select id="reg-country" name="country" required autocomplete="country">
        @foreach($countries as $code => $label)
          <option value="{{ $code }}" @selected(old('country', 'NL') === $code)>{{ $label }}</option>
        @endforeach
      </select>
    </label>
    <label for="reg-postcode">Postcode *
      <input
        type="text"
        id="reg-postcode"
        name="postcode"
        required
        maxlength="10"
        autocomplete="postal-code"
        inputmode="text"
        autocapitalize="characters"
        spellcheck="false"
        value="{{ old('postcode') }}"
        placeholder="{{ old('country', 'NL') === 'BE' ? '9000' : '2011 AB' }}"
      >
    </label>
    <p class="hint">Alleen een afgeronde plek wordt zichtbaar, nooit je straatadres. Zo kunnen buren aanbod bij jou in de buurt vinden.</p>

    <label class="checkbox">
      <input type="checkbox" name="gratis_akkoord" value="1" required @checked(old('gratis_akkoord'))>
      <span>Ik ga akkoord: ik bied alles <strong>gratis</strong> aan en verkoop niets via deze site.</span>
    </label>
    <label class="checkbox">
      <input type="checkbox" name="privacy" value="1" required @checked(old('privacy'))>
      <span>Ik ga akkoord met de <a href="{{ route('privacy') }}">privacyverklaring</a>.</span>
    </label>

    <fieldset class="form-fieldset form-fieldset--meet" data-meet-found-via>
      <legend>Hoe heb je ons gevonden? <span class="hint">(optioneel)</span></legend>
      <label for="reg-found-via">
        <select id="reg-found-via" name="acquisition_found_via" data-found-via-select autocomplete="off">
          <option value="">— kies indien je wilt —</option>
          @foreach(($foundViaOptions ?? []) as $value => $label)
            <option value="{{ $value }}" @selected(old('acquisition_found_via') === $value)>{{ $label }}</option>
          @endforeach
        </select>
      </label>
      <label for="reg-found-via-other" data-found-via-other @if(old('acquisition_found_via') !== 'anders') hidden @endif>
        Anders, namelijk
        <input
          type="text"
          id="reg-found-via-other"
          name="acquisition_found_via_other"
          maxlength="120"
          value="{{ old('acquisition_found_via_other') }}"
          autocomplete="off"
        >
      </label>
    </fieldset>

    <button class="btn" type="submit">Account maken</button>
  </form>

  <p class="muted">Al een account? <a href="{{ route('account.inloggen', ['next' => $next ?? '/account']) }}">Inloggen</a></p>
</section>
@endsection

@push('scripts')
<script>
(function () {
  var btn = document.querySelector('[data-wachtwoord-toggle]');
  if (btn) {
    var input = document.getElementById(btn.getAttribute('aria-controls'));
    if (input) {
      btn.addEventListener('click', function () {
        var show = input.type === 'password';
        input.type = show ? 'text' : 'password';
        btn.setAttribute('aria-pressed', show ? 'true' : 'false');
        btn.textContent = show ? 'Verberg' : 'Toon';
      });
    }
  }

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
