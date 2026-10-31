@extends('layouts.site')

@section('title', $title)
@section('meta_description', $metaDescription)
@section('canonical', $canonical)

@section('content')
<x-audience-page
  eyebrow="Voor natuurliefhebbers"
  heading="Meer planten, meer groen"
  intro="Als je houdt van planten — in de tuin, op het balkon of in huis — weet je: één stekje kan uitgroeien tot iets groots. {{ config('site.name') }} is er zodat goede planten niet op de compost belanden, maar een nieuw plekje krijgen."
  :current="$currentSeries"
  :series="$series"
>
  <h2>Wat jij kunt doen</h2>
  <ul>
    <li>Stekjes, scheuren of zaailingen die je overhebt gratis aanbieden in de buurt.</li>
    <li>Zelf ophalen wat iemand anders kwijt wil — minder kopen, meer hergebruiken.</li>
    <li>Via <a href="{{ route('stektips.index') }}">stektips</a> leren hoe je planten vermeerdert, met bronnen en seizoensadvies.</li>
    <li>Op <a href="{{ route('deelplekken') }}">deelplekken</a> vaste plekken vinden: plantenasiels, plantenbiebs, ruilkasten en meer.</li>
  </ul>

  <h2>Waarom dit telt</h2>
  <p>Elke plant die blijft leven is een klein stukje meer groen. En omdat ophalen fysiek gebeurt, blijft delen lokaal: minder transport, meer contact met mensen in je buurt. Invasieve soorten hoor je niet aan te bieden — daar staat een <a href="{{ route('invasieve-soorten') }}">duidelijke lijst</a> voor.</p>
  <p>Geen verkoop via deze site, geen reclame, geen partnerschap-claims. Alleen een plek om planten door te geven.</p>

  <x-slot:cta>
    <div class="audience-cta-actions">
      <a class="btn" href="{{ route('account.registreren') }}">Doe mee</a>
      <a class="btn btn-secondary" href="{{ route('aanbod') }}">Bekijk het aanbod</a>
      <a class="btn btn-secondary" href="{{ route('stektips.index') }}">Stektips</a>
    </div>
  </x-slot:cta>
</x-audience-page>
@endsection
