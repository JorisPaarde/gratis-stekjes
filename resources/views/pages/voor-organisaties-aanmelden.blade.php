@extends('layouts.site')

@section('title', $title)
@section('meta_description', $metaDescription)
@section('canonical', $canonical)

@section('content')
<section class="section wrap">
  <h1 class="page-title">Plek aanmelden</h1>
  <p class="section-intro">Meld je voedselbos, tuinvereniging, plantenasiel, plantenbieb, ruilkast of andere deelplek aan. Na controle verschijnt de plek op <a href="{{ route('deelplekken') }}">deelplekken</a> en eventueel op stadspagina’s.</p>

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
    <label>Adres
      <input name="adres" maxlength="255" value="{{ old('adres') }}" autocomplete="street-address">
    </label>
    <label>Postcode
      <input name="postcode" maxlength="16" value="{{ old('postcode') }}" autocomplete="postal-code">
    </label>
    <label>Plaats *
      <input name="plaats" required maxlength="120" value="{{ old('plaats') }}" autocomplete="address-level2">
    </label>
    <label>Website
      <input name="url" maxlength="500" placeholder="https://" value="{{ old('url') }}" autocomplete="url">
    </label>
    <label>Korte beschrijving
      <textarea name="beschrijving" maxlength="1000" rows="4">{{ old('beschrijving') }}</textarea>
    </label>
    <label>Contact-e-mail *
      <input type="email" name="contact_email" required maxlength="254" value="{{ old('contact_email') }}" autocomplete="email">
    </label>
    <p class="hint">Dit adres gebruiken we alleen voor de bevestiging en bij vragen over je aanmelding. Het komt niet op de publieke kaart.</p>

    <label class="checkbox">
      <input type="checkbox" name="privacy" value="1" required @checked(old('privacy'))>
      <span>Ik ga akkoord met de <a href="{{ route('privacy') }}">privacyverklaring</a> (AVG).</span>
    </label>

    <button class="btn" type="submit">Aanmelden</button>
  </form>

  <p class="muted"><a href="{{ route('voor-organisaties') }}">Terug naar voor organisaties</a></p>
</section>
@endsection
