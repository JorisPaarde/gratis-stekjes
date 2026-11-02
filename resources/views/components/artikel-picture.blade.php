@props([
    'id',
    'eager' => false,
    'sizes' => '(max-width: 640px) 100vw, (max-width: 1024px) 80vw, 800px',
    'class' => '',
    'decorative' => false,
])

@php
    static $manifest = null;
    if ($manifest === null) {
        $path = resource_path('data/artikel-fotos.json');
        $raw = is_readable($path) ? json_decode((string) file_get_contents($path), true) : null;
        $manifest = is_array($raw) ? $raw : ['fotos' => []];
    }
    $foto = $manifest['fotos'][$id] ?? null;
@endphp

@if($foto)
  @php
    $variants = $foto['variants'] ?? [];
    $avifSrcset = collect($variants)
        ->map(fn (array $v) => asset($v['avif']).' '.$v['w'].'w')
        ->implode(', ');
    $webpSrcset = collect($variants)
        ->map(fn (array $v) => asset($v['webp']).' '.$v['w'].'w')
        ->implode(', ');
    $jpgSrcset = collect($variants)
        ->map(fn (array $v) => asset($v['jpg']).' '.$v['w'].'w')
        ->implode(', ');
    $fallback = asset($variants[count($variants) > 1 ? 1 : 0]['jpg'] ?? ($variants[0]['jpg'] ?? ''));
    $w = (int) ($foto['width'] ?? 1600);
    $h = (int) ($foto['height'] ?? 1067);
    $alt = $decorative ? '' : (string) ($foto['alt'] ?? '');
    $loading = $eager ? 'eager' : 'lazy';
  @endphp
  <picture {{ $attributes->class(['kb-pic', $class]) }}>
    @if($avifSrcset !== '')
      <source type="image/avif" srcset="{{ $avifSrcset }}" sizes="{{ $sizes }}">
    @endif
    @if($webpSrcset !== '')
      <source type="image/webp" srcset="{{ $webpSrcset }}" sizes="{{ $sizes }}">
    @endif
    <img
      src="{{ $fallback }}"
      @if($jpgSrcset !== '') srcset="{{ $jpgSrcset }}" sizes="{{ $sizes }}" @endif
      alt="{{ $alt }}"
      width="{{ $w }}"
      height="{{ $h }}"
      decoding="async"
      loading="{{ $loading }}"
      @if($eager) fetchpriority="high" @endif
    >
  </picture>
@endif
