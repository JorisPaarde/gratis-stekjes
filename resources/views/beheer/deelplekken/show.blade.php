@extends('layouts.site')

@section('title', $title)
@section('meta_description', 'Beheer')
@section('canonical', $canonical)

@push('head')
  @vite(['resources/css/admin.css'])
  <meta name="robots" content="noindex, nofollow">
@endpush

@section('content')
<section class="section wrap beheer-page">
  @include('beheer.partials.nav')

  <p><a href="{{ route('beheer.deelplekken.index', ['zichtbaar' => $plek->visible ? 'zichtbaar' : 'verborgen']) }}">← Deelplekken</a></p>

  <h1 class="page-title">{{ $plek->naam }}</h1>
  <p>
    @if($plek->visible)
      <span class="status status--ok">Zichtbaar</span>
    @else
      <span class="status status--uit">Verborgen</span>
    @endif
    <span class="muted">· {{ $typeLabels[$plek->type] ?? $plek->type }}</span>
  </p>

  <dl class="beheer-dl">
    <dt>Plaats</dt><dd>{{ $plek->plaats }}@if($plek->provincie) <span class="muted">({{ $plek->provincie }})</span>@endif</dd>
    <dt>Adres</dt><dd>{{ $plek->adres !== '' ? $plek->adres : '—' }}</dd>
    <dt>Slug</dt><dd><code>{{ $plek->slug }}</code></dd>
    <dt>Bron</dt>
    <dd>
      @if($plek->bron_url !== '')
        <a href="{{ $plek->bron_url }}" rel="noopener noreferrer" target="_blank">Website</a>
      @else
        —
      @endif
    </dd>
    <dt>Bron in app</dt><dd>{{ $plek->source }} · {{ $plek->status }}</dd>
  </dl>

  @if(! $plek->visible)
    <div class="beheer-gevaar">
      <h2>Verwijderen</h2>
      <p>Deze plek staat niet op de kaart. Definitief verwijderen wist de rij en voorkomt dat de seeder hem terugzet.</p>
      <a class="btn btn-gevaar btn-klein" href="{{ route('beheer.deelplekken.destroy.confirm', $plek) }}">Verwijderen…</a>
    </div>
  @else
    <p class="muted">Zichtbare deelplekken kun je hier niet verwijderen.</p>
  @endif
</section>
@endsection
