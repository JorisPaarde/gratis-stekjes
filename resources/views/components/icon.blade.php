@props([
    'name',
    'size' => 24,
    'label' => null,
    'class' => '',
])
@php
    /** @var array<string, string> $paths Lucide-style 24×24, stroke icons (ISC) + eigen planticonen */
    $paths = [
        // —— Lucide (ISC): https://lucide.dev — zie docs/afbeeldingen-licenties.md ——
        'zoeken' => '<circle cx="11" cy="11" r="8"/><path d="m21 21-4.3-4.3"/>',
        'locatie' => '<path d="M20 10c0 4.993-5.539 10.193-7.399 11.799a1 1 0 0 1-1.202 0C9.539 20.193 4 14.993 4 10a8 8 0 0 1 16 0"/><circle cx="12" cy="10" r="3"/>',
        'kaart' => '<path d="M14.106 5.553a2 2 0 0 0-1.813-.1l-6.414 3.2A2 2 0 0 0 4.5 10.4v7.2a2 2 0 0 0 1.106 1.789l6.414 3.2a2 2 0 0 0 1.813.1l6.414-3.2A2 2 0 0 0 21.5 17.6v-7.2a2 2 0 0 0-1.106-1.789z"/><path d="M10.5 6.6v14.8"/><path d="M15.5 3.4v14.8"/>',
        'bericht' => '<path d="M7.9 20A9 9 0 1 0 4 16.1L2 22Z"/>',
        'gebruiker' => '<path d="M19 21v-2a4 4 0 0 0-4-4H9a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/>',
        'hart' => '<path d="M19 14c1.49-1.46 3-3.21 3-5.5A5.5 5.5 0 0 0 16.5 3c-1.76 0-3 .5-4.5 2-1.5-1.5-2.74-2-4.5-2A5.5 5.5 0 0 0 2 8.5c0 2.3 1.5 4.05 3 5.5l7 7Z"/>',
        'delen' => '<circle cx="18" cy="5" r="3"/><circle cx="6" cy="12" r="3"/><circle cx="18" cy="19" r="3"/><path d="m8.59 13.51 6.83 3.98"/><path d="m15.41 6.51-6.82 3.98"/>',
        'kalender' => '<path d="M8 2v4"/><path d="M16 2v4"/><rect width="18" height="18" x="3" y="4" rx="2"/><path d="M3 10h18"/>',
        'info' => '<circle cx="12" cy="12" r="10"/><path d="M12 16v-4"/><path d="M12 8h.01"/>',
        'waarschuwing' => '<path d="m21.73 18-8-14a2 2 0 0 0-3.48 0l-8 14A2 2 0 0 0 4 21h16a2 2 0 0 0 1.73-3"/><path d="M12 9v4"/><path d="M12 17h.01"/>',
        'check' => '<path d="M20 6 9 17l-5-5"/>',
        'sluiten' => '<path d="M18 6 6 18"/><path d="m6 6 12 12"/>',
        'menu' => '<path d="M4 5h16"/><path d="M4 12h16"/><path d="M4 19h16"/>',
        'filter' => '<polygon points="22 3 2 3 10 12.46 10 19 14 21 14 12.46 22 3"/>',
        'plus' => '<path d="M5 12h14"/><path d="M12 5v14"/>',
        'uitnodigen' => '<path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><line x1="19" x2="19" y1="8" y2="14"/><line x1="22" x2="16" y1="11" y2="11"/>',
        'partner' => '<path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M22 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/>',
        'boom' => '<path d="M10 10v.2A3 3 0 0 1 8.9 16H5a3 3 0 0 1-1-5.8V10a3 3 0 0 1 6 0Z"/><path d="M7 16v6"/><path d="M13 19v3"/><path d="M12 19h8.3a1.7 1.7 0 0 0 1.5-2.5L19 12.2a1.7 1.7 0 0 0-1.5-.9h-1.8"/><path d="M15.2 12.2 13 8.4a1.7 1.7 0 0 0-2.9.1L8.2 13"/>',
        // —— Eigen planticonen (zelfde lijnstijl: 24 grid, stroke 2, round) ——
        'stekje' => '<path d="M12 21V9"/><path d="M12 13c-2.5-1.5-4.5-2.2-6-1.5"/><path d="M12 11c2.2-1.8 4.5-2.2 6-1.2"/><path d="M12 8c-1-2.5-.5-4.5 1-6"/><path d="M12 8c1.2-2.2 2.8-3.5 5-4"/><path d="M9 21h6"/>',
        'plant' => '<path d="M12 22v-7"/><path d="M9 22h6"/><path d="M12 15c-3.5-1-6-3.5-6-7 4 0 6 2.5 6 7Z"/><path d="M12 15c3.5-1 6-3.5 6-7-4 0-6 2.5-6 7Z"/><path d="M12 10c-1.5-3-.5-5.5 2-7"/>',
        'zaad' => '<ellipse cx="12" cy="13" rx="4.5" ry="7"/><path d="M12 6v14"/><path d="M9.5 10.5c1 .8 2.5.8 3.5 0"/><path d="M9.5 15c1 .8 2.5.8 3.5 0"/>',
        'ophalen' => '<path d="M8 13v5a2 2 0 0 0 2 2h4a2 2 0 0 0 2-2v-5"/><path d="M7 13h10"/><path d="M12 3v7"/><path d="m9 7 3 3 3-3"/><path d="M5 11c0-1.5 1-2.5 2.5-2.5"/><path d="M19 11c0-1.5-1-2.5-2.5-2.5"/>',
    ];

    $inner = $paths[$name] ?? null;
    $ariaHidden = $label ? 'false' : 'true';
    $role = $label ? 'img' : null;
@endphp
@if($inner)
<svg
  xmlns="http://www.w3.org/2000/svg"
  width="{{ (int) $size }}"
  height="{{ (int) $size }}"
  viewBox="0 0 24 24"
  fill="none"
  stroke="currentColor"
  stroke-width="2"
  stroke-linecap="round"
  stroke-linejoin="round"
  class="icon icon-{{ $name }} {{ $class }}"
  @if($label) role="img" aria-label="{{ $label }}" @else aria-hidden="true" @endif
  focusable="false"
>{!! $inner !!}</svg>
@endif
