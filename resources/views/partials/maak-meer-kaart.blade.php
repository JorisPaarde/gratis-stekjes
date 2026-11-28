@if(!empty($maakMeer))
  @php
    $uitgelicht = empty($maakMeer['heeft_aanbod']);
    $naam = $maakMeer['naam'];
    $afbeelding = $maakMeer['afbeelding'] ?? null;
    $heeftFoto = is_array($afbeelding) && ! empty($afbeelding['url']);
    $heeftMethode = ! empty($maakMeer['heeft_methode']);
    $alleenZin = $uitgelicht && ! $heeftMethode && ! $heeftFoto;
  @endphp
  <aside
    class="maak-meer{{ $uitgelicht ? ' maak-meer--uitgelicht' : ' maak-meer--compact' }}{{ $heeftFoto ? ' maak-meer--met-foto' : '' }}{{ $alleenZin ? ' maak-meer--alleen-zin' : '' }}"
    @if($uitgelicht) role="status" @endif
    aria-labelledby="maak-meer-titel"
  >
    @if($heeftFoto)
      <figure class="maak-meer-foto">
        <img
          src="{{ $afbeelding['url'] }}"
          alt="{{ $afbeelding['alt'] }}"
          width="{{ $afbeelding['width'] ?? 640 }}"
          height="{{ $afbeelding['height'] ?? 480 }}"
          loading="lazy"
          decoding="async"
        >
        @if(!empty($afbeelding['auteur']) || !empty($afbeelding['licentie']))
          <figcaption class="maak-meer-credit">
            Foto:
            @if(!empty($afbeelding['bron_url']))
              <a href="{{ $afbeelding['bron_url'] }}" rel="noopener noreferrer">{{ $afbeelding['auteur'] }}</a>
            @else
              {{ $afbeelding['auteur'] }}
            @endif
            @if(!empty($afbeelding['licentie']))
              ·
              @if(!empty($afbeelding['licentie_url']))
                <a href="{{ $afbeelding['licentie_url'] }}" rel="noopener noreferrer license">{{ $afbeelding['licentie'] }}</a>
              @else
                {{ $afbeelding['licentie'] }}
              @endif
            @endif
          </figcaption>
        @endif
      </figure>
    @endif

    <div class="maak-meer-inhoud">
      @if($uitgelicht)
        <h3 id="maak-meer-titel" class="maak-meer-titel">Deze staat er nog niet op</h3>
        @if($heeftMethode)
          <p class="maak-meer-tekst">Zo maak je er meer, en dan kun je die zelf aanbieden.</p>
          <div class="maak-meer-tip">
            <p class="maak-meer-kort">{{ $maakMeer['kort'] }}</p>
            @if(!empty($maakMeer['wanneer']))
              <p class="maak-meer-tijd"><span class="maak-meer-label">Beste tijd</span> {{ $maakMeer['wanneer'] }}</p>
            @endif
          </div>
        @elseif($alleenZin)
          <p class="maak-meer-tekst">Wil je zelf {{ $naam }} <a class="maak-meer-link" href="{{ $maakMeer['aanbied_url'] }}">aanbieden</a>?</p>
        @else
          <p class="maak-meer-tekst">Wil je zelf {{ $naam }} aanbieden?</p>
        @endif
      @else
        @if($heeftMethode)
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

      @if(! $alleenZin)
        <p class="maak-meer-acties">
          <a class="maak-meer-link" href="{{ $maakMeer['aanbied_url'] }}">Zelf aanbieden</a>
          @if(!empty($maakMeer['stektip_url']))
            <a class="maak-meer-link" href="{{ $maakMeer['stektip_url'] }}">Bekijk de tip</a>
          @endif
        </p>
      @endif
    </div>
  </aside>
@endif
