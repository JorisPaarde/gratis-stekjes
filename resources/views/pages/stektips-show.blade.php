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
  <h1 class="page-title">{{ $soort['naam'] }} stekken en gratis delen</h1>
  @if(!empty($soort['kort']))
    <p class="muted">{{ $soort['kort'] }}@if($categorie !== '') · {{ $categorie }}@endif</p>
  @endif

  @if($waarschuwing !== '')
    <aside class="warn-card stektips-waarschuwing" role="note">
      <p><x-icon name="waarschuwing" :size="20" /> {{ $waarschuwing }}</p>
    </aside>
  @endif

  <table class="table stektips-table">
    <tbody>
      @foreach($fields as $key => $label)
        @php $waarde = $soort[$key] ?? null; @endphp
        @if(is_string($waarde) || is_numeric($waarde))
          <tr>
            <th scope="row">{{ $label }}</th>
            <td>{{ $waarde !== '' ? $waarde : 'Volgt binnenkort' }}</td>
          </tr>
        @endif
      @endforeach
    </tbody>
  </table>

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

  <p class="stektips-actions">
    <a class="btn" href="{{ $aanbodUrl }}">Zoek {{ $soort['naam'] }} in het aanbod</a>
    <a class="btn btn-secondary" href="{{ route('account.registreren') }}">Doe mee</a>
    <a href="{{ route('stektips.index') }}">Alle stektips</a>
  </p>
</section>
@endsection
