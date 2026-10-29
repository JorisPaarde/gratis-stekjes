@extends('layouts.site')

@section('title', $title)
@section('meta_description', $metaDescription)
@section('canonical', $canonical)

@push('head')
  @vite(['resources/css/stektips.css'])
@endpush

@section('content')
<section class="section wrap prose stektips-page stektips-artikel">
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

  <h1 class="page-title">{{ $artikel['titel'] }}</h1>
  @if(!empty($artikel['samenvatting']))
    <p class="section-intro">{{ $artikel['samenvatting'] }}</p>
  @endif

  <ul class="stektips-meta-list">
    @if(!empty($artikel['categorieen']))
      <li>
        <span class="stektips-meta-label">Onderwerpen</span>
        @foreach($artikel['categorieen'] as $catSlug)
          <a href="{{ route('stektips.index', ['categorie' => $catSlug]) }}">{{ $categoryLabels[$catSlug] ?? $catSlug }}</a>@if(! $loop->last), @endif
        @endforeach
      </li>
    @endif
    @if(!empty($artikel['niveau']))
      <li><span class="stektips-meta-label">Niveau</span> {{ $artikel['niveau'] }}</li>
    @endif
    @if(!empty($artikel['locatie']))
      <li><span class="stektips-meta-label">Plek</span> {{ $artikel['locatie'] }}</li>
    @endif
    @if(!empty($artikel['seizoenen']))
      <li><span class="stektips-meta-label">Seizoen</span> {{ implode(', ', $artikel['seizoenen']) }}</li>
    @endif
  </ul>

  @if($duurzamer)
    <aside class="soft-card stektips-duurzamer" role="note">
      <h2 class="stektips-related-title">Duurzamer alternatief</h2>
      <p>
        Liever zonder synthetisch stekpoeder?
        <a href="{{ route('stektips.artikel', ['slug' => $duurzamer['slug']]) }}">{{ $duurzamer['titel'] }}</a>
      </p>
    </aside>
  @endif

  @include('partials.stektips-seo-ctas', ['seoCtas' => $seoCtas ?? []])

  <div class="stektips-artikel-body">
    @foreach($artikel['inhoud'] ?? [] as $block)
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

  @if(!empty($kinderen))
    <h2 class="stektips-related-title">In deze pijler</h2>
    <ul class="stektips-related">
      @foreach($kinderen as $kind)
        <li>
          <a href="{{ route('stektips.artikel', ['slug' => $kind['slug']]) }}">{{ $kind['titel'] }}</a>
        </li>
      @endforeach
    </ul>
  @endif

  @if(!empty($artikel['bronnen']))
    <h2 class="stektips-related-title">Bronnen</h2>
    <ul class="stektips-bronnen">
      @foreach($artikel['bronnen'] as $bron)
        @if(!empty($bron['url']))
          <li>
            <a href="{{ $bron['url'] }}" rel="noopener noreferrer" target="_blank">{{ $bron['titel'] ?? $bron['url'] }}</a>
          </li>
        @endif
      @endforeach
    </ul>
  @endif

  @if(!empty($gerelateerdeSoorten))
    <h2 class="stektips-related-title">Gerelateerde soorten</h2>
    <ul class="stektips-related">
      @foreach($gerelateerdeSoorten as $soort)
        <li>
          <a href="{{ route('stektips.show', ['slug' => $soort['slug']]) }}">{{ $soort['naam'] }}</a>
        </li>
      @endforeach
    </ul>
  @endif

  @if(!empty($gerelateerdeArtikelen))
    <h2 class="stektips-related-title">Gerelateerde artikelen</h2>
    <ul class="stektips-related">
      @foreach($gerelateerdeArtikelen as $related)
        <li>
          <a href="{{ route('stektips.artikel', ['slug' => $related['slug']]) }}">{{ $related['titel'] }}</a>
        </li>
      @endforeach
    </ul>
  @endif

  <p class="stektips-actions">
    <a class="btn btn-secondary" href="{{ route('stektips.index') }}">Alle stektips</a>
    <a href="{{ route('hulpmiddelen') }}">Hulpmiddelen</a>
  </p>
</section>
@endsection
