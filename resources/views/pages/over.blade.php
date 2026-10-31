@extends('layouts.site')

@section('title', $title)
@section('meta_description', $metaDescription)
@section('canonical', $canonical)

@section('content')
<section class="section wrap prose">
  <h1 class="page-title">{{ $title }}</h1>

  <h2>Wie</h2>
  <p>Ik maak en beheer {{ config('site.name') }} (gratisplanten.nl): de website, de teksten en de hosting. Het is een plek waar mensen in Nederland en België gratis planten, stekjes en zaden aan elkaar geven.</p>

  <h2>Waarom</h2>
  <p>Te veel goede planten verdwijnen op de compost of in de kliko, terwijl iemand in de buurt er blij mee zou zijn. En vaak koop je iets nieuws, terwijl een stekje van een buur net zo goed was geweest. Ik wil meer groen en meer verbinding tussen mensen. Daarom wil ik uitproberen of mensen stekjes, planten en zaden gaan uitdelen en ophalen: gratis, met een praatje als enige tegenprestatie. Ik begin bij mensen die met voedselbossen en eetbare tuinen bezig zijn, maar iedereen in Nederland en België mag meedoen.</p>

  <h2>Hoe het werkt</h2>
  <p>Drie spelregels: het is <strong>gratis</strong>, je haalt alles <strong>zelf op</strong>, en de enige tegenprestatie is een <strong>praatje</strong>.</p>
  <ol class="list-plain">
    <li>Je maakt één account en bevestigt je e-mailadres. Daarmee kun je iets aanbieden én iets ophalen.</li>
    <li>Je zet op de site wat je gratis weggeeft. Je geeft een postcode of plaats op, nooit een straatadres.</li>
    <li>Je zoekt in de buurt en stuurt een bericht via de site. Je e-mailadres blijft verborgen.</li>
    <li>Jullie spreken samen een ophaalmoment af. Je adres deel je zelf, als je dat wilt.</li>
    <li>Alleen als jullie allebei ja zeggen, zien jullie elkaars contactgegevens.</li>
  </ol>
  <p>Daarnaast verzamel ik deelplekken (zoals plantenasiels, plantenbiebs en ruilkasten), stektips en links naar andere initiatieven. Dat zijn geen officiële samenwerkingen. Ik verwijs naar plekken waar ook gratis of gedeeld groen centraal staat.</p>

  <h2>Gratis en zonder reclame</h2>
  <p>Meedoen kost niets. Alles is gratis en er wordt niets verkocht via deze site (dat bevestig je bij het aanmaken van een account en bij je aanbod). Er zit geen reclame op de site en geen meetcode van derden die je volgt. De site is er om te helpen, niet om aan jouw aandacht te verdienen.</p>

  <p><a href="{{ route('hoe-werkt-het') }}">Hoe werkt het</a> · <a href="{{ route('privacy') }}">Privacyverklaring</a> · <a href="{{ route('account.registreren') }}">Doe mee</a></p>
</section>
@endsection
