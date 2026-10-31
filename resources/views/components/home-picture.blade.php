@props([
    'id',
    'alt' => '',
    'width' => 960,
    'height' => 640,
    'eager' => false,
    'sizes' => '(max-width: 640px) 100vw, (max-width: 1024px) 90vw, 960px',
    'class' => '',
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
    // Display box aspect from source; width/height attrs prevent CLS.
    $base = 'assets/images/home-v2/'.$id;
    $webpSrcset = collect([640, 960, 1280])
        ->map(fn (int $size) => asset($base.'-'.$size.'.webp').' '.$size.'w')
        ->implode(', ');
    $jpg = asset($base.'-960.jpg');
    $loading = $eager ? 'eager' : 'lazy';
    $fetchpriority = $eager ? 'high' : null;
@endphp

<figure {{ $attributes->class(['home-pic', $class]) }}>
  <picture>
    <source type="image/webp" srcset="{{ $webpSrcset }}" sizes="{{ $sizes }}">
    <img
      src="{{ $jpg }}"
      alt="{{ $altText }}"
      width="{{ $w }}"
      height="{{ $h }}"
      decoding="async"
      loading="{{ $loading }}"
      @if($fetchpriority) fetchpriority="{{ $fetchpriority }}" @endif
    >
  </picture>
  @if(!empty($credits['attribution_required']))
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
