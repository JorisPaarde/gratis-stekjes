@php
  /** @var \App\Support\BevestigingsmailStats $stats */
  $compact = $compact ?? false;
  $metHint = $metHint ?? false;
  $laatste = $stats->laatsteOk ?? $stats->laatstePoging;
@endphp
@if($compact)
  <span class="beheer-bevestigingsmails">
    <span class="beheer-bevestigingsmails__label">Bevestigingsmails:</span>
    <strong>{{ $stats->tellingLabel() }}</strong>
    @if($laatste)
      <span class="muted beheer-klein">, laatste {{ $stats->formatLaatste($laatste) }}</span>
    @endif
    @if($stats->mislukt > 0)
      <span class="status status--fout">{{ $stats->mislukt === 1 ? '1 mislukt' : $stats->mislukt.' mislukt' }}</span>
    @endif
  </span>
@else
  <div class="beheer-bevestigingsmails beheer-bevestigingsmails--blok">
    <p class="beheer-bevestigingsmails__samenvatting">
      <span class="beheer-bevestigingsmails__label">Bevestigingsmails:</span>
      <strong>{{ $stats->tellingLabel() }}</strong>
      @if($laatste)
        <span class="muted">· laatste {{ $stats->formatLaatste($laatste) }}</span>
      @else
        <span class="muted">· nog geen bevestigingsmail gelogd</span>
      @endif
      @if($stats->mislukt > 0)
        <span class="status status--fout">{{ $stats->mislukt === 1 ? '1 mislukt' : $stats->mislukt.' mislukt' }}</span>
      @endif
    </p>
    @if($metHint && $stats->laatsteVerzendHint())
      <p class="muted beheer-klein beheer-bevestigingsmails__hint">{{ $stats->laatsteVerzendHint() }}</p>
    @endif
  </div>
@endif
