@extends('layouts.site')

@section('title', $title)
@section('meta_description', $metaDescription)
@section('canonical', $canonical)

@push('head')
  @vite(['resources/css/struikroven.css'])
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
@endphp
<section class="section wrap struikroven-page">
  <h1 class="page-title">{{ $init->pagina_titel !== '' ? $init->pagina_titel : $korte }}</h1>
  @if($init->intro_titel !== '')
    <h2>{{ $init->intro_titel }}</h2>
  @endif
  @foreach($init->introAlineas() as $alinea)
    <p>{{ $alinea }}</p>
  @endforeach
  @if(trim((string) $init->site_url) !== '' || trim((string) $init->secundaire_link_label) !== '')
    <p class="struikroven-links muted">
      @if(trim((string) $init->site_url) !== '')
        <a href="{{ $init->site_url }}" rel="noopener noreferrer" target="_blank">{{ $init->site_link_tekst !== '' ? $init->site_link_tekst : 'Meer info' }}</a>
      @endif
      @if(trim((string) $init->site_url) !== '' && trim((string) $init->secundaire_link_label) !== '')
        ·
      @endif
      @if(trim((string) $init->secundaire_link_label) !== '')
        <a href="{{ route('organisaties.show', ['slug' => $init->organisatie_slug]) }}">{{ $init->secundaire_link_label }}</a>
      @endif
    </p>
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
      <p>{{ $init->acties_leeg_tekst !== '' && $init->acties_leeg_tekst !== null ? $init->acties_leeg_tekst : 'Er staan nu geen acties gepland op deze agenda. Zodra er iets bij komt, zie je het hier.' }}</p>
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

  <h2>{{ $init->leden_titel !== '' ? $init->leden_titel : 'Voor leden' }}</h2>
  <p>{{ $init->leden_intro !== '' && $init->leden_intro !== null ? $init->leden_intro : 'Wil je Gratis planten bij een actie of in je nieuwsbrief noemen? Gebruik dit gerust.' }}</p>
  <ul class="struikroven-materiaal list-plain">
    <li><a href="{{ route('poster', ['voor' => $slug]) }}">A4-poster om te printen</a></li>
    <li><a href="{{ route('poster', ['voor' => $slug, 'formaat' => 'a5']) }}">A5-flyer om te printen</a></li>
  </ul>
  <div class="struikroven-kopieer">
    <label class="struikroven-kopieer-label" for="{{ $deelId }}">Tekst voor nieuwsbrief of socials</label>
    <textarea id="{{ $deelId }}" class="struikroven-deeltekst" readonly rows="4">{{ $init->kopieertekst }}</textarea>
    <button type="button" class="btn btn-secondary btn-klein" data-kopieer="{{ $deelId }}">Kopieer tekst</button>
  </div>
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
