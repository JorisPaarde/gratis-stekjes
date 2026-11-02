{{-- Eigen lijn-illustraties voor stek- en snoeiartikelen (CC0). Gebruik: @include('partials.illustraties.stek', ['naam' => 'kopstek']) --}}
@php
  $leaf = '#4c8a3c'; $dark = '#1f4d2b'; $soil = '#8a5a3b'; $water = '#9fd0e6'; $oranje = '#e8833a';
@endphp
<svg class="illu illu--{{ $naam }}" viewBox="0 0 120 120" width="120" height="120" role="img" aria-hidden="true" focusable="false">
  <circle cx="60" cy="60" r="56" fill="#e6f2e1"/>
  @switch($naam)
    @case('kopstek')
      {{-- jonge scheut met schuine snede onder een knoop --}}
      <path d="M60 98 L60 34" stroke="{{ $dark }}" stroke-width="4" stroke-linecap="round"/>
      <path d="M60 46 C46 40 40 28 44 20 C54 24 60 34 60 46Z" fill="{{ $leaf }}"/>
      <path d="M60 46 C74 40 80 28 76 20 C66 24 60 34 60 46Z" fill="{{ $leaf }}"/>
      <path d="M60 64 C48 62 40 54 40 46 C50 48 58 54 60 64Z" fill="{{ $leaf }}" opacity=".85"/>
      <circle cx="60" cy="80" r="3.5" fill="{{ $dark }}"/>
      <path d="M50 90 L70 84" stroke="{{ $oranje }}" stroke-width="3" stroke-dasharray="4 3" stroke-linecap="round"/>
      @break
    @case('bladstek')
      {{-- vetplantblad met wortels en mini-rozet --}}
      <path d="M60 86 L60 104" stroke="{{ $soil }}" stroke-width="3"/>
      <rect x="20" y="86" width="80" height="10" rx="5" fill="{{ $soil }}" opacity=".35"/>
      <ellipse cx="50" cy="66" rx="12" ry="22" transform="rotate(-35 50 66)" fill="{{ $leaf }}"/>
      <path d="M62 82 C70 76 76 76 80 80 C76 84 70 86 62 82Z" fill="{{ $leaf }}" opacity=".9"/>
      <path d="M62 82 C64 74 68 70 72 70" stroke="{{ $leaf }}" stroke-width="3" fill="none" stroke-linecap="round"/>
      <path d="M60 86 C56 92 54 96 50 100 M62 86 C64 93 68 96 72 99 M61 86 L61 102" stroke="{{ $dark }}" stroke-width="1.6" fill="none" stroke-linecap="round"/>
      @break
    @case('halfrijp')
      {{-- tak: onderkant bruin (verhout), top groen --}}
      <path d="M60 100 L60 70" stroke="{{ $soil }}" stroke-width="5" stroke-linecap="round"/>
      <path d="M60 70 L60 30" stroke="{{ $leaf }}" stroke-width="4" stroke-linecap="round"/>
      <path d="M60 40 C48 36 42 26 46 18 C56 22 60 30 60 40Z" fill="{{ $leaf }}"/>
      <path d="M60 52 C72 48 80 40 80 30 C70 32 62 40 60 52Z" fill="{{ $leaf }}"/>
      <path d="M60 64 C48 62 42 54 42 46 C52 48 58 54 60 64Z" fill="{{ $leaf }}" opacity=".85"/>
      <circle cx="60" cy="86" r="3" fill="{{ $dark }}"/>
      @break
    @case('winterhout')
      {{-- kale twijgen in de grond, knoppen zichtbaar --}}
      <rect x="18" y="80" width="84" height="22" rx="8" fill="{{ $soil }}" opacity=".45"/>
      @foreach([40, 60, 80] as $x)
        <path d="M{{ $x }} 96 L{{ $x }} 34" stroke="{{ $soil }}" stroke-width="5" stroke-linecap="round"/>
        <circle cx="{{ $x + 3 }}" cy="46" r="2.6" fill="{{ $dark }}"/>
        <circle cx="{{ $x - 3 }}" cy="60" r="2.6" fill="{{ $dark }}"/>
        <circle cx="{{ $x + 3 }}" cy="72" r="2.6" fill="{{ $dark }}"/>
      @endforeach
      <path d="M30 24 l4 4 m0 -4 l-4 4 M88 20 l4 4 m0 -4 l-4 4" stroke="#7fb7d8" stroke-width="2" stroke-linecap="round"/>
      @break
    @case('afleggen')
      {{-- buigende tak die in de grond wortelt --}}
      <rect x="14" y="78" width="92" height="24" rx="8" fill="{{ $soil }}" opacity=".45"/>
      <path d="M26 80 L26 40" stroke="{{ $soil }}" stroke-width="6" stroke-linecap="round"/>
      <path d="M26 46 C50 40 70 60 74 84 C76 70 84 56 92 44" stroke="{{ $leaf }}" stroke-width="4" fill="none" stroke-linecap="round"/>
      <path d="M92 44 C84 36 86 28 94 24 C98 32 98 38 92 44Z" fill="{{ $leaf }}"/>
      <path d="M74 84 L70 96 M74 84 L78 96 M74 84 L74 98" stroke="{{ $dark }}" stroke-width="1.8" stroke-linecap="round"/>
      <path d="M58 70 l8 0" stroke="{{ $oranje }}" stroke-width="3" stroke-linecap="round"/>
      @break
    @case('water')
      {{-- glas water met stek en wortels --}}
      <path d="M40 46 L44 100 L76 100 L80 46Z" fill="{{ $water }}" opacity=".55" stroke="{{ $dark }}" stroke-width="2.5" stroke-linejoin="round"/>
      <path d="M41 58 L79 58" stroke="#ffffff" stroke-width="2" opacity=".8"/>
      <path d="M60 92 L60 22" stroke="{{ $leaf }}" stroke-width="4" stroke-linecap="round"/>
      <path d="M60 30 C48 26 42 16 46 10 C56 14 60 22 60 30Z" fill="{{ $leaf }}"/>
      <path d="M60 38 C72 34 78 24 74 18 C64 22 60 30 60 38Z" fill="{{ $leaf }}"/>
      <path d="M60 86 C56 90 52 92 50 96 M60 86 C64 90 68 92 70 96 M60 88 L60 98" stroke="#f3efe6" stroke-width="2" stroke-linecap="round" fill="none"/>
      @break
    @case('grond')
      {{-- potje met stekgrond en stek --}}
      <path d="M36 62 L42 102 L78 102 L84 62Z" fill="{{ $oranje }}" opacity=".85"/>
      <rect x="32" y="56" width="56" height="10" rx="3" fill="{{ $oranje }}"/>
      <ellipse cx="60" cy="62" rx="24" ry="4" fill="{{ $soil }}"/>
      <path d="M60 62 L60 24" stroke="{{ $leaf }}" stroke-width="4" stroke-linecap="round"/>
      <path d="M60 32 C48 28 42 18 46 12 C56 16 60 24 60 32Z" fill="{{ $leaf }}"/>
      <path d="M60 42 C72 38 78 28 74 22 C64 26 60 34 60 42Z" fill="{{ $leaf }}"/>
      @break
    @case('snoeisnede')
      {{-- schuine snede net boven een knop --}}
      <path d="M50 104 L50 40" stroke="{{ $soil }}" stroke-width="7" stroke-linecap="round"/>
      <path d="M50 58 C60 52 66 46 70 38" stroke="{{ $soil }}" stroke-width="4" fill="none" stroke-linecap="round"/>
      <circle cx="56" cy="64" r="4" fill="{{ $leaf }}"/>
      <path d="M40 54 L64 44" stroke="{{ $oranje }}" stroke-width="3" stroke-dasharray="5 3" stroke-linecap="round"/>
      <path d="M74 30 l14 -10 m-14 0 l14 10" stroke="{{ $dark }}" stroke-width="3" stroke-linecap="round"/>
      <circle cx="74" cy="25" r="4" fill="none" stroke="{{ $dark }}" stroke-width="2.5"/>
      @break
    @case('nieuw-hout')
      {{-- struik met bloemen aan jonge (groene) scheuten --}}
      <path d="M60 104 L60 70 M60 80 L44 62 M60 80 L76 60" stroke="{{ $soil }}" stroke-width="5" stroke-linecap="round"/>
      <path d="M44 62 L38 30 M76 60 L84 28 M60 70 L60 26" stroke="{{ $leaf }}" stroke-width="3.5" stroke-linecap="round"/>
      @foreach([[38,26],[84,24],[60,22]] as [$x,$y])
        <ellipse cx="{{ $x }}" cy="{{ $y }}" rx="7" ry="10" fill="#a774c9"/>
      @endforeach
      @break
    @case('oud-hout')
      {{-- struik met bloemknoppen op de oude takken --}}
      <path d="M60 104 L60 40 M60 76 L40 50 M60 70 L82 46" stroke="{{ $soil }}" stroke-width="5" stroke-linecap="round"/>
      @foreach([[40,46],[82,42],[60,36]] as [$x,$y])
        <circle cx="{{ $x }}" cy="{{ $y }}" r="10" fill="#7fa8e0"/>
        <circle cx="{{ $x }}" cy="{{ $y }}" r="4" fill="#ffffff" opacity=".6"/>
      @endforeach
      @break
    @case('delen')
      {{-- twee handen geven een potje door --}}
      <path d="M48 68 L52 92 L68 92 L72 68Z" fill="{{ $oranje }}"/>
      <path d="M60 68 L60 40" stroke="{{ $leaf }}" stroke-width="3.5" stroke-linecap="round"/>
      <path d="M60 46 C50 42 46 34 48 28 C56 32 60 38 60 46Z" fill="{{ $leaf }}"/>
      <path d="M60 52 C70 48 74 40 72 34 C64 38 60 44 60 52Z" fill="{{ $leaf }}"/>
      <path d="M22 90 C30 86 40 88 48 84 M98 90 C90 86 80 88 72 84" stroke="{{ $dark }}" stroke-width="4" fill="none" stroke-linecap="round"/>
      @break
    @default
      <path d="M60 96 L60 40" stroke="{{ $leaf }}" stroke-width="4" stroke-linecap="round"/>
      <path d="M60 50 C48 46 42 36 46 28 C56 32 60 40 60 50Z" fill="{{ $leaf }}"/>
  @endswitch
</svg>
