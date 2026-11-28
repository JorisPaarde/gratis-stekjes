@extends('layouts.site')

@section('title', $title)
@section('meta_description', $metaDescription)
@section('canonical', $canonical)

@section('content')
<section class="section wrap">
  <h1 class="page-title">Plek aanmelden</h1>
  <p class="section-intro">Meld je voedselbos, tuinvereniging, plantenasiel, plantenbieb, ruilkast of andere deelplek aan. Na controle wordt je plek zichtbaar op de kaart en in de lijst, en soms op een stadspagina.</p>

  @if($errors->any())
    <ul class="error-list" role="alert">
      @foreach($errors->all() as $error)
        <li class="error">{{ $error }}</li>
      @endforeach
    </ul>
  @endif

  <form class="form" method="post" action="{{ route('voor-organisaties.aanmelden') }}" novalidate>
    @csrf
    @include('partials.honeypot')

    <label>Naam van de plek *
      <input name="naam" required maxlength="160" value="{{ old('naam') }}">
    </label>
    <label>Type *
      <select name="type" required>
        <option value="">Kies een type</option>
        @foreach($typeLabels as $key => $label)
          <option value="{{ $key }}" @selected(old('type') === $key)>{{ $label }}</option>
        @endforeach
      </select>
    </label>
    <label>Land *
      <select name="country" required>
        @foreach($countries as $code => $label)
          <option value="{{ $code }}" @selected(old('country', 'NL') === $code)>{{ $label }}</option>
        @endforeach
      </select>
    </label>
    <label>Adres
      <input name="adres" maxlength="255" value="{{ old('adres') }}" autocomplete="street-address">
      <span class="hint">Alleen om je op de kaart te zetten; ik sla geen straatadres op.</span>
    </label>
    <label>Postcode
      <input name="postcode" maxlength="16" value="{{ old('postcode') }}" autocomplete="postal-code" placeholder="1234 AB of 9000">
      <span class="hint">NL: 1234 AB. BE: 4 cijfers (heel België).</span>
    </label>
    <label>Plaats *
      <input name="plaats" required maxlength="120" value="{{ old('plaats') }}" autocomplete="address-level2">
    </label>
    <label>Website
      <input name="url" maxlength="500" placeholder="https://" value="{{ old('url') }}" autocomplete="url">
    </label>
    <fieldset class="form-open">
      <legend>Wanneer is de plek open?</legend>
      <p class="hint">Niet verplicht. Zo weten bezoekers of ze altijd langs kunnen, of alleen op bepaalde dagen.</p>
      <label class="checkbox">
        <input type="radio" name="open_keuze" value="altijd" @checked(old('open_keuze') === 'altijd')>
        <span>Altijd of meestal open</span>
      </label>
      <label class="checkbox">
        <input type="radio" name="open_keuze" value="dagen" @checked(old('open_keuze') === 'dagen')>
        <span>Op vaste dagen</span>
      </label>
      <p class="form-open-extra form-open-extra--dagen">
        <label>
          <span class="visually-hidden">Op welke dagen of tijden is de plek open?</span>
          <input name="open_tekst_dagen" maxlength="200" value="{{ old('open_keuze') === 'dagen' ? old('open_tekst_dagen') : '' }}" placeholder="Bijv. di en za, 10 tot 16 uur">
        </label>
      </p>
      <label class="checkbox">
        <input type="radio" name="open_keuze" value="seizoen" @checked(old('open_keuze') === 'seizoen')>
        <span>Alleen in een seizoen</span>
      </label>
      <p class="form-open-extra form-open-extra--seizoen">
        <label>
          <span class="visually-hidden">In welk seizoen is de plek open?</span>
          <input name="open_tekst_seizoen" maxlength="200" value="{{ old('open_keuze') === 'seizoen' ? old('open_tekst_seizoen') : '' }}" placeholder="Bijv. april tot oktober">
        </label>
      </p>
    </fieldset>
    <label>Korte beschrijving
      <textarea name="beschrijving" maxlength="1000" rows="4">{{ old('beschrijving') }}</textarea>
      <span class="hint">Kort, bijvoorbeeld wat je er kunt brengen of meenemen.</span>
    </label>
    <label>Contact-e-mail *
      <input type="email" name="contact_email" required maxlength="254" value="{{ old('contact_email') }}" autocomplete="email">
    </label>
    <p class="hint">Dit adres gebruik ik alleen voor de bevestiging en bij vragen over je aanmelding. Het komt niet op de publieke kaart.</p>

    <label class="checkbox">
      <input type="checkbox" name="privacy" value="1" required @checked(old('privacy'))>
      <span>Ik ga akkoord met de <a href="{{ route('privacy') }}">privacyverklaring</a> (AVG).</span>
    </label>

    <button class="btn" type="submit">Aanmelden</button>
  </form>

  <p class="muted"><a href="{{ route('voor-organisaties') }}">Terug naar voor organisaties</a></p>
</section>
@endsection
