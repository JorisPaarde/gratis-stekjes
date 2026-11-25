@php
  $fotos = $item['fotos'] ?? [];
  $foto = $fotos[0]['thumb_url'] ?? $fotos[0]['url'] ?? null;
  $labels = ['uitdelen' => 'Te geef', 'ophalen' => 'Gezocht'];
  $label = $labels[$item['type'] ?? ''] ?? ($item['type'] ?? '');
  $aantal = (int) ($item['aantal'] ?? 1);
  $tekst = (string) ($item['tekst'] ?? '');
  $aria = trim($label.' '.$tekst.($aantal > 1 ? ', '.$aantal.' stuks' : '').': bekijk foto en info');
@endphp
<li class="zoek-regel">
  <button type="button" class="zoek-regel-open" data-aanbod-open="{{ $item['id'] }}" aria-haspopup="dialog" aria-label="{{ $aria }}">
    @if($foto)
      <img src="{{ $foto }}" alt="" width="56" height="56" loading="lazy">
    @endif
    <span class="zoek-regel-tekst">
      <span class="badge">{{ $label }}</span>
      {{ $tekst }}
      @if($aantal > 1)
        <span class="aanbod-aantal">({{ $aantal }} stuks)</span>
      @endif
      <span class="zoek-regel-meer" aria-hidden="true">{{ $foto ? 'Foto en info' : 'Meer info' }}</span>
    </span>
  </button>
  <a class="zoek-regel-bericht" href="{{ route('account.bericht-starten', ['aanbod_id' => $item['id'], 'user_id' => $item['user_id']]) }}">Stuur bericht</a>
</li>
