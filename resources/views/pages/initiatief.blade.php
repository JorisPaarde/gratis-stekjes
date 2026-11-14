@extends('layouts.site')

@section('title', $title)
@section('meta_description', $metaDescription)
@section('canonical', $canonical)

@push('head')
  @vite(array_merge(['resources/css/struikroven.css'], $init->secties !== [] ? ['resources/css/stektips.css'] : []))
  <script type="application/ld+json">{!! json_encode($schema, JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE) !!}</script>
@endpush

@section('content')
@php
  $slug = $init->initiatief_slug;
  $korte = $init->korte_naam !== '' ? $init->korte_naam : (string) ($org['naam'] ?? $slug);
  $deelId = $slug.'-deeltekst';
  $aanmeldParam = trim((string) $init->aanmeld_param);
  $agendaUrl = trim((string) $init->agenda_url);
  $agendaHost = $agendaUrl !== '' ? (preg_replace('#^https?://#i', '', $agendaUrl) ?: $agendaUrl) : '';
  $isStekchallenge = $slug === 'stekchallenge';
  $isStruikroven = $slug === 'struikroven';
  $toonLedenBlok = $init->leden_titel !== '';
@endphp
<section class="section wrap struikroven-page">
  <h1 class="page-title">{{ $init->pagina_titel !== '' ? $init->pagina_titel : $korte }}</h1>

  @if($isStruikroven)
    <div class="struikroven-logo-kaart">
      <picture>
        <source type="image/webp" srcset="/assets/images/initiatieven/logo-struikroven.webp">
        <img
          class="struikroven-logo"
          src="/assets/images/initiatieven/logo-struikroven.png"
          width="220"
          height="110"
          alt="Struikroven"
          decoding="async"
        >
      </picture>
    </div>
  @endif

  @if($isStekchallenge)
    <figure class="struikroven-hero">
      <picture>
        <source type="image/webp" srcset="/assets/images/initiatieven/stekchallenge-groep-800.webp 800w, /assets/images/initiatieven/stekchallenge-groep-1280.webp 1280w" sizes="(min-width: 900px) 800px, 92vw">
        <img src="/assets/images/initiatieven/stekchallenge-groep-800.jpg" srcset="/assets/images/initiatieven/stekchallenge-groep-800.jpg 800w, /assets/images/initiatieven/stekchallenge-groep-1280.jpg 1280w" sizes="(min-width: 900px) 800px, 92vw" width="800" height="600" alt="Groep mensen met stekken in potten tijdens een stekbijeenkomst" decoding="async">
      </picture>
      <figcaption>Foto: Carpe Vitam</figcaption>
    </figure>

    <h2>Doe mee</h2>
    <p>Drie manieren om meteen te starten:</p>
    <div class="struikroven-ctas" role="group" aria-label="STEKchallenge-acties">
      <a class="btn struikroven-cta" href="https://carpevitam.net/event/stek-challenge-workshop-stekken-8-november/" rel="noopener noreferrer" target="_blank">Workshop volgen: 8 november in Elim</a>
      <a class="btn btn-secondary struikroven-cta" href="https://carpevitam.net/stekchallenge/" rel="noopener noreferrer" target="_blank">Workshop geven</a>
      <a class="btn btn-secondary struikroven-cta" href="https://carpevitam.net/contact/" rel="noopener noreferrer" target="_blank">Contact</a>
    </div>
  @endif

  @if($init->intro_titel !== '')
    <h2>{{ $init->intro_titel }}</h2>
  @endif
  @foreach($init->introAlineas() as $alinea)
    <p>{{ $alinea }}</p>
  @endforeach

  @if($isStekchallenge)
    <div class="struikroven-verhaal">
      <figure class="struikroven-verhaal-foto">
        <picture>
          <source type="image/webp" srcset="/assets/images/initiatieven/stekchallenge-dewy-stekje-400.webp 400w, /assets/images/initiatieven/stekchallenge-dewy-stekje-800.webp 800w" sizes="(min-width: 720px) 280px, 70vw">
          <img src="/assets/images/initiatieven/stekchallenge-dewy-stekje-400.jpg" srcset="/assets/images/initiatieven/stekchallenge-dewy-stekje-400.jpg 400w, /assets/images/initiatieven/stekchallenge-dewy-stekje-800.jpg 800w" sizes="(min-width: 720px) 280px, 70vw" width="400" height="533" alt="Dewy Mulder houdt een stekje in een pot vast" loading="lazy" decoding="async">
        </picture>
        <figcaption>Foto: Carpe Vitam</figcaption>
      </figure>
      <div>
        <p>Geschikte soorten voor houtstekken in het najaar zijn onder meer <a href="{{ route('stektips.show', ['slug' => 'rode-bes']) }}">rode bes</a>, <a href="{{ route('stektips.show', ['slug' => 'zwarte-bes']) }}">zwarte bes</a>, <a href="{{ route('stektips.show', ['slug' => 'wilg']) }}">wilg</a> en <a href="{{ route('stektips.show', ['slug' => 'zwarte-vlier']) }}">vlier</a>.</p>
        <p><a href="https://carpevitam.net/stekchallenge/" rel="noopener noreferrer" target="_blank">Registreer je stekken</a> — je krijgt een persoonlijke link per mail. <a href="https://carpevitam.net/geef-je-snoeihout-een-toekomst-doe-mee-met-de-stekchallenge/" rel="noopener noreferrer" target="_blank">Lees het persbericht</a>.</p>
        <p class="muted">Kijk ook op onze <a href="{{ route('vermeerderen') }}">stektips</a> hoe je planten vermeerdert.</p>
      </div>
    </div>
  @endif

  @if($init->site_url !== '')
    <p class="struikroven-links muted">
      <a href="{{ $init->site_url }}" rel="noopener noreferrer" target="_blank">{{ $init->site_link_tekst !== '' ? $init->site_link_tekst : 'Meer info' }}</a>
    </p>
  @endif

  @if($init->secties !== [])
    <div class="stektips-page stektips-artikel-body org-secties">
      @foreach($init->secties as $block)
        @include('partials.kennisbank-blok', ['block' => $block])
      @endforeach
    </div>
  @endif

  <h2>Doe je mee met {{ $korte }}?</h2>
  <p>
    Of heb je zelf planten die een tweede leven verdienen? Op Gratis planten deel je planten, stekjes en zaden gratis met iemand in de buurt. Kijk hier verder.
  </p>
  <div class="struikroven-ctas" role="group" aria-label="Wat wil je doen?">
    <a class="btn struikroven-cta" href="{{ route('aanbod') }}">Planten zoeken in je buurt</a>
    @auth
      <a class="btn btn-secondary struikroven-cta" href="{{ route('aanbieden') }}">Planten over? Bied ze gratis aan</a>
    @else
      <a class="btn btn-secondary struikroven-cta" href="{{ route('account.registreren', $aanmeldParam !== '' ? [$aanmeldParam => 1] : []) }}">Planten over? Bied ze gratis aan</a>
    @endauth
  </div>
  <p class="muted struikroven-cta-hint">Zoeken kan zonder account. Aanbieden vraagt een gratis account.</p>

  <h2>Zo werkt het</h2>
  <ol class="struikroven-stappen">
    <li>Planten over na een actie? Zet ze er gratis op.</li>
    <li>Iets nodig voor je tuin? Zoek wat er in de buurt is.</li>
    <li>Spreek af met de aanbieder en haal het op. Alles is gratis, er wordt niets verkocht.</li>
  </ol>
  <p>
    Op de site staan geen straatadressen: alleen een postcode of plaats, zodat je ziet wat er in de buurt is.
  </p>

  <h2>Komende {{ $korte }}-acties</h2>
  @if($acties->isEmpty())
    <div class="empty-state" role="status">
      <p>{{ $init->acties_leeg_tekst !== '' ? $init->acties_leeg_tekst : 'Er staan nu geen acties gepland op deze agenda. Zodra er iets bij komt, zie je het hier.' }}</p>
      @if($agendaUrl !== '')
        <p class="muted">De actieagenda van {{ $korte }} zelf staat op <a href="{{ $agendaUrl }}" rel="noopener noreferrer" target="_blank">{{ $agendaHost }}</a>.</p>
      @endif
    </div>
  @else
    <ul class="struikroven-acties list-plain">
      @foreach($acties as $event)
        <li class="struikroven-actie">
          <p class="struikroven-actie-titel">
            @if(trim((string) $event->url) !== '')
              <a href="{{ $event->url }}" rel="noopener noreferrer" target="_blank">{{ $event->titel }}</a>
            @else
              {{ $event->titel }}
            @endif
          </p>
          <p class="struikroven-actie-meta muted">
            <time datetime="{{ $event->start_datum->format('Y-m-d') }}">{{ $evenementen->formatDateRange($event) }}</time>
            · {{ $event->plaats }}
            @if(trim((string) $event->organisator) !== '')
              · {{ $event->organisator }}
            @endif
          </p>
        </li>
      @endforeach
    </ul>
  @endif

  @if($toonLedenBlok)
    <h2>{{ $init->leden_titel }}</h2>
    <p>{{ $init->leden_intro !== '' ? $init->leden_intro : 'Wil je Gratis planten bij een actie of in je nieuwsbrief noemen? Gebruik dit gerust.' }}</p>
    <ul class="struikroven-materiaal list-plain">
      <li><a href="{{ route('poster', ['voor' => $slug]) }}">A4-poster om te printen</a></li>
      <li><a href="{{ route('poster', ['voor' => $slug, 'formaat' => 'a5']) }}">A5-flyer om te printen</a></li>
    </ul>
    @if($isStekchallenge)
      <figure class="struikroven-banner">
        <picture>
          <source type="image/webp" srcset="/assets/images/initiatieven/stekchallenge-banner-215.webp 215w, /assets/images/initiatieven/stekchallenge-banner-430.webp 430w" sizes="215px">
          <img src="/assets/images/initiatieven/stekchallenge-banner-215.jpg" srcset="/assets/images/initiatieven/stekchallenge-banner-215.jpg 215w, /assets/images/initiatieven/stekchallenge-banner-430.jpg 430w" sizes="215px" width="215" height="215" alt="STEKchallenge-campagnebanner" loading="lazy" decoding="async">
        </picture>
        <figcaption>Foto: Carpe Vitam · Deel met #STEKchallenge</figcaption>
      </figure>
    @endif
    @if($init->kopieertekst !== '')
      <div class="struikroven-kopieer">
        <label class="struikroven-kopieer-label" for="{{ $deelId }}">Tekst voor nieuwsbrief of socials</label>
        <textarea id="{{ $deelId }}" class="struikroven-deeltekst" readonly rows="4">{{ $init->kopieertekst }}</textarea>
        <button type="button" class="btn btn-secondary btn-klein" data-kopieer="{{ $deelId }}">Kopieer tekst</button>
      </div>
    @endif
  @endif
</section>
@endsection

@push('scripts')
<script>
  document.querySelectorAll('[data-kopieer]').forEach(function (btn) {
    btn.addEventListener('click', function () {
      var el = document.getElementById(btn.getAttribute('data-kopieer'));
      var tekst = el ? (el.value !== undefined ? el.value : el.textContent).trim() : '';
      var klaar = function () { var o = btn.textContent; btn.textContent = 'Gekopieerd ✓'; setTimeout(function () { btn.textContent = o; }, 2000); };
      if (navigator.clipboard) { navigator.clipboard.writeText(tekst).then(klaar).catch(function () {}); }
      else if (el && el.select) { el.select(); document.execCommand('copy'); klaar(); }
    });
  });
</script>
@endpush
