@extends('layouts.site')

@section('title', $title)
@section('meta_description', $metaDescription)
@section('canonical', $canonical)

@section('content')
<x-audience-page
  eyebrow="Voor natuurliefhebbers"
  heading="Meer groen, zonder één plant te kopen"
  intro="Elk stekje dat niet op de compost belandt, is een plant meer in een tuin. Via {{ config('site.name') }} deel je inheemse planten, bijenplanten en vlinderplanten gratis. Zo krijgt snoeiafval elders een nieuwe kans om te groeien."
  :current="$currentSeries"
  :series="$series"
>
  <h2>Inheemse planten gratis delen</h2>
  <ul>
    <li>Stekjes, scheuren, zaailingen of zaden van inheemse soorten die je overhebt gratis aanbieden in de buurt.</li>
    <li>Bijen- en vlinderplanten doorgeven in plaats van weggooien of opnieuw kopen.</li>
    <li>Zelf ophalen wat iemand anders kwijt wil: minder kopen, meer hergebruiken.</li>
    <li>Via <a href="{{ route('stektips.index') }}">stektips</a> leren hoe je planten vermeerdert, met bronnen en seizoensadvies.</li>
    <li>Op <a href="{{ route('deelplekken') }}">deelplekken</a> vaste plekken vinden: plantenasiels, plantenbiebs, ruilkasten en meer.</li>
  </ul>

  <h2>Waarom dit telt</h2>
  <p>Elke plant die blijft leven is een klein stukje meer groen. En omdat je alles zelf ophaalt, blijft delen dichtbij. Invasieve soorten bied je niet aan. Er staat een <a href="{{ route('invasieve-soorten') }}">duidelijke lijst</a> met die soorten.</p>
  <p>Er is geen verkoop via deze site en geen reclame. Het is alleen een plek om planten door te geven.</p>

  <x-slot:cta>
    <div class="audience-cta-actions">
      <a class="btn" href="{{ route('account.registreren') }}">Bied je snoeiafval aan als stek</a>
      <a class="btn btn-secondary" href="{{ route('aanbod') }}">Bekijk het aanbod</a>
      <a class="btn btn-secondary" href="{{ route('stektips.index') }}">Stektips</a>
    </div>
  </x-slot:cta>
</x-audience-page>
@endsection
