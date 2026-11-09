@extends('layouts.site')

@section('title', $title)
@section('meta_description', $metaDescription)
@section('canonical', $canonical)

@section('content')
<section class="section wrap prose in-de-media-page">
  <h1 class="page-title">Gratis planten in de media</h1>
  <p class="section-intro">Artikelen en vermeldingen over {{ config('site.name') }}. We zetten hier wat er over ons verschijnt — in eigen woorden, met een link naar het origineel.</p>

  @if($vermeldingen === [])
    <p class="muted" role="status">Er staan nog geen vermeldingen. Check later opnieuw, of mail ons als je er een hebt gezien.</p>
  @else
    <ul class="media-lijst">
      @foreach($vermeldingen as $item)
        <li class="media-item">
          <p class="media-meta">
            <span class="media-medium">{{ $item['medium'] }}</span>
            <span class="media-sep" aria-hidden="true">·</span>
            <time datetime="{{ $item['datum'] }}">{{ $item['datum_label'] }}</time>
          </p>
          <h2 class="media-kop">
            <a href="{{ $item['url'] }}" target="_blank" rel="noopener noreferrer">{{ $item['kop'] }}</a>
          </h2>
          <p class="media-samenvatting">{{ $item['samenvatting'] }}</p>
          <p class="media-lees">
            <a href="{{ $item['url'] }}" target="_blank" rel="noopener noreferrer">Lees het artikel<span class="visually-hidden">: {{ $item['kop'] }}</span></a>
          </p>
        </li>
      @endforeach
    </ul>
  @endif

  <aside class="media-journalist" aria-labelledby="journalist-titel">
    <h2 id="journalist-titel">Ben je journalist?</h2>
    <p>Schrijf je over delen, groen of buurtinitiatieven? Mail gerust naar <a href="mailto:{{ config('site.contact_email') }}">{{ config('site.contact_email') }}</a> — we denken graag mee over feiten, cijfers en interviews.</p>
  </aside>
</section>
@endsection
