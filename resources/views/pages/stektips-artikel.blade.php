@extends('layouts.site')

@section('title', $title)
@section('meta_description', $metaDescription)
@section('canonical', $canonical)
@section('og_image', '/assets/og/stektips.jpg')

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

  <h1 class="page-title">{{ $heading ?? $artikel['titel'] }}</h1>
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
      <li><span class="stektips-meta-label">Geschikt voor</span> {{ ['beginner' => 'beginners', 'gevorderd' => 'gevorderden'][$artikel['niveau']] ?? $artikel['niveau'] }}</li>
    @endif
    @if(!empty($artikel['locatie']))
      <li><span class="stektips-meta-label">Plek</span> {{ ['binnen' => 'Binnen', 'buiten' => 'Buiten', 'beide' => 'Binnen en buiten'][$artikel['locatie']] ?? $artikel['locatie'] }}</li>
    @endif
    @if(!empty($artikel['seizoenen']))
      @php $seizoenen = array_values($artikel['seizoenen']); @endphp
      <li><span class="stektips-meta-label">Beste periode</span> {{ count($seizoenen) >= 4 ? 'het hele jaar' : (count($seizoenen) > 1 ? implode(', ', array_slice($seizoenen, 0, -1)).' en '.end($seizoenen) : $seizoenen[0]) }}</li>
    @endif
  </ul>


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

  @if($duurzamer)
    <aside class="soft-card stektips-duurzamer" role="note">
      <h2 class="stektips-related-title">Duurzamer alternatief</h2>
      <p>
        Liever geen kunstmatig stekpoeder gebruiken?
        <a href="{{ route('stektips.artikel', ['slug' => $duurzamer['slug']]) }}">{{ $duurzamer['titel'] }}</a>
      </p>
    </aside>
  @endif

  @include('partials.stektips-seo-ctas', ['seoCtas' => $seoCtas ?? []])

  @if(!empty($kinderen))
    <h2 class="stektips-related-title">Meer over dit onderwerp</h2>
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
