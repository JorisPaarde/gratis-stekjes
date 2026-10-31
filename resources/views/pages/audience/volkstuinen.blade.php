@extends('layouts.site')

@section('title', $title)
@section('meta_description', $metaDescription)
@section('canonical', $canonical)

@section('content')
<x-audience-page
  eyebrow="Voor volkstuinverenigingen"
  heading="Delen binnen je vereniging"
  intro="Op een volkstuin groeit vaak meer dan je zelf kwijt kunt: stekjes, zaailingen, zaden, plantjes die iemand anders juist mist. {{ config('site.name') }} maakt dat delen binnen je vereniging eenvoudig — zonder WhatsApp-groep vol foto’s en zonder dat adressen publiek komen te liggen."
  :current="$currentSeries"
  :series="$series"
>
  <h2>Hoe het werkt voor leden</h2>
  <ol class="list-plain">
    <li>Elk lid maakt een account (e-mailbevestiging verplicht). Met datzelfde account kun je iets aanbieden én iets zoeken.</li>
    <li>Je zet op de site wat je gratis weggeeft of zoekt — stekjes, planten of zaden — met een benaderde plek (postcode of plaats). Geen straatadres via de site.</li>
    <li>Leden van dezelfde vereniging vinden elkaar via zoeken op postcode of plaats, of via berichten op de site. E-mail blijft verborgen tot jullie allebei ja zeggen voor verder contact.</li>
    <li>Jullie spreken een ophaalmoment af op de tuin of elders. Exacte plekken deel je zelf, als je dat wilt.</li>
  </ol>
  <p>Drie spelregels blijven gelden: <strong>gratis</strong>, alleen <strong>fysiek ophalen</strong>, en de enige tegenprestatie is een <strong>praatje</strong>.</p>

  <h2>Hoe je vereniging meedoet</h2>
  <p>Een volkstuinvereniging kan zich als deelplek aanmelden via het bestaande formulier voor organisaties. Kies type <em>tuinvereniging</em>. Na controle kan de plek zichtbaar worden op <a href="{{ route('deelplekken') }}">deelplekken</a> en op relevante <a href="{{ route('gratis-planten.index') }}">stadspagina’s</a> — zodat mensen je tuinvereniging kunnen vinden. Geen partnerschap, geen kosten, geen verkoop via deze site.</p>
  <p>Wil je eerst iets vragen? Gebruik het <a href="{{ route('contact') }}">contactadres</a> op de site. Ik stuur vanuit deze pagina geen massamails naar verenigingen.</p>

  <x-slot:cta>
    <div class="audience-cta-actions">
      <a class="btn" href="{{ route('account.registreren') }}">Account maken</a>
      <a class="btn btn-secondary" href="{{ route('voor-organisaties.aanmelden') }}">Vereniging aanmelden</a>
      <a class="btn btn-secondary" href="{{ route('contact') }}">Contact</a>
    </div>
    <p class="muted">Meer over organisaties: <a href="{{ route('voor-organisaties') }}">voor organisaties</a> · <a href="{{ route('hoe-werkt-het') }}">hoe werkt het</a></p>
  </x-slot:cta>
</x-audience-page>
@endsection
