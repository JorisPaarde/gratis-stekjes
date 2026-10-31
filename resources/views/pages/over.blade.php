@extends('layouts.site')

@section('title', $title)
@section('meta_description', $metaDescription)
@section('canonical', $canonical)

@section('content')
<section class="section wrap prose">
  <h1 class="page-title">{{ $title }}</h1>

  <h2>Wie</h2>
  <p>Ik ben Joris Paardekooper. Ik maak en houd {{ config('site.name') }} (gratisplanten.nl) bij via jpwebcreation — code, teksten en hosting. Het is een plek waar mensen in Nederland en België gratis planten en stekjes aan elkaar uitdelen.</p>

  <h2>Waarom</h2>
  <p>Te veel goede planten verdwijnen op de compost of in de kliko, terwijl iemand in de buurt er blij mee zou zijn. En te vaak koop je iets nieuws terwijl een stekje van een buur net zo goed was geweest. Ik wil toetsen of mensen stekjes gaan uitdelen en ophalen — gratis, met een praatje als enige tegenprestatie. Eerst gericht op mensen die met voedselbossen en eetbare tuinen bezig zijn; de regio is open: heel Nederland en België.</p>

  <h2>Hoe het werkt</h2>
  <p>Drie spelregels: <strong>gratis</strong>, alleen <strong>fysiek ophalen</strong>, en de enige tegenprestatie is een <strong>praatje</strong>.</p>
  <ol class="list-plain">
    <li>Je maakt één account (e-mailbevestiging verplicht). Met dat account kun je zowel iets aanbieden als iets ophalen.</li>
    <li>Je zet op de site wat je gratis weggeeft, met een benaderde plek (postcode of plaats) — nooit een straatadres via de site.</li>
    <li>Je zoekt in de buurt en stuurt een bericht via de site. E-mail blijft verborgen.</li>
    <li>Jullie spreken onderling een ophaalmoment af. Exacte adressen deel je zelf, als je dat wilt.</li>
    <li>Pas bij wederzijds akkoord zien jullie elkaars contactgegevens.</li>
  </ol>
  <p>Daarnaast verzamel ik deelplekken (plantenasiels, plantenbiebs, ruilkasten, …), stektips en links naar verwante initiatieven — geen officiële partnerschappen, wel doorverwijzen naar plekken die ook over gratis of gedeeld groen gaan.</p>

  <h2>Gratis en zonder reclame</h2>
  <p>Meedoen kost niets. Stekjes blijven gratis — er is geen verkoop via deze site (dat bevestig je bij registratie en bij aanbod). Er draait geen advertentie- of trackingcode: geen ad-networks, geen analytiek-pixels van derden. De site is bedoeld als nuttig hulpmiddel, niet als verdienmodel op jouw aandacht.</p>

  <p><a href="{{ route('hoe-werkt-het') }}">Hoe werkt het</a> · <a href="{{ route('privacy') }}">Privacyverklaring</a> · <a href="{{ route('account.registreren') }}">Doe mee</a></p>
</section>
@endsection
