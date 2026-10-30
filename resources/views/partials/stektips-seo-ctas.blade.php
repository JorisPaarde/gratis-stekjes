{{-- Verplichte interne SEO-CTA's voor snoei-/stekpagina's --}}
@if(!empty($seoCtas))
  <aside class="soft-card stektips-seo-ctas" aria-label="Vervolgstappen">
    @if(!empty($seoCtas['stek_url']))
      <h2 class="stektips-related-title">Gooi je snoeisel niet weg</h2>
      <p>
        Zo stek je {{ $seoCtas['plant_label'] ?? 'deze plant' }}:
        <a href="{{ $seoCtas['stek_url'] }}">{{ $seoCtas['stek_label'] ?? 'Naar stekpagina' }}</a>.
        Daarna kun je stekjes <a href="{{ route('aanbod', array_filter(['plant' => $seoCtas['aanbod_plant'] ?? null])) }}">gratis delen</a>.
      </p>
    @endif

    @if(!empty($seoCtas['wilgenwater_url']))
      <h2 class="stektips-related-title">Natuurlijk bewortelen</h2>
      <p>
        Liever zonder synthetisch stekpoeder?
        <a href="{{ $seoCtas['wilgenwater_url'] }}">Wilgenwater maken</a>
        — of lees de pijler
        <a href="{{ route('stektips.artikel', ['slug' => 'natuurlijk-bewortelen']) }}">Natuurlijk bewortelen</a>.
      </p>
    @endif

    @if(!empty($seoCtas['aanbod_url']))
      <h2 class="stektips-related-title">Gratis {{ $seoCtas['plant_label'] ?? '' }}-stekjes in je buurt</h2>
      <p>
        <a class="btn" href="{{ $seoCtas['aanbod_url'] }}">Zoek gratis {{ $seoCtas['plant_label'] ?? 'planten' }}-stekjes</a>
      </p>
    @endif

    @if(!empty($seoCtas['snoei_url']))
      <p class="muted" style="margin-top:.75rem">
        Ook handig:
        <a href="{{ $seoCtas['snoei_url'] }}">{{ $seoCtas['snoei_label'] ?? 'Snoeien' }}</a>.
      </p>
    @endif
  </aside>
@endif
