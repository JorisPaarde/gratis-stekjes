@extends('layouts.site')

@section('title', $title)
@section('meta_description', $metaDescription)
@section('canonical', $canonical)

@section('content')
<x-audience-page
  eyebrow="Voor wie meer contact zoekt"
  heading="Leer je buren kennen via een stekje"
  intro="Het stekje is het excuus, het praatje is het punt. Of je nu bij een plantenbieb, stekjesmarkt of buurttuin hangt — via {{ config('site.name') }} haal je iets op bij iemand in de buurt en spreek je elkaar even."
  :current="$currentSeries"
  :series="$series"
>
  <h2>Delen als ontmoeting</h2>
  <p>Je zoekt in de buurt wat iemand gratis weggeeft. Je stuurt een bericht via de site. E-mailadressen blijven verborgen tot jullie allebei ja zeggen. Daarna spreek je een ophaalmoment af — op de stoep, in de tuin, bij een <a href="{{ route('deelplekken') }}">deelplek</a>.</p>
  <p>De enige tegenprestatie is een praatje. Geen betaling, geen ruilplicht, geen verkoop via de site.</p>

  <h2>Zo begin je</h2>
  <ol class="list-plain">
    <li>Maak een account en bevestig je e-mail — of kijk eerst op de <a href="{{ route('aanbod') }}">kaart</a> wat er bij jou in de buurt staat.</li>
    <li>Zet iets klaar om weg te geven, of stuur een bericht over iets dat je wilt ophalen.</li>
    <li>Spreek af en haal het plantje op. Vaak blijft er een praatje van over.</li>
  </ol>
  <p>Wil je vooral anderen helpen de site te verspreiden? Kijk op <a href="{{ route('help-mee') }}">Help mee</a>.</p>

  <x-slot:cta>
    <div class="audience-cta-actions">
      <a class="btn" href="{{ route('aanbod') }}">Kijk wat je buren weggeven</a>
      <a class="btn btn-secondary" href="{{ route('account.registreren') }}">Account maken</a>
      <a class="btn btn-secondary" href="{{ route('hoe-werkt-het') }}">Hoe werkt het</a>
    </div>
  </x-slot:cta>
</x-audience-page>
@endsection
