@if(!empty($maakMeer))
  @php
    $uitgelicht = empty($maakMeer['heeft_aanbod']);
    $naam = $maakMeer['naam'];
  @endphp
  <aside
    class="maak-meer{{ $uitgelicht ? ' maak-meer--uitgelicht' : ' maak-meer--compact' }}"
    @if($uitgelicht) role="status" @endif
    aria-labelledby="maak-meer-titel"
  >
    @if($uitgelicht)
      <h3 id="maak-meer-titel" class="maak-meer-titel">Deze staat er nog niet op</h3>
      @if(!empty($maakMeer['heeft_methode']))
        <p class="maak-meer-tekst">Zo maak je er meer, en dan kun je die zelf aanbieden.</p>
        <div class="maak-meer-tip">
          <p class="maak-meer-kort">{{ $maakMeer['kort'] }}</p>
          @if(!empty($maakMeer['wanneer']))
            <p class="maak-meer-tijd"><span class="maak-meer-label">Beste tijd</span> {{ $maakMeer['wanneer'] }}</p>
          @endif
        </div>
      @else
        <p class="maak-meer-tekst">Wil je zelf {{ $naam }} aanbieden?</p>
      @endif
    @else
      @if(!empty($maakMeer['heeft_methode']))
        <h3 id="maak-meer-titel" class="maak-meer-titel">Zo maak je zelf meer {{ $naam }}</h3>
        <div class="maak-meer-tip">
          <p class="maak-meer-kort">{{ $maakMeer['kort'] }}</p>
          @if(!empty($maakMeer['wanneer']))
            <p class="maak-meer-tijd"><span class="maak-meer-label">Beste tijd</span> {{ $maakMeer['wanneer'] }}</p>
          @endif
        </div>
      @else
        <h3 id="maak-meer-titel" class="maak-meer-titel">Zelf ook {{ $naam }} over?</h3>
        <p class="maak-meer-tekst">Je kunt die hier zelf aanbieden.</p>
      @endif
    @endif

    <div class="maak-meer-acties">
      <a class="btn" href="{{ $maakMeer['aanbied_url'] }}">Bied {{ $naam }} aan</a>
      @if(!empty($maakMeer['stektip_url']))
        <a class="maak-meer-link" href="{{ $maakMeer['stektip_url'] }}">Bekijk de tip voor {{ $naam }}</a>
      @endif
    </div>
  </aside>
@endif
