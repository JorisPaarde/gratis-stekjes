@php
  /** @var \App\Support\ActivatieHerinneringStats $stats */
  $laatste = $stats->laatste();
@endphp
<span class="beheer-activatie-herinnering">
  <span class="beheer-activatie-herinnering__regel">{{ $stats->tellingLabel() }}</span>
  @if($laatste)
    <br><span class="muted beheer-klein">laatste: {{ $stats->formatLaatste($laatste) }}</span>
  @endif
  @if($stats->mislukt > 0)
    <span class="status status--fout">{{ $stats->mislukt === 1 ? '1 mislukt' : $stats->mislukt.' mislukt' }}</span>
  @endif
</span>
