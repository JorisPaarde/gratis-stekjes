@extends('layouts.site')

@section('title', $title)
@section('meta_description', $metaDescription)
@section('canonical', $canonical)

@section('content')
<section class="section wrap prose">
  <h1 class="page-title">{{ $org['naam'] }}</h1>
  <p class="muted">{{ $org['plaats'] }}@if(!empty($org['type'])) · {{ $typeLabels[$org['type']] ?? $org['type'] }}@endif</p>

  <p>{{ $org['kort'] }}</p>

  <p>
    <a href="{{ $org['website'] }}" rel="noopener noreferrer" target="_blank">Website</a>
    ·
    <a href="{{ $org['bron_url'] }}" rel="noopener noreferrer" target="_blank">Bron</a>
    @foreach($extraLinks as $link)
      ·
      <a href="{{ $link['url'] }}" rel="noopener noreferrer" target="_blank">{{ $link['label'] }}</a>
    @endforeach
  </p>

  @if(count($deelplekken) > 0)
    <h2>Op de deelplekkenkaart</h2>
    <ul>
      @foreach($deelplekken as $plek)
        <li>
          {{ $plek->naam }} · {{ $plek->plaats }}
          <span class="muted">({{ $typeLabels[$plek->type] ?? $plek->type }})</span>
        </li>
      @endforeach
    </ul>
    <p><a href="{{ ($org['plaats'] ?? '') !== '' && ($org['plaats'] ?? '') !== 'Nederland' ? route('deelplekken', ['plaats' => $org['plaats']]) : route('deelplekken') }}">Bekijk deelplekken</a></p>
  @else
    <p class="muted">Geen bezoeklocatie op mijn <a href="{{ route('deelplekken') }}">deelplekkenkaart</a>; zie hun eigen site.</p>
  @endif

  @if(count($gerelateerd) > 0)
    <h2>Samenwerking</h2>
    <ul>
      @foreach($gerelateerd as $related)
        <li>
          <a href="{{ route('organisaties.show', ['slug' => $related['slug']]) }}">{{ $related['naam'] }}</a>
        </li>
      @endforeach
    </ul>
  @endif

  <p class="muted">
    <a href="{{ route('organisaties.index') }}">Alle organisaties</a> ·
    <a href="{{ route('voor-organisaties.aanmelden') }}">Zelf een plek aanmelden</a>
  </p>
</section>
@endsection
