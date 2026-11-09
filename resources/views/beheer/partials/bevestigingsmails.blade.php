@php
  /** @var \App\Support\BevestigingsmailStats $stats */
  $laatste = $stats->laatste();
@endphp
<span class="beheer-bevestigingsmails">
  <span class="beheer-bevestigingsmails__regel">{{ $stats->tellingLabel() }}</span>
  @if($laatste)
    <br><span class="muted beheer-klein">laatste: {{ $stats->formatLaatste($laatste) }}</span>
  @endif
  @if($stats->mislukt > 0)
    <span class="status status--fout">{{ $stats->mislukt === 1 ? '1 mislukt' : $stats->mislukt.' mislukt' }}</span>
  @endif
</span>
