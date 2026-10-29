<!DOCTYPE html>
<html lang="nl">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  @php($siteName = config('site.name'))
  @php($noindex = (bool) config('site.noindex') || app()->environment('staging'))
  <title>@hasSection('title')@yield('title') · {{ $siteName }}@else{{ $siteName }}@endif</title>
  <meta name="description" content="@yield('meta_description', config('site.description'))">
  @if($noindex)
  <meta name="robots" content="noindex, nofollow">
  @endif
  <link rel="canonical" href="@yield('canonical', url()->current())">
  <meta property="og:type" content="website">
  <meta property="og:locale" content="nl_NL">
  <meta property="og:site_name" content="{{ $siteName }}">
  <meta property="og:title" content="@hasSection('title')@yield('title') · {{ $siteName }}@else{{ $siteName }}@endif">
  <meta property="og:description" content="@yield('meta_description', config('site.description'))">
  <meta property="og:url" content="@yield('canonical', url()->current())">
  <meta name="theme-color" content="{{ config('site.theme_color') }}">
  @vite(['resources/css/app.css', 'resources/js/app.js'])
  <link rel="manifest" href="{{ asset('manifest.webmanifest') }}">
  <link rel="icon" href="{{ asset('assets/icons/icon-192.png') }}" sizes="192x192">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Fraunces:opsz,wght@9..144,500;9..144,700&family=Figtree:wght@400;560;700&display=swap" rel="stylesheet">
  @isset($schema)
  <script type="application/ld+json">{!! json_encode($schema, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES) !!}</script>
  @endisset
  @stack('head')
</head>
<body class="@yield('body_class')">
  <a class="skip-link" href="#inhoud">Naar inhoud</a>
  <header class="site-header">
    <div class="wrap header-inner">
      <a class="brand" href="{{ route('home') }}">{{ $siteName }}</a>
      <button type="button" class="nav-toggle" aria-expanded="false" aria-controls="hoofdmenu" data-nav-toggle>Menu</button>
      <nav id="hoofdmenu" class="site-nav" data-nav>
        <a href="{{ route('home') }}#hoe-werkt-het">Hoe werkt het</a>
        <span class="nav-soon" title="Volgt in een volgende release">Aanbod <em>binnenkort</em></span>
        <span class="nav-soon" title="Volgt in een volgende release">Stektips <em>binnenkort</em></span>
        <a href="{{ route('home') }}#partners">Partners</a>
        <a href="{{ route('privacy') }}">Privacy</a>
        <a class="nav-cta" href="{{ route('home') }}#doe-mee">Doe mee</a>
      </nav>
    </div>
  </header>
  <main id="inhoud">
    @yield('content')
  </main>
  <footer class="site-footer">
    <div class="wrap footer-inner">
      <p><strong>{{ $siteName }}</strong> — planten delen, een praatje als enige prijs.</p>
      <p class="footer-links">
        <a href="{{ route('privacy') }}">Privacy</a>
        <a href="{{ route('home') }}#doe-mee">Doe mee</a>
      </p>
      <p class="footer-meta">Versie {{ config('site.version') }}@if($noindex) · noindex @endif</p>
    </div>
  </footer>
  @stack('scripts')
</body>
</html>
