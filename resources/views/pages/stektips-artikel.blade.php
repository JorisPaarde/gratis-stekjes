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

  <header @class([
    'kb-hero',
    'kb-hero--met-foto' => !empty($artikel['foto']),
    'kb-hero--met-illu' => empty($artikel['foto']) && !empty($artikel['illustratie']),
  ])>
    <div>
      <h1 class="page-title">{{ $heading ?? $artikel['titel'] }}</h1>
      @if(!empty($toonPlantzoeker))
        <form class="stektips-zoek" method="get" action="{{ route('stektips.index') }}" role="search">
          @include('partials.plant-zoeker', ['zoekId' => 'vermeerderen-q', 'zoekValue' => ''])
        </form>
        <p class="stektips-tag-filters" aria-label="Filter op type">
          <a class="badge badge--voedselbos" href="{{ route('stektips.index', ['tag' => 'voedselbos']) }}">Voedselbos</a>
          <a class="badge badge--inheems" href="{{ route('stektips.index', ['tag' => 'inheems']) }}">Inheems</a>
        </p>
      @endif
      @if(!empty($artikel['samenvatting']))
        <p class="section-intro">{{ $artikel['samenvatting'] }}</p>
      @endif
    </div>
    @if(!empty($artikel['foto']))
      <div class="kb-hero-foto">
        <x-artikel-picture :id="$artikel['foto']" :eager="true" sizes="(max-width: 759px) 100vw, 44vw" />
      </div>
    @elseif(!empty($artikel['illustratie']))
      <div class="kb-hero-illu">@include('partials.illustraties.stek', ['naam' => $artikel['illustratie']])</div>
    @endif
  </header>

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


  @php
    $kopjes = collect($blokken)->filter(fn ($b) => in_array($b['type'] ?? '', ['h2', 'videos'], true) && (($b['tekst'] ?? $b['titel'] ?? '') !== ''))
      ->map(fn ($b) => (string) ($b['tekst'] ?? $b['titel']))->values();
  @endphp
  @if($kopjes->count() >= 3)
    <nav class="kb-inhoud" aria-label="Op deze pagina">
      <span class="kb-inhoud-label">Op deze pagina</span>
      @foreach($kopjes as $kop)
        <a href="#{{ \Illuminate\Support\Str::slug($kop) }}">{{ $kop }}</a>
      @endforeach
    </nav>
  @endif

  <div class="stektips-artikel-body">
    @foreach($blokken as $block)
      @include('partials.kennisbank-blok', ['block' => $block])
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
    <h2 class="stektips-related-title" id="meer-over-dit-onderwerp">Meer over dit onderwerp</h2>
    <ul class="kb-kinderen">
      @foreach($kinderen as $kind)
        <li>
          <a href="{{ route('stektips.artikel', ['slug' => $kind['slug']]) }}">
            @if(!empty($kind['foto_id']))
              <x-artikel-picture :id="$kind['foto_id']" class="kb-kind-foto" sizes="(max-width: 640px) 100vw, 360px" />
            @endif
            <span class="kb-kind-tekst">
              <strong>{{ $kind['titel'] }}</strong>
              @if(!empty($kind['samenvatting']))<span>{{ \Illuminate\Support\Str::limit($kind['samenvatting'], 110) }}</span>@endif
            </span>
          </a>
        </li>
      @endforeach
    </ul>
  @endif

  @php
    $fotoMakers = \App\Support\ArtikelFotos::makersVoorArtikel(
        (string) ($artikel['slug'] ?? ''),
        array_values(array_filter(array_column($kinderen ?? [], 'foto_id'))),
    );
  @endphp
  @if($fotoMakers !== [])
    <p class="kb-foto-credits">Foto’s: {{ implode(', ', $fotoMakers) }} / Unsplash</p>
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

  <aside class="kb-delen">
    @include('partials.illustraties.stek', ['naam' => 'delen'])
    <div>
      <p class="kb-delen-titel">Te veel stekjes of snoeisel?</p>
      <p>Geef het gratis weg aan iemand in de buurt. Zo krijgt elk stekje een tweede leven.</p>
      <a class="btn" href="{{ auth()->check() ? route('aanbieden') : route('account.registreren') }}">Bied het gratis aan</a>
    </div>
  </aside>

  <p class="stektips-actions">
    <a class="btn btn-secondary" href="{{ route('stektips.index') }}">Alle stektips</a>
    <a href="{{ route('hulpmiddelen') }}">Hulpmiddelen</a>
  </p>
</section>
@endsection

@push('scripts')
<script>
  document.querySelectorAll('[data-video-id]').forEach(function (frame) {
    var start = frame.querySelector('.kb-video-start');
    if (!start) return;
    start.addEventListener('click', function (e) {
      e.preventDefault();
      var id = frame.getAttribute('data-video-id');
      var iframe = document.createElement('iframe');
      iframe.src = 'https://www.youtube-nocookie.com/embed/' + encodeURIComponent(id) + '?autoplay=1&rel=0&hl=nl&cc_lang_pref=nl';
      iframe.title = frame.getAttribute('data-video-titel') || 'Video';
      iframe.allow = 'autoplay; encrypted-media; picture-in-picture; fullscreen';
      iframe.allowFullscreen = true;
      iframe.loading = 'lazy';
      iframe.referrerPolicy = 'strict-origin-when-cross-origin';
      frame.innerHTML = '';
      frame.appendChild(iframe);
      frame.classList.add('is-geladen');
    });
  });
</script>
@endpush
