@extends('layouts.site')

@section('title', $title)
@section('meta_description', $metaDescription)
@section('canonical', $canonical)

@push('head')
  @if(!empty($org['secties']))
    @vite(['resources/css/stektips.css'])
  @endif
  @if($vereniging)
    @vite(['resources/css/gratis-planten.css', 'resources/css/aanbod.css'])
  @endif
@endpush

@section('content')
<section class="section wrap prose">
  <h1 class="page-title">{{ $org['naam'] }}</h1>
  <p class="muted">{{ $org['plaats'] }}@if(!empty($org['type'])) · {{ $typeLabels[$org['type']] ?? $org['type'] }}@endif</p>

  <p @class(['section-intro' => !empty($org['secties'])])>{{ $org['kort'] }}</p>

  @if(!empty($org['secties']))
    <p class="org-acties">
      <a class="btn" href="{{ route('aanbod') }}"><x-icon name="zoeken" :size="18" /> Zoek gratis planten</a>
      <a class="btn btn-secondary" href="{{ auth()->check() ? route('aanbieden') : route('account.registreren') }}"><x-icon name="plus" :size="18" /> Planten over? Bied ze aan</a>
      @foreach($extraLinks as $link)
        <a class="btn btn-secondary" href="{{ $link['url'] }}" rel="noopener noreferrer" target="_blank">{{ $link['label'] }}</a>
      @endforeach
    </p>
    <div class="stektips-page stektips-artikel-body org-secties">
      @foreach($org['secties'] as $block)
        @include('partials.kennisbank-blok', ['block' => $block])
      @endforeach
    </div>
  @endif

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
    <p class="muted">Niet te bezoeken via mijn <a href="{{ route('deelplekken') }}">kaart</a>; zie hun eigen site.</p>
  @endif

  @if($vereniging && $blok)
    @include('partials.vereniging-leden', ['blok' => $blok, 'naam' => $org['naam'], 'slug' => $org['slug']])
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
