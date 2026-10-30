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
        <a href="{{ route('hoe-werkt-het') }}"><x-icon name="info" :size="16" /> Hoe werkt het</a>
        <a href="{{ route('aanbod') }}"><x-icon name="kaart" :size="16" /> Aanbod</a>
        <a href="{{ route('stektips.index') }}"><x-icon name="stekje" :size="16" /> Stektips</a>
        <a href="{{ route('hulpmiddelen') }}"><x-icon name="plant" :size="16" /> Hulpmiddelen</a>
        <a href="{{ route('partners') }}"><x-icon name="initiatief" :size="16" /> Initiatieven</a>
        <a href="{{ route('deelplekken') }}"><x-icon name="locatie" :size="16" /> Deelplekken</a>
        <a href="{{ route('over') }}"><x-icon name="hart" :size="16" /> Over</a>
        <a href="{{ route('privacy') }}"><x-icon name="check" :size="16" /> Privacy</a>
        @auth
          <a href="{{ route('account.index') }}"><x-icon name="gebruiker" :size="16" /> Account</a>
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
    @if(session('status'))
      <div class="wrap" style="padding-top:1rem">
        <p class="flash {{ session('status_level') === 'warn' ? 'flash-warn' : 'flash-ok' }}" role="status">{{ session('status') }}</p>
      </div>
    @endif
    @yield('content')
  </main>
  <footer class="site-footer">
    <div class="wrap footer-inner">
      <p><strong>{{ $siteName }}</strong> — planten delen, een praatje als enige prijs.</p>
      <p class="footer-links">
        <a href="{{ route('privacy') }}">Privacy</a>
        <a href="{{ route('contact') }}">Contact</a>
        <a href="{{ route('over') }}">Over</a>
        <a href="{{ route('stektips.index') }}">Stektips</a>
        <a href="{{ route('aanbod') }}">Aanbod</a>
        <a href="{{ route('gratis-planten.index') }}">Gratis planten in jouw stad</a>
        <a href="{{ route('invasieve-soorten') }}">Invasieve soorten</a>
        <a href="{{ route('partners') }}">Initiatieven</a>
        <a href="{{ route('deelplekken') }}">Deelplekken</a>
        <a href="{{ route('voor-organisaties') }}">Voor organisaties</a>
        @auth
          <a href="{{ route('account.index') }}">Account</a>
        @else
          <a href="{{ route('account.registreren') }}">Doe mee</a>
        @endauth
      </p>
      <p class="footer-meta">
        <a href="mailto:{{ config('site.contact_email') }}">{{ config('site.contact_email') }}</a>
        · KvK 90397320 · btw NL004813310B95
        · Versie {{ config('site.version') }}@if($noindex) · noindex @endif
      </p>
    </div>
  </footer>
  @auth
    @unless(request()->is('beheer', 'beheer/*', 'feedback'))
      @include('partials.feedback-fab')
    @endunless
  @endauth
  @stack('scripts')
</body>
</html>
