@extends('layouts.site')

@section('title', $title)
@section('meta_description', $metaDescription)
@section('canonical', $canonical)

@section('content')
<section class="section wrap">
  <h1 class="page-title">Profiel</h1>
  <p class="section-intro">Anderen zien alleen je voornaam of bijnaam en ongeveer waar je woont. Je telefoonnummer zien ze pas als jullie in een gesprek allebei “ja” zeggen.</p>

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
    <label>Land *
      <select name="country" required>
        @foreach($countries as $code => $label)
          <option value="{{ $code }}" @selected(old('country', $user->country ?? 'NL') === $code)>{{ $label }}</option>
        @endforeach
      </select>
    </label>
    <label>Postcode of plaats *
      <input name="locatie" required maxlength="120" value="{{ old('locatie', $user->locatie_label) }}" placeholder="Bijv. Haarlem, 2011 AB of Gent">
      <span class="hint">Vul je postcode of woonplaats in. We bewaren alleen ongeveer waar je woont, nooit je straat of huisnummer.</span>
    </label>
    <label>Telefoon (optioneel)
      @if($user->isUnreadable('telefoon') && old('telefoon') === null)
        <input name="telefoon" maxlength="40" value="" autocomplete="tel" placeholder="{{ \App\Casts\SafeEncrypted::VIEW_FALLBACK }}">
        <span class="hint">Opgeslagen nummer is niet leesbaar (andere sleutel). Leeg laten behoudt de oude waarde; vul alleen in om te vervangen.</span>
      @else
        <input name="telefoon" maxlength="40" value="{{ old('telefoon', $user->telefoon) }}" autocomplete="tel">
      @endif
    </label>
    <button class="btn" type="submit">Opslaan</button>
  </form>

  <p class="muted" style="margin-top:1rem">
    <a href="{{ route('account.index') }}">Terug naar account</a>
    · <a href="{{ route('account.aanbod') }}">Mijn aanbod</a>
  </p>
</section>
@endsection
