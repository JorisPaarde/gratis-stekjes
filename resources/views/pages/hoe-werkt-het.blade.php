@extends('layouts.site')

@section('title', $title)
@section('meta_description', $metaDescription)
@section('canonical', $canonical)
@section('og_image', '/assets/og/hoe-werkt-het.jpg')
@section('body_class', 'page-hoe-werkt-het')

@push('head')
  @vite(['resources/css/home.css', 'resources/css/hoe-werkt-het.css'])
@endpush

@section('content')
<section class="section wrap hwh-intro">
  <h1 class="hwh-titel">{{ $title }}</h1>
  <p class="section-intro">Mensen in Nederland en België geven planten, stekjes en zaden aan elkaar weg. Je kunt zoeken, iets weggeven of allebei, met één account.</p>

  <ul class="hwh-regels">
    <li>
      <span class="hwh-regel-icoon" aria-hidden="true"><x-icon name="hart" :size="22" /></span>
      <strong>Altijd gratis</strong>
      <span>Er wordt niets verkocht, ook niet voor een klein bedrag.</span>
    </li>
    <li>
      <span class="hwh-regel-icoon" aria-hidden="true"><x-icon name="ophalen" :size="22" /></span>
      <strong>Zelf ophalen</strong>
      <span>Niets wordt verstuurd. Je haalt het op in de buurt.</span>
    </li>
    <li>
      <span class="hwh-regel-icoon" aria-hidden="true"><x-icon name="bericht" :size="22" /></span>
      <strong>Een praatje</strong>
      <span>Geen verplichting, wel de bedoeling: zo leer je je buren kennen.</span>
    </li>
  </ul>
</section>

<section class="section section--mint" aria-label="Twee routes">
  <div class="wrap hwh-routes">
    <article class="hwh-route" aria-labelledby="route-zoek">
      <x-home-picture id="stappen" :show-credit="false" sizes="(max-width: 860px) 100vw, 50vw" />
      <h2 id="route-zoek">Ik zoek een plant</h2>
      <ol class="hwh-stappen">
        <li><strong>Kijk wat er in de buurt is.</strong> Zoek op postcode of plaats. Kijken kan zonder account.</li>
        <li><strong>Stuur een bericht.</strong> Daarvoor maak je een gratis account. Je e-mailadres blijft verborgen.</li>
        <li><strong>Spreek af en haal op.</strong> Kies samen een moment. Neem eventueel een tasje of potje mee.</li>
      </ol>
      <form class="hero-zoek hwh-zoek" method="get" action="{{ route('aanbod') }}" role="search">
        <label for="hwh-q">Wat is er bij jou in de buurt?</label>
        <div class="hero-zoek-veld">
          <input type="text" id="hwh-q" name="q" maxlength="120" required autocomplete="postal-code" placeholder="Postcode of plaats">
          <button class="btn" type="submit"><x-icon name="zoeken" :size="18" /> Zoek</button>
        </div>
      </form>
      <p class="hwh-extra">Liever een vaste plek? Bekijk de <a href="{{ route('deelplekken') }}">deelplekken</a>, zoals plantenasiels en zadenbibliotheken.</p>
    </article>

    <article class="hwh-route" aria-labelledby="route-geef">
      <x-home-picture id="delen" :show-credit="false" sizes="(max-width: 860px) 100vw, 50vw" />
      <h2 id="route-geef">Ik geef een plant weg</h2>
      <ol class="hwh-stappen">
        <li><strong>Maak één account.</strong> Bevestig je e-mailadres. Met hetzelfde account kun je ook zelf iets ophalen.</li>
        <li><strong>Zet het online.</strong> Plant, stekje of zaden, eventueel met foto. Je geeft je postcode of plaats op, nooit je straatadres.</li>
        <li><strong>Kies een moment.</strong> Je krijgt berichten via de site en spreekt af met wie het komt ophalen.</li>
      </ol>
      <p class="home-actions">
        <a class="btn" href="{{ auth()->check() ? route('account.aanbod') : route('account.registreren') }}"><x-icon name="plus" :size="18" /> {{ auth()->check() ? 'Zet iets online' : 'Maak gratis een account' }}</a>
      </p>
      <p class="hwh-extra">Je aanbod blijft 60 dagen staan. Daarna krijg je een mail om het met één klik te verlengen. Bied geen <a href="{{ route('invasieve-soorten') }}">invasieve soorten</a> aan.</p>
    </article>
  </div>
</section>

<section class="section wrap" aria-labelledby="veilig-titel">
  <h2 id="veilig-titel">Veilig en privé</h2>
  <ul class="hwh-veilig">
    <li><x-icon name="locatie" :size="20" /> <span>Op de kaart staat alleen ongeveer waar je woont, nooit je straatadres.</span></li>
    <li><x-icon name="bericht" :size="20" /> <span>Berichten gaan via de site. Je e-mailadres blijft verborgen.</span></li>
    <li><x-icon name="check" :size="20" /> <span>Contactgegevens zien jullie alleen als jullie in het gesprek allebei ja zeggen.</span></li>
    <li><x-icon name="waarschuwing" :size="20" /> <span>Klopt er iets niet? Je kunt iemand melden of blokkeren.</span></li>
  </ul>
  <p class="hwh-extra">Meer weten? Lees de <a href="{{ route('privacy') }}">privacyverklaring</a> of <a href="{{ route('contact') }}">neem contact op</a>.</p>
</section>
@endsection
