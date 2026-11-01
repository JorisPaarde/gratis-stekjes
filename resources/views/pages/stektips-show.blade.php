@extends('layouts.site')

@section('title', $title)
@section('meta_description', $metaDescription)
@section('canonical', $canonical)
@section('og_image', is_file(public_path('assets/og/soort/'.$soort['slug'].'.jpg')) ? '/assets/og/soort/'.$soort['slug'].'.jpg' : '/assets/og/stektips.jpg')

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
    'water' => 'Water geven',
    'hulpmiddel' => 'Hulpmiddel nodig?',
    'kans' => 'Moeilijkheid',
    'klaar' => 'Wanneer je het kunt uitdelen',
    'ervaringen' => 'Ervaringen van anderen',
    'bronnen' => 'Bronnen',
  ];
  $waarschuwing = trim((string) ($soort['waarschuwing'] ?? ''));
  $categorie = (string) ($soort['categorie'] ?? '');
  $txt = static fn (string $key): string => trim((string) ($soort[$key] ?? ''));
  $hulpmiddel = $txt('hulpmiddel');
  $nodig = $txt('welk_hout');
  if ($hulpmiddel !== '' && ! preg_match('/^niet nodig/i', $hulpmiddel)) {
      $nodig .= ($nodig !== '' ? '. ' : '').'Handig: '.$hulpmiddel;
  }
  $verzorgen = trim(implode(' ', array_filter([
      $txt('licht') !== '' ? 'Licht: '.$txt('licht').'.' : '',
      $txt('water') !== '' ? 'Water: '.$txt('water').'.' : '',
  ])));
  $werkwoord = trim((string) ($soort['seo_werkwoord'] ?? '')) ?: 'stekken';
  $labels = [
      'scheuren' => ['Kies het moment', 'Kies de plant', 'Deel de plant', 'Plant de delen', 'Verzorg', 'Klaar om te delen'],
      'zaaien' => ['Kies het moment', 'Kies het zaad', 'Zaai', 'Laat kiemen', 'Verzorg', 'Klaar om te delen'],
  ][$werkwoord] ?? ['Kies het moment', 'Kies je stek', 'Knip de stek', 'Laat wortelen', 'Verzorg', 'Klaar om te delen'];
  // Stappen in de volgorde waarin je ze doet; lege velden vallen weg.
  $stappen = array_values(array_filter(array_map(
      static fn (string $titel, string $tekst): array => ['titel' => $titel, 'tekst' => $tekst],
      $labels,
      [$txt('wanneer'), $nodig, $txt('lengte_snit'), $txt('water_grond'), $verzorgen, $txt('klaar')],
  ), static fn (array $stap): bool => $stap['tekst'] !== ''));
  $snel = array_values(array_filter([
      ['label' => 'Methode', 'waarde' => $txt('methode')],
      ['label' => 'Moeilijkheid', 'waarde' => $txt('kans')],
      ['label' => 'Beste tijd', 'waarde' => $txt('wanneer')],
  ], static fn (array $r): bool => $r['waarde'] !== ''));
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

  @if($snel !== [])
    <dl class="stektips-snel" aria-label="In het kort">
      @foreach($snel as $r)
        <div><dt>{{ $r['label'] }}</dt><dd>{{ $r['waarde'] }}</dd></div>
      @endforeach
    </dl>
  @endif

  @if($stappen !== [])
    <h2 id="stappen">Stap voor stap</h2>
    <ol class="stektips-stappen">
      @foreach($stappen as $stap)
        <li><strong>{{ $stap['titel'] }}.</strong> {{ $stap['tekst'] }}</li>
      @endforeach
    </ol>
  @endif

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

  <h2 class="stektips-related-title">Alles op een rij</h2>
  <div class="table-scroll" role="region" aria-label="Stektips in een overzicht" tabindex="0">
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

  @include('partials.stektips-seo-ctas', ['seoCtas' => $seoCtas ?? []])

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
