@extends('layouts.site')

@section('title', $title)
@section('meta_description', $metaDescription)
@section('canonical', $canonical)

@section('content')
<section class="section wrap">
  <h1 class="page-title">Profiel</h1>
  <p class="section-intro">Publiek: voornaam/bijnaam en benaderde locatie (postcode-4 of plaats). Telefoon alleen later zichtbaar bij wederzijds “ja” in een gesprek.</p>

  @if($errors->any())
    <p class="error" role="alert">{{ $errors->first() }}</p>
  @endif

  <form class="form" method="post" action="{{ route('account.profiel') }}">
    @csrf
    <label>Voornaam of bijnaam *
      <input name="voornaam" required maxlength="80" autocomplete="nickname" value="{{ old('voornaam', $user->name) }}">
    </label>
    <label>Postcode (4 cijfers) of plaats *
      <input name="locatie" required maxlength="120" value="{{ old('locatie', $user->locatie_label) }}" placeholder="Bijv. 2011 of Haarlem">
      <span class="hint">Ik sla alleen een afgeronde plek op, geen straatadres.</span>
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
