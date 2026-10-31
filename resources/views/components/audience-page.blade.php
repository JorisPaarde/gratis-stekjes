@props([
    'eyebrow' => null,
    'heading',
    'intro',
    'current' => null,
    'series' => [],
])

<section class="section wrap audience-landing">
  @if($eyebrow)
    <p class="audience-eyebrow">{{ $eyebrow }}</p>
  @endif
  <h1 class="page-title">{{ $heading }}</h1>
  <p class="section-intro">{{ $intro }}</p>

  <div class="audience-body prose">
    {{ $slot }}
  </div>

  @isset($cta)
    <div class="audience-cta">
      {{ $cta }}
    </div>
  @endisset

  @if(count($series) > 0)
    <nav class="audience-series" aria-label="Meer doelgroeppagina’s">
      <p class="audience-series-label">Meer over {{ config('site.name') }}</p>
      <ul class="audience-series-list">
        @foreach($series as $item)
          <li>
            @if(($item['slug'] ?? '') === $current)
              <span class="audience-series-current" aria-current="page">{{ $item['label'] }}</span>
            @else
              <a href="{{ route($item['route']) }}">{{ $item['label'] }}</a>
            @endif
          </li>
        @endforeach
      </ul>
    </nav>
  @endif
</section>
