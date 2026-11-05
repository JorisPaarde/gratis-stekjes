{{-- Foto van een uitgelicht initiatief: 4:3, webp 400/800. --}}
@php $b = '/assets/images/initiatieven/'.$afbeelding['bestand']; @endphp
<figure class="init-foto">
  <img src="{{ $b }}-800.webp" srcset="{{ $b }}-400.webp 400w, {{ $b }}-800.webp 800w" sizes="(min-width: 760px) 45vw, 92vw" width="800" height="600" loading="lazy" decoding="async" alt="{{ $afbeelding['alt'] }}">
  @if($afbeelding['credit'] !== '')<figcaption>{{ $afbeelding['credit'] }}</figcaption>@endif
</figure>
