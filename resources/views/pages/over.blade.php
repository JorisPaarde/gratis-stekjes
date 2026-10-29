@extends('layouts.site')

@section('title', $title)
@section('meta_description', $metaDescription)
@section('canonical', $canonical)

@section('content')
<section class="section wrap prose">
  <h1 class="page-title">Over {{ config('site.name') }}</h1>

  <h2>Wie</h2>
  <p>Ik ben Joris Paardekooper. Ik bouw {{ config('site.name') }} (gratisplanten.nl) via jpwebcreation — een plek waar mensen in Nederland gratis planten en stekjes aan elkaar uitdelen.</p>

  <h2>Waarom</h2>
  <p>Te veel goede planten verdwijnen op de compost of in de kliko, terwijl iemand in de buurt er blij mee zou zijn. En te vaak koop je iets nieuws terwijl een stekje van een buur net zo goed was geweest. Ik wil toetsen of mensen stekjes gaan uitdelen en ophalen — gratis, met een praatje als enige tegenprestatie. Eerst gericht op mensen die met voedselbossen en eetbare tuinen bezig zijn; de regio is open: heel Nederland.</p>
  <p>Op de site zelf zeggen we <em>we</em>, zoals op de homepage: we willen groen dichterbij huis, mensen bij elkaar brengen, en meedoen aan iets dat groeit. Achter die “we” zit voorlopig vooral ik die de code, teksten en hosting bijhoudt.</p>

  <h2>Hoe het werkt</h2>
  <p>Drie spelregels: <strong>gratis</strong>, alleen <strong>fysiek ophalen</strong>, en de enige tegenprestatie is een <strong>praatje</strong>.</p>
  <ol class="list-plain">
    <li>Je maakt een account (e-mailbevestiging verplicht).</li>
    <li>Uitdelers zetten op de site wat ze gratis weggeven, met een benaderde plek (postcode of plaats) — nooit een straatadres via de site.</li>
    <li>Ophalers zoeken in de buurt en sturen een bericht via de site. E-mail blijft verborgen.</li>
    <li>Jullie spreken onderling een ophaalmoment af. Exacte adressen deel je zelf, als je dat wilt.</li>
    <li>Pas bij wederzijds akkoord zien jullie elkaars contactgegevens.</li>
  </ol>
  <p>Daarnaast verzamelen we deelplekken (plantenasiels, plantenbiebs, ruilkasten, …), stektips en links naar verwante initiatieven — geen officiële partnerschappen, wel doorverwijzen naar plekken die ook over gratis of gedeeld groen gaan.</p>

  <h2>Gratis en zonder reclame</h2>
  <p>Meedoen kost niets. Stekjes blijven gratis — er is geen verkoop via deze site (dat bevestig je bij registratie en bij aanbod). Er draait geen advertentie- of trackingcode: geen ad-networks, geen analytiek-pixels van derden. De site is bedoeld als nuttig hulpmiddel, niet als verdienmodel op jouw aandacht.</p>

  <p><a href="{{ route('hoe-werkt-het') }}">Hoe werkt het</a> · <a href="{{ route('privacy') }}">Privacyverklaring</a> · <a href="{{ route('account.registreren') }}">Doe mee</a></p>
</section>
@endsection
