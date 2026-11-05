{{-- Foto van een uitgelicht initiatief: 4:3, webp 400/800 met jpg-fallback. --}}
@php
  $b = '/assets/images/initiatieven/'.$afbeelding['bestand'];
  $jpg800 = public_path('assets/images/initiatieven/'.$afbeelding['bestand'].'-800.jpg');
  $heeftJpg = is_file($jpg800);
@endphp
<figure class="init-foto">
  @if($heeftJpg)
    <picture>
      <source type="image/webp" srcset="{{ $b }}-400.webp 400w, {{ $b }}-800.webp 800w" sizes="(min-width: 760px) 45vw, 92vw">
      <img src="{{ $b }}-800.jpg" srcset="{{ $b }}-400.jpg 400w, {{ $b }}-800.jpg 800w" sizes="(min-width: 760px) 45vw, 92vw" width="800" height="600" loading="lazy" decoding="async" alt="{{ $afbeelding['alt'] }}">
    </picture>
  @else
    <img src="{{ $b }}-800.webp" srcset="{{ $b }}-400.webp 400w, {{ $b }}-800.webp 800w" sizes="(min-width: 760px) 45vw, 92vw" width="800" height="600" loading="lazy" decoding="async" alt="{{ $afbeelding['alt'] }}">
  @endif
  @if($afbeelding['credit'] !== '')<figcaption>{{ $afbeelding['credit'] }}</figcaption>@endif
</figure>
