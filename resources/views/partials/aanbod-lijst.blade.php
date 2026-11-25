{{-- Gedeelde aanbodlijst (stad, vereniging, no-JS op /aanbod). Zelfde regels als zoeken.js. --}}
@php
  $items = array_values($items ?? []);
  $groepen = [];
  foreach ($items as $item) {
      $key = (string) ($item['user_id'] ?? $item['id'] ?? '');
      if (! isset($groepen[$key])) {
          $groepen[$key] = [
              'voornaam' => $item['voornaam'] ?? '',
              'locatie_label' => $item['locatie_label'] ?? '',
              'afstand_km' => $item['afstand_km'] ?? null,
              'verenigingen' => $item['verenigingen'] ?? [],
              'items' => [],
          ];
      }
      $groepen[$key]['items'][] = $item;
  }
@endphp
<ul class="zoek-lijst gp-aanbod-lijst">
  @foreach($groepen as $groep)
    <li class="zoek-item zoek-item--aanbod" data-kind="aanbod">
      <div class="zoek-item-kop">
        <h3>{{ $groep['voornaam'] !== '' ? $groep['voornaam'] : 'Iemand' }}@if($groep['locatie_label'] !== '') · {{ $groep['locatie_label'] }}@endif</h3>
        @if($groep['afstand_km'] !== null)
          @php
            $km = (float) $groep['afstand_km'];
            $kmLabel = str_replace('.', ',', $km < 10 ? number_format($km, 1, '.', '') : (string) round($km));
          @endphp
          <span class="zoek-item-afstand">{{ $kmLabel }} km</span>
        @endif
      </div>
      @include('partials.lid-van', ['verenigingen' => $groep['verenigingen']])
      <ul class="zoek-regels">
        @foreach($groep['items'] as $item)
          @include('partials.aanbod-regel', ['item' => $item])
        @endforeach
      </ul>
    </li>
  @endforeach
</ul>
@if(count($items) > 0)
  <script type="application/json" data-aanbod-items>{!! json_encode($items, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP) !!}</script>
@endif
