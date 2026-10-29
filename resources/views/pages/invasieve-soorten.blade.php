@extends('layouts.site')

@section('title', $title)
@section('meta_description', $metaDescription)
@section('canonical', $canonical)

@section('content')
<section class="section wrap prose">
  <h1 class="page-title"><x-icon name="waarschuwing" :size="28" /> Invasieve soorten</h1>
  <p class="section-intro">Op {{ config('site.name') }} mag je geen planten aanbieden die op de EU-Unielijst van invasieve uitheemse soorten staan. Die soorten mogen in de EU niet worden verhandeld of verspreid — ook niet gratis als stekje.</p>

  <div class="soft-card warn-card">
    <p><x-icon name="waarschuwing" :size="20" /> Waarom? Invasieve exoten verdringen inheemse planten en dieren, verstoren ecosystemen en kosten veel om te bestrijden. De Unielijst beschermt de natuur in heel Europa.</p>
  </div>

  <h2>Wat kun je wel doen?</h2>
  <ul>
    <li>Deel inheemse of niet-invasieve tuin- en kamerplanten, bessenstruiken en kruiden.</li>
    <li>Twijfel je over een soort? Bied hem niet aan en check de NVWA of onze lijst hieronder.</li>
    <li>Heb je een Unielijst-plant in de tuin? Verwijder die veilig volgens lokale adviezen — geef hem niet door.</li>
  </ul>

  <h2>Lijst ({{ $aantal }} soorten)</h2>
  <p class="muted">Nederlandse namen volgens de NVWA (terrestrische planten en water-/oeverplanten). Wetenschappelijke namen en synoniemen uit de EU-Unielijst.</p>

  <div class="stack" style="margin-top:1rem">
    @foreach($soorten as $soort)
      <article class="soft-card">
        <h3>{{ $soort['nederlandse_naam'] }}</h3>
        <p><em>{{ $soort['wetenschappelijke_naam'] }}</em></p>
        @if(!empty($soort['synoniemen']))
          <p class="muted">Ook bekend als: {{ implode(', ', $soort['synoniemen']) }}</p>
        @endif
        <p class="muted">
          <a href="{{ $soort['bron_url'] ?? 'https://www.nvwa.nl/onderwerpen/plant/invasieve-exoten/unielijst-invasieve-exoten' }}" rel="noopener noreferrer">Bron (NVWA)</a>
          @if(!empty($soort['geraadpleegd']))
            · geraadpleegd {{ $soort['geraadpleegd'] }}
          @endif
        </p>
      </article>
    @endforeach
  </div>

  <h2>Bronnen</h2>
  <ul>
    @forelse(($meta['bronnen'] ?? []) as $bron)
      <li><a href="{{ $bron['url'] }}" rel="noopener noreferrer">{{ $bron['naam'] }}</a></li>
    @empty
      <li><a href="https://www.nvwa.nl/onderwerpen/plant/invasieve-exoten/unielijst-invasieve-exoten" rel="noopener noreferrer">NVWA — Unielijst invasieve exoten</a></li>
      <li><a href="https://eur-lex.europa.eu/legal-content/NL/TXT/?uri=CELEX:02016R1141-20250807" rel="noopener noreferrer">EUR-Lex — Uitvoeringsverordening (EU) 2016/1141</a></li>
    @endforelse
  </ul>

  @if(!empty($meta['niet_opgenomen']))
    <h2>Niet opgenomen</h2>
    <ul class="muted">
      @foreach($meta['niet_opgenomen'] as $punt)
        <li>{{ $punt }}</li>
      @endforeach
    </ul>
  @endif

  <p style="margin-top:1.5rem">
    <a class="btn" href="{{ route('account.aanbod') }}">Naar mijn aanbod</a>
    <a class="btn btn-secondary" href="{{ route('hoe-werkt-het') }}">Hoe werkt het</a>
  </p>
</section>
@endsection
