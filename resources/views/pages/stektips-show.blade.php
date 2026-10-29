@extends('layouts.site')

@section('title', $title)
@section('meta_description', $metaDescription)
@section('canonical', $canonical)

@push('head')
  @vite(['resources/css/stektips.css'])
@endpush

@php
  $fields = [
    'latijn' => 'Latijnse naam',
    'waarom' => 'Waarom in een voedselbos',
    'methode' => 'Methode',
    'wanneer' => 'Wanneer',
    'welk_hout' => 'Welk hout',
    'lengte_snit' => 'Lengte en snit',
    'water_grond' => 'Water of grond',
    'hulpmiddel' => 'Hulpmiddel nodig?',
    'kans' => 'Slagingskans',
    'klaar' => 'Wanneer klaar om uit te delen',
    'ervaringen' => 'Ervaringen deelnemers',
    'bronnen' => 'Bronnen',
  ];
@endphp

@section('content')
<section class="section wrap prose stektips-page">
  <h1 class="page-title">{{ $soort['naam'] }}</h1>
  @if(!empty($soort['kort']))
    <p class="muted">{{ $soort['kort'] }}</p>
  @endif
  <table class="table stektips-table">
    <tbody>
      @foreach($fields as $key => $label)
        <tr>
          <th scope="row">{{ $label }}</th>
          <td>{{ $soort[$key] ?? 'Volgt binnenkort' }}</td>
        </tr>
      @endforeach
    </tbody>
  </table>
  <p class="stektips-actions">
    <a class="btn" href="{{ route('aanbod') }}">Naar het aanbod</a>
    <a class="btn btn-secondary" href="{{ route('account.registreren') }}">Doe mee</a>
    <a href="{{ route('stektips.index') }}">Alle stektips</a>
  </p>
</section>
@endsection
