@extends('layouts.site')

@section('title', $title)
@section('meta_description', $metaDescription)
@section('canonical', $canonical)

@section('content')
<section class="section wrap">
  <h1 class="page-title">Profiel</h1>
  <p class="section-intro">Publiek: voornaam/bijnaam en benaderde locatie (postcode of plaats in Nederland of België). Telefoon alleen later zichtbaar bij wederzijds “ja” in een gesprek.</p>

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
      <input name="locatie" required maxlength="120" value="{{ old('locatie', $user->locatie_label) }}" placeholder="Bijv. 2011, 1234 AB, Haarlem of 9000 / Gent">
      <span class="hint">Nederland: postcode (1234 AB) of plaats. België: 4 cijfers of plaatsnaam (NL/FR zoals de geocoder die geeft). Ik sla alleen een afgeronde plek op, geen straatadres.</span>
    </label>
    <label>Telefoon (optioneel)
      <input name="telefoon" maxlength="40" value="{{ old('telefoon', $user->telefoon) }}" autocomplete="tel">
    </label>
    <button class="btn" type="submit">Opslaan</button>
  </form>

  <p class="muted" style="margin-top:1rem">
    <a href="{{ route('account.index') }}">Terug naar account</a>
    · <a href="{{ route('account.aanbod') }}">Mijn aanbod</a>
  </p>
</section>
@endsection
