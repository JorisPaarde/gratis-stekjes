@props([
    'id',
    'alt' => '',
    'width' => 960,
    'height' => 640,
    'eager' => false,
    'sizes' => '(max-width: 640px) 100vw, (max-width: 1024px) 90vw, 960px',
    'class' => '',
    'showCredit' => true,
])

@php
    $credits = $credits ?? null;
    if ($credits === null) {
        static $allCredits = null;
        if ($allCredits === null) {
            $path = resource_path('data/foto-credits.json');
            $raw = is_readable($path) ? json_decode((string) file_get_contents($path), true) : null;
            $allCredits = [];
            foreach (($raw['fotos'] ?? []) as $row) {
                if (! empty($row['id'])) {
                    $allCredits[$row['id']] = $row;
                }
            }
        }
        $credits = $allCredits[$id] ?? null;
    }
    $altText = $alt !== '' ? $alt : (string) ($credits['alt'] ?? '');
    $w = (int) ($credits['width'] ?? $width);
    $h = (int) ($credits['height'] ?? $height);
    $base = 'assets/images/home-v2/'.$id;
    // Hero landscape: include 1920w for sharp 2x on common laptop widths.
    $widths = $id === 'hero' ? [640, 960, 1280, 1920] : [640, 960, 1280];
    $webpSrcset = collect($widths)
        ->map(fn (int $size) => asset($base.'-'.$size.'.webp').' '.$size.'w')
        ->implode(', ');
    $portraitWidths = [720, 1080, 1440];
    $hasPortrait = $id === 'hero'
        && is_readable(public_path('assets/images/home-v2/hero-portrait-720.webp'));
    $portraitSrcset = $hasPortrait
        ? collect($portraitWidths)
            ->map(fn (int $size) => asset('assets/images/home-v2/hero-portrait-'.$size.'.webp').' '.$size.'w')
            ->implode(', ')
        : '';
    $jpgFallback = $id === 'hero' && is_readable(public_path($base.'-1920.jpg'))
        ? asset($base.'-1920.jpg')
        : asset($base.'-960.jpg');
    $loading = $eager ? 'eager' : 'lazy';
    $fetchpriority = $eager ? 'high' : null;
    $showFigcaption = $showCredit && ! empty($credits['attribution_required']);
@endphp

<figure {{ $attributes->class(['home-pic', $class]) }}>
  <picture>
    @if($hasPortrait)
      <source
        media="(max-width: 767px)"
        type="image/webp"
        srcset="{{ $portraitSrcset }}"
        sizes="100vw"
      >
    @endif
    <source type="image/webp" srcset="{{ $webpSrcset }}" sizes="{{ $sizes }}">
    <img
      src="{{ $jpgFallback }}"
      alt="{{ $altText }}"
      width="{{ $w }}"
      height="{{ $h }}"
      decoding="async"
      loading="{{ $loading }}"
      @if($fetchpriority) fetchpriority="{{ $fetchpriority }}" @endif
    >
  </picture>
  @if($showFigcaption)
    <figcaption class="home-pic-credit">
      Foto:
      @if(!empty($credits['bron_url']))
        <a href="{{ $credits['bron_url'] }}" rel="noopener noreferrer">{{ $credits['maker'] }}</a>
      @else
        {{ $credits['maker'] }}
      @endif
      ·
      @if(!empty($credits['licentie_url']))
        <a href="{{ $credits['licentie_url'] }}" rel="noopener noreferrer license">{{ $credits['licentie'] }}</a>
      @else
        {{ $credits['licentie'] }}
      @endif
      · via {{ $credits['bron'] }}
    </figcaption>
  @endif
</figure>
