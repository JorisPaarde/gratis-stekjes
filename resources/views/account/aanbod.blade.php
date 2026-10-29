@extends('layouts.site')

@section('title', $title)
@section('meta_description', $metaDescription)
@section('canonical', $canonical)

@section('content')
<section class="section wrap">
  <h1 class="page-title">Mijn aanbod</h1>
  <p class="section-intro">Vrije tekst over wat je hebt of zoekt. Aanbod staat {{ (int) config('stekjes.aanbod_ttl_days', 60) }} dagen in zoeken en op de kaart; daarna blijft het hier zichtbaar zodat je kunt verlengen.</p>

  @if($errors->any())
    <p class="error" role="alert">{{ $errors->first() }}</p>
  @endif

  <form class="form" method="post" action="{{ route('account.aanbod') }}">
    @csrf
    @include('partials.honeypot')
    <label>Type *
      <select name="type" required>
        <option value="uitdelen" @selected(old('type') === 'uitdelen')>Ik deel uit</option>
        <option value="ophalen" @selected(old('type') === 'ophalen')>Ik zoek / wil ophalen</option>
      </select>
    </label>
    <label>Wat heb je of zoek je? *
      <textarea name="tekst" required maxlength="2000" placeholder="Bijv. zwarte bessenstekken, vanaf november, zaterdagochtend">{{ old('tekst') }}</textarea>
    </label>
    <label class="checkbox">
      <input type="checkbox" name="gratis_akkoord" value="1" required @checked(old('gratis_akkoord'))>
      <span>Alleen gratis, geen verkoop.</span>
    </label>
    <button class="btn" type="submit">Plaatsen</button>
  </form>

  <h2>Jouw items</h2>
  @if($mine->isEmpty())
    <p class="muted">Nog niets geplaatst. Publiek zoeken: <a href="{{ route('aanbod') }}">aanbod &amp; kaart</a>.</p>
  @else
    <ul class="zoek-lijst">
      @foreach($mine as $row)
        @php($expired = $row->isExpired())
        <li class="zoek-item">
          <span class="badge">{{ $row->type }}</span>
          @if(!$row->visible || $expired)
            <span class="muted">
              (@if(!$row->visible)verborgen@endif
              @if(!$row->visible && $expired), @endif
              @if($expired)verlopen (niet meer in zoeken/kaart)@endif)
            </span>
          @endif
          <p>{{ $row->tekst }}</p>
          <p class="muted">Geldig tot {{ $row->expires_at->timezone('Europe/Amsterdam')->format('d-m-Y') }}</p>
          <form method="post" action="{{ route('account.aanbod.renew', $row) }}">
            @csrf
            <button class="btn btn-secondary" type="submit">Nog {{ (int) config('stekjes.aanbod_ttl_days', 60) }} dagen verlengen</button>
          </form>
        </li>
      @endforeach
    </ul>
  @endif

  <p class="muted" style="margin-top:1rem">
    <a href="{{ route('account.profiel') }}">Profiel / locatie</a>
    · <a href="{{ route('aanbod') }}">Publieke kaart</a>
  </p>
</section>
@endsection
