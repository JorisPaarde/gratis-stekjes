@extends('layouts.site')

@section('title', $title)
@section('meta_description', $metaDescription)
@section('canonical', $canonical)

@section('content')
<section class="section wrap prose">
  <h1 class="page-title">Voor organisaties</h1>
  <p class="section-intro">Heb je een voedselbos, tuinvereniging, plantenasiel, plantenbieb of ruilkast? Op {{ config('site.name') }} kunnen mensen in de buurt je vinden, naast het aanbod van mens tot mens.</p>

  <h2>Wat je krijgt</h2>
  <ul>
    <li>Een vermelding bij de <a href="{{ route('deelplekken') }}">deelplekken</a>, op de kaart en in de lijst (te filteren op soort en plaats).</li>
    <li>Lig je in de buurt van een van de steden, dan sta je ook op die <a href="{{ route('gratis-planten.index') }}">stadspagina</a>.</li>
    <li>Een korte pagina over je organisatie, met een link naar je eigen site.</li>
  </ul>
  <p>Het is geen officieel partnerschap en het kost niets. Er wordt niets verkocht via deze site. Het is alleen een plek waar mensen je kunnen vinden om planten, stekjes of zaden gratis te delen.</p>

  <p><a class="btn" href="{{ route('voor-organisaties.aanmelden') }}">Plek aanmelden</a></p>

  <h2>Organisaties op deze site</h2>
  <p>Hieronder staan organisaties waar ik al naar verwijs, op basis van openbare bronnen. Staat jouw plek er niet bij? Meld hem dan aan.</p>
  <ul>
    @foreach($organisaties as $org)
      <li>
        <a href="{{ route('organisaties.show', ['slug' => $org['slug']]) }}">{{ $org['naam'] }}</a>
        @if(!empty($org['plaats']))
          <span class="muted"> · {{ $org['plaats'] }}</span>
        @endif
      </li>
    @endforeach
  </ul>
  <p class="muted"><a href="{{ route('organisaties.index') }}">Alle organisaties</a> · <a href="{{ route('deelplekken') }}">Deelplekken</a></p>
</section>
@endsection
