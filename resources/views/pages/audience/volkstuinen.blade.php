@extends('layouts.site')

@section('title', $title)
@section('meta_description', $metaDescription)
@section('canonical', $canonical)

@section('content')
<x-audience-page
  eyebrow="Voor volkstuinverenigingen"
  heading="Stekjes delen binnen je eigen volkstuin"
  intro="Op het pad delen jullie al plantjes en stekjes. Via {{ config('site.name') }} ziet ook wie zaterdag niet op de tuin is wat er over is. Dat kan zonder WhatsApp-groep vol foto’s en zonder dat adressen openbaar worden."
  :current="$currentSeries"
  :series="$series"
>
  <h2>Hoe het werkt voor leden</h2>
  <ol class="list-plain">
    <li>Elk lid maakt een account en bevestigt het e-mailadres. Met datzelfde account kun je iets aanbieden én iets zoeken.</li>
    <li>Je zet op de site wat je gratis weggeeft of zoekt: stekjes, planten, zaden of oogst. Je geeft een postcode of plaats op, geen straatadres.</li>
    <li>Leden van dezelfde vereniging vinden elkaar door te zoeken op postcode of plaats, of via berichten op de site. Je e-mailadres blijft verborgen tot jullie allebei ja zeggen tegen verder contact.</li>
    <li>Jullie spreken een ophaalmoment af op de tuin of ergens anders. Een precieze plek deel je zelf, als je dat wilt.</li>
  </ol>
  <p>Er blijven drie spelregels: het is <strong>gratis</strong>, je haalt alles <strong>zelf op</strong>, en de enige tegenprestatie is een <strong>praatje</strong>.</p>

  <h2>Voor het bestuur</h2>
  <p>Een volkstuinvereniging kan zich als deelplek aanmelden met het formulier voor organisaties. Kies bij het type <em>tuinvereniging</em>. Na controle komt de plek op de pagina met <a href="{{ route('deelplekken') }}">deelplekken</a> en op de <a href="{{ route('gratis-planten.index') }}">stadspagina’s</a> in de buurt. Het is geen officieel partnerschap, het kost niets en er wordt niets verkocht via deze site.</p>
  <p>Later komt er voor elke vereniging een eigen link en QR-code om in het clubhuis op te hangen. Die poster bestaat nog niet. Wil je hem krijgen zodra hij klaar is? Mail me dan via <a href="{{ route('contact') }}">contact</a>.</p>

  <x-slot:cta>
    <div class="audience-cta-actions">
      <a class="btn" href="{{ route('account.registreren') }}">Zet het eerste stekje van jouw tuin online</a>
      <a class="btn btn-secondary" href="{{ route('voor-organisaties.aanmelden') }}">Vereniging aanmelden</a>
      <a class="btn btn-secondary" href="{{ route('contact') }}">Contact</a>
    </div>
    <p class="muted">Meer lezen: <a href="{{ route('voor-organisaties') }}">voor organisaties</a> · <a href="{{ route('hoe-werkt-het') }}">hoe werkt het</a></p>
  </x-slot:cta>
</x-audience-page>
@endsection
