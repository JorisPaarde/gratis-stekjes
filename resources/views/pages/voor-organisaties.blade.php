@extends('layouts.site')

@section('title', $title)
@section('meta_description', $metaDescription)
@section('canonical', $canonical)

@section('content')
<section class="section wrap prose">
  <h1 class="page-title">Voor organisaties</h1>
  <p class="section-intro">Heb je een voedselbos, tuinvereniging, plantenasiel, plantenbieb of ruilkast? Op {{ config('site.name') }} kunnen mensen in de buurt je vinden — naast aanbod van mens tot mens.</p>

  <h2>Wat je krijgt</h2>
  <ul>
    <li>Vermelding op <a href="{{ route('deelplekken') }}">deelplekken</a> (kaart + lijst, filterbaar op type en plaats).</li>
    <li>Zichtbaarheid op relevante <a href="{{ route('gratis-planten.index') }}">stadspagina’s</a> als je in de buurt van die stad ligt.</li>
    <li>Een korte pagina over je organisatie, met link naar je eigen site.</li>
  </ul>
  <p>Geen partnerschap, geen kosten, geen verkoop via deze site. Alleen een plek waar mensen je kunnen vinden om planten of stekjes te delen.</p>

  <p><a class="btn" href="{{ route('voor-organisaties.aanmelden') }}">Plek aanmelden</a></p>

  <h2>Organisaties op deze site</h2>
  <p>Hieronder staan organisaties waar we al naar linken, op basis van openbare bronnen. Mis je jezelf? Meld je plek aan.</p>
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
