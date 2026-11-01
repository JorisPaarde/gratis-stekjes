<!DOCTYPE html>
<html lang="nl">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  @php($siteName = config('site.name'))
  @php($noindex = (bool) config('site.noindex') || app()->environment('staging'))
  @php($accountNoindex = request()->is('account', 'account/*', 'berichtmail/*'))
  @php($documentTitle = \App\Support\SeoTitle::document($__env->yieldContent('title')))
  @php($ogPad = trim($__env->yieldContent('og_image')) ?: (string) config('site.og_image', '/assets/og/standaard.jpg'))
  @php($ogImage = \App\Support\CanonicalUrl::asset($ogPad))
  <title>{{ $documentTitle }}</title>
  <meta name="description" content="@yield('meta_description', config('site.description'))">
  @if($noindex || $accountNoindex)
  <meta name="robots" content="noindex, nofollow">
  @elseif(trim($__env->yieldContent('robots')) !== '')
  <meta name="robots" content="@yield('robots')">
  @endif
  @hasSection('canonical')
  <link rel="canonical" href="@yield('canonical')">
  @endif
  <meta property="og:type" content="website">
  <meta property="og:locale" content="nl_NL">
  <meta property="og:site_name" content="{{ $siteName }}">
  <meta property="og:title" content="{{ $documentTitle }}">
  <meta property="og:description" content="@yield('meta_description', config('site.description'))">
  @hasSection('canonical')
  <meta property="og:url" content="@yield('canonical')">
  @endif
  <meta property="og:image" content="{{ $ogImage }}">
  @if(str_starts_with($ogPad, '/assets/og/'))
  <meta property="og:image:width" content="1200">
  <meta property="og:image:height" content="630">
  <meta property="og:image:type" content="image/jpeg">
  @endif
  <meta property="og:image:alt" content="{{ $documentTitle }}">
  <meta name="twitter:card" content="summary_large_image">
  <meta name="twitter:image" content="{{ $ogImage }}">
  <meta name="theme-color" content="{{ config('site.theme_color') }}">
  <meta name="application-name" content="{{ $siteName }}">
  <meta name="apple-mobile-web-app-capable" content="yes">
  <meta name="apple-mobile-web-app-title" content="{{ $siteName }}">
  <meta name="gp-app-version" content="{{ config('site.version') }}">
  @vite(['resources/css/app.css', 'resources/js/app.js'])
  <link rel="manifest" href="{{ url('/manifest.webmanifest') }}">
  <link rel="icon" href="{{ asset('favicon.ico') }}" sizes="any">
  <link rel="icon" type="image/png" href="{{ asset('assets/icons/icon-192.png') }}" sizes="192x192">
  <link rel="apple-touch-icon" href="{{ asset('assets/icons/apple-touch-icon.png') }}">
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
      <button type="button" class="nav-toggle" aria-expanded="false" aria-controls="hoofdmenu" data-nav-toggle>
        <x-icon name="menu" :size="18" /> Menu
      </button>
      <nav id="hoofdmenu" class="site-nav" data-nav>
        <a href="{{ route('aanbod') }}"><x-icon name="kaart" :size="16" /> Aanbod</a>
        <a href="{{ route('hoe-werkt-het') }}"><x-icon name="info" :size="16" /> Hoe werkt het</a>
        <a href="{{ route('stektips.index') }}"><x-icon name="stekje" :size="16" /> Stektips</a>
        <a href="{{ route('deelplekken') }}"><x-icon name="locatie" :size="16" /> Deelplekken</a>
        @can('see-admin-dashboard')
          <a href="{{ route('beheer.index') }}"><x-icon name="kaart" :size="16" /> Dashboard</a>
        @endcan
        @auth
          @if(($ongelezenBerichten ?? 0) > 0)
            <a href="{{ route('account.inbox') }}"><x-icon name="gebruiker" :size="16" /> Account <span class="nav-badge" aria-label="{{ $ongelezenBerichten }} ongelezen {{ $ongelezenBerichten === 1 ? 'bericht' : 'berichten' }}">{{ $ongelezenBerichten }}</span></a>
          @else
            <a href="{{ route('account.index') }}"><x-icon name="gebruiker" :size="16" /> Account</a>
          @endif
          <form class="nav-logout" method="post" action="{{ route('account.uitloggen') }}">
            @csrf
            <button type="submit" class="nav-cta">Uitloggen</button>
          </form>
        @else
          <a href="{{ route('account.inloggen') }}"><x-icon name="gebruiker" :size="16" /> Inloggen</a>
          <a class="nav-cta" href="{{ route('account.registreren') }}"><x-icon name="plus" :size="16" /> Doe mee</a>
        @endauth
      </nav>
    </div>
  </header>
  <main id="inhoud">
    @if(request()->hasSession() && session('status'))
      <div class="wrap" style="padding-top:1rem">
        <p class="flash {{ session('status_level') === 'warn' ? 'flash-warn' : 'flash-ok' }}" role="status">{{ session('status') }}</p>
      </div>
    @endif
    @yield('content')
  </main>
  <footer class="site-footer">
    <div class="wrap footer-grid">
      <div class="footer-merk">
        <a class="footer-brand" href="{{ route('home') }}">{{ $siteName }}</a>
        <p>Gratis planten, stekjes en zaden delen in Nederland en België. Zelf ophalen, en vaak een praatje erbij.</p>
        @auth
          <p><a class="btn footer-cta" href="{{ route('account.index') }}"><x-icon name="gebruiker" :size="16" /> Mijn account</a></p>
        @else
          <p><a class="btn footer-cta" href="{{ route('account.registreren') }}"><x-icon name="plus" :size="16" /> Doe mee</a></p>
        @endauth
        <p class="footer-contact"><a href="mailto:{{ config('site.contact_email') }}">{{ config('site.contact_email') }}</a></p>
      </div>
      <nav class="footer-links" aria-label="Footer">
        <div class="footer-kolom">
          <h2>Planten vinden</h2>
          <ul>
            <li><a href="{{ route('aanbod') }}">Aanbod en kaart</a></li>
            <li><a href="{{ route('deelplekken') }}">Deelplekken</a></li>
            <li><a href="{{ route('gratis-planten.index') }}">Gratis planten in jouw stad</a></li>
            <li><a href="{{ route('partners') }}">Initiatieven</a></li>
          </ul>
        </div>
        <div class="footer-kolom">
          <h2>Zelf stekken</h2>
          <ul>
            <li><a href="{{ route('stektips.index') }}">Stektips</a></li>
            <li><a href="{{ route('hulpmiddelen') }}">Hulpmiddelen</a></li>
            <li><a href="{{ route('invasieve-soorten') }}">Invasieve soorten</a></li>
          </ul>
        </div>
        <div class="footer-kolom">
          <h2>Over Gratis planten</h2>
          <ul>
            <li><a href="{{ route('hoe-werkt-het') }}">Hoe werkt het</a></li>
            <li><a href="{{ route('over') }}">Over</a></li>
            <li><a href="{{ route('voor-organisaties') }}">Voor organisaties</a></li>
            <li><a href="{{ route('contact') }}">Contact</a></li>
            <li><a href="{{ route('privacy') }}">Privacy</a></li>
          </ul>
        </div>
      </nav>
    </div>
    <div class="wrap footer-onder">
      <p class="footer-meta">Gratis en zonder reclame · KvK 90397320 · btw NL004813310B95</p>
    </div>
    {{-- Niet zichtbaar voor bezoekers; deploy-smoke (scripts/deploy-hook-upload.sh) grept op «Versie x.y.z». --}}
    <!-- Versie {{ config('site.version') }} -->
  </footer>
  @auth
    @unless(request()->is('beheer', 'beheer/*', 'feedback'))
      @include('partials.feedback-fab')
    @endunless
  @endauth
  @stack('scripts')
</body>
</html>
