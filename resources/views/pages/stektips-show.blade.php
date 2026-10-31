@extends('layouts.site')

@section('title', $title)
@section('meta_description', $metaDescription)
@section('canonical', $canonical)

@push('head')
  @vite(['resources/css/stektips.css'])
@endpush

@php
  $fields = [
    'latijn' => 'Wetenschappelijke naam',
    'waarom' => 'Waarom',
    'methode' => 'Methode',
    'wanneer' => 'Wanneer',
    'welk_hout' => 'Welk materiaal',
    'lengte_snit' => 'Lengte en snit',
    'water_grond' => 'Water of grond',
    'licht' => 'Licht',
    'water' => 'Water (verzorging)',
    'hulpmiddel' => 'Hulpmiddel nodig?',
    'kans' => 'Moeilijkheid',
    'klaar' => 'Wanneer klaar om uit te delen',
    'ervaringen' => 'Ervaringen deelnemers',
    'bronnen' => 'Bronnen',
  ];
  $waarschuwing = trim((string) ($soort['waarschuwing'] ?? ''));
  $categorie = (string) ($soort['categorie'] ?? '');
@endphp

@section('content')
<section class="section wrap prose stektips-page">
  @isset($breadcrumbs)
  <nav class="stektips-breadcrumbs" aria-label="Broodkruimels">
    <ol>
      @foreach($breadcrumbs as $crumb)
        <li>
          @if(! $loop->last)
            <a href="{{ $crumb['url'] }}">{{ $crumb['naam'] }}</a>
          @else
            <span aria-current="page">{{ $crumb['naam'] }}</span>
          @endif
        </li>
      @endforeach
    </ol>
  </nav>
  @endisset

  <h1 class="page-title">{{ $pageHeading ?? ($soort['naam'].' stekken') }}</h1>
  @if(!empty($soort['kort']))
    <p class="muted">{{ $soort['kort'] }}@if($categorie !== '') · {{ $categorie }}@endif</p>
  @endif

  @isset($afbeelding)
    @include('partials.soort-afbeelding', ['afbeelding' => $afbeelding])
  @endisset

  @if($waarschuwing !== '')
    <aside class="warn-card stektips-waarschuwing" role="note">
      <p><x-icon name="waarschuwing" :size="20" /> {{ $waarschuwing }}</p>
    </aside>
  @endif

  @include('partials.stektips-seo-ctas', ['seoCtas' => $seoCtas ?? []])

  <div class="table-scroll" role="region" aria-label="Stektips-tabel" tabindex="0">
    <table class="table stektips-table">
      <tbody>
        @foreach($fields as $key => $label)
          @php
            $waarde = $soort[$key] ?? null;
            $isEmpty = ! is_string($waarde) && ! is_numeric($waarde)
                || (is_string($waarde) && trim($waarde) === '');
          @endphp
          @if(! $isEmpty)
            <tr>
              <th scope="row">{{ $label }}</th>
              <td>{{ $waarde }}</td>
            </tr>
          @endif
        @endforeach
      </tbody>
    </table>
  </div>

  @if(!empty($soort['inhoud']) && is_array($soort['inhoud']))
    <div class="stektips-artikel-body stektips-soort-inhoud">
      @foreach($soort['inhoud'] as $block)
        @php $type = $block['type'] ?? ''; @endphp
        @if($type === 'h2')
          <h2>{{ $block['tekst'] ?? '' }}</h2>
        @elseif($type === 'p')
          <p>{{ $block['tekst'] ?? '' }}</p>
        @elseif($type === 'ol' && !empty($block['items']))
          <ol>
            @foreach($block['items'] as $item)
              <li>{{ $item }}</li>
            @endforeach
          </ol>
        @elseif($type === 'ul' && !empty($block['items']))
          <ul>
            @foreach($block['items'] as $item)
              <li>{{ $item }}</li>
            @endforeach
          </ul>
        @endif
      @endforeach
    </div>
  @endif

  @if(!empty($verwanten))
    <h2 class="stektips-related-title">Verwante soorten</h2>
    <ul class="stektips-related">
      @foreach($verwanten as $verwant)
        <li>
          <a href="{{ route('stektips.show', ['slug' => $verwant['slug']]) }}">{{ $verwant['naam'] }}</a>
        </li>
      @endforeach
    </ul>
  @endif

  @if(!empty($gerelateerdeArtikelen))
    <h2 class="stektips-related-title">Gerelateerde artikelen</h2>
    <ul class="stektips-related">
      @foreach($gerelateerdeArtikelen as $artikel)
        <li>
          <a href="{{ route('stektips.artikel', ['slug' => $artikel['slug']]) }}">{{ $artikel['titel'] }}</a>
        </li>
      @endforeach
    </ul>
  @endif

  <p class="stektips-actions">
    <a class="btn" href="{{ $aanbodUrl }}">Gratis {{ $soort['naam'] }}-stekjes in je buurt</a>
    <a class="btn btn-secondary" href="{{ route('account.registreren') }}">Doe mee</a>
    <a href="{{ route('stektips.index') }}">Alle stektips</a>
  </p>
</section>
@endsection
