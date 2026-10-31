@extends('layouts.site')

@section('title', $title)
@section('meta_description', $metaDescription)
@section('canonical', $canonical)
@section('body_class', 'page-deelplekken')

@push('head')
  <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" integrity="sha256-p4NxAoJBhIIN+hmNHrzRCf9tD/miZyoHS5obTRR9BMY=" crossorigin="">
  <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js" integrity="sha256-20nQCchB9co0qIjJZRGuk2/Z9VM+kNiyxNV1lvTlZBo=" crossorigin=""></script>
  <script>
    window.STEKJES_DEELPLEKKEN = {
      api: @json(route('api.deelplekken')),
      type: @json($filters['type']),
      provincie: @json($filters['provincie']),
      plaats: @json($filters['plaats']),
      types: @json($typeLabels)
    };
  </script>
  @vite(['resources/css/deelplekken.css'])
@endpush

@section('content')
<section class="section wrap deelplekken-page">
  <h1 class="page-title">Plantenasiels, plantenbiebs en ruilkastjes in Nederland en België</h1>
  <p class="section-intro">Landelijk overzicht van plekken waar je planten, stekjes of zaden kunt brengen, ruilen of ophalen — plantenasiels, plantenbiebs, ruilkasten, stekjesruilpunten en zaadbibliotheken. Gegevens komen uit openbare bronnen; check altijd de bron voor actuele openingstijden.</p>
  <p class="muted"><a href="{{ route('voor-organisaties') }}">Organisatie of plek aanmelden</a> · <a href="{{ route('over') }}">Plek missen of fout? Meld het</a> · ook op de <a href="{{ route('aanbod') }}">aanbodkaart</a>.</p>

  <form class="form deelplekken-filters" method="get" action="{{ route('deelplekken') }}" style="max-width:100%;margin:1rem 0">
    <div class="deelplekken-filter-grid">
      <label>Type
        <select name="type">
          <option value="">Alle types</option>
          @foreach($typeLabels as $key => $label)
            <option value="{{ $key }}" @selected($filters['type'] === $key)>{{ $label }}</option>
          @endforeach
        </select>
      </label>
      <label>Provincie
        <select name="provincie">
          <option value="">Alle provincies</option>
          @foreach($provincies as $p)
            <option value="{{ $p }}" @selected($filters['provincie'] === $p)>{{ $p }}</option>
          @endforeach
        </select>
      </label>
      <label>Plaats
        <input type="search" name="plaats" value="{{ $filters['plaats'] }}" maxlength="120" placeholder="Bijv. Utrecht">
      </label>
      <button class="btn" type="submit">Filter</button>
    </div>
  </form>

  <div class="search-layout deelplekken-layout">
    <div id="deelplekken-kaart" role="img" aria-label="Kaart met deelplekken voor planten"></div>
    <div>
      <p class="muted" id="deelplekken-status">{{ $items->count() }} {{ $items->count() === 1 ? 'plek' : 'plekken' }}</p>
      <ul class="zoek-lijst deelplekken-lijst" id="deelplekken-lijst">
        @forelse($items as $plek)
          <li class="zoek-item zoek-item--deelplek" data-kind="deelplek" data-type="{{ $plek->type }}">
            <span class="badge badge--{{ $plek->type }}">{{ $typeLabels[$plek->type] ?? $plek->type }}</span>
            <h2>{{ $plek->naam }} · {{ $plek->plaats }}</h2>
            <p>{{ $plek->adres !== '' ? $plek->adres : $plek->plaats }}@if($plek->provincie) · {{ $plek->provincie }}@endif</p>
            <p><a href="{{ $plek->bron_url }}" rel="noopener noreferrer" target="_blank">Bron</a>
              <span class="muted"> · gecontroleerd {{ $plek->geraadpleegd?->format('Y-m-d') }}</span></p>
          </li>
        @empty
          <li class="zoek-item">
            <p>Geen deelplekken voor deze filters. <a href="{{ route('deelplekken') }}">Toon alles</a></p>
          </li>
        @endforelse
      </ul>
    </div>
  </div>

  <p class="muted" style="margin-top:1rem">Kaarttegels: © OpenStreetMap-bijdragers. Locaties benaderd op openbaar adres uit de bron. Dit is geen officiële partnerschaplijst.</p>
</section>
@endsection

@push('scripts')
  @vite(['resources/js/deelplekken.js'])
@endpush
