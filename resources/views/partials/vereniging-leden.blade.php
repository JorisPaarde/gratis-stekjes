{{-- Aanbod van leden die hun lidmaatschap tonen; alleen aantal leden, geen namen van anderen. --}}
<section class="vereniging-leden" aria-labelledby="leden-titel">
  <h2 id="leden-titel">Gedeeld door leden</h2>
  <p class="muted">
    {{ $blok['leden'] }} {{ $blok['leden'] === 1 ? 'lid is' : 'leden zijn' }} op Gratis planten aan {{ $naam }} gekoppeld.
  </p>
  @if(count($blok['aanbod']) === 0)
    <p>Nog geen aanbod van leden. Ben je lid? <a href="{{ route('account.verenigingen') }}">Koppel je account</a> en zet iets online.</p>
  @else
    <ul class="zoek-lijst gp-aanbod-lijst">
      @foreach($blok['aanbod'] as $item)
        <li class="zoek-item">
          <h3>{{ $item['voornaam'] ?: 'Iemand' }} · {{ $item['type'] === 'ophalen' ? 'zoekt' : 'deelt' }}</h3>
          <p>{{ $item['tekst'] }}</p>
          <p class="muted">{{ $item['locatie_label'] }}</p>
          @auth
            <p><a href="{{ route('account.bericht-starten', ['aanbod_id' => $item['id'], 'user_id' => $item['user_id']]) }}">Bericht sturen</a></p>
          @else
            <p class="muted"><a href="{{ route('account.inloggen') }}">Log in</a> om een bericht te sturen.</p>
          @endauth
        </li>
      @endforeach
    </ul>
  @endif
  <p class="vereniging-acties">
    <a class="btn" href="{{ route('account.verenigingen') }}">Ik ben lid: koppel mijn account</a>
    <a class="btn btn-secondary" href="{{ route('organisaties.poster', ['slug' => $slug]) }}">Poster met QR-code</a>
  </p>
</section>
