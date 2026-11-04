@extends('layouts.site')

@section('title', $title)
@section('meta_description', $metaDescription)
@section('canonical', $canonical)

@push('head')
  @vite(['resources/css/struikroven.css'])
  <script type="application/ld+json">{!! json_encode($schema, JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE) !!}</script>
@endpush

@section('content')
<section class="section wrap struikroven-page">
  <h1 class="page-title">Struikroven redt planten van de sloop</h1>
  <h2>Wie zijn de Struikrovers?</h2>
  <p>
    Als huizen worden gesloopt of opgeknapt, verdwijnt vaak veel gezond groen uit voor- en achtertuinen. Zonde, want volwassen planten en struiken geven karakter aan een buurt.
  </p>
  <p>
    Daarom organiseert Stichting Struikroven struikroofacties. Buurtbewoners halen dan samen de planten uit de grond en geven ze een tweede leven in hun eigen tuin, op hun balkon, in de buurt of in een logeertuin. Meedoen is gratis en iedereen is welkom, jong en oud.
  </p>
  <p class="struikroven-links muted">
    <a href="https://struikroven.nu" rel="noopener noreferrer" target="_blank">Meer over Struikroven</a>
    ·
    <a href="{{ route('organisaties.show', ['slug' => 'struikroven']) }}">Over de stichting</a>
  </p>

  <h2>Doe je mee met Struikroven?</h2>
  <p>
    Of heb je zelf planten die een tweede leven verdienen? Op Gratis planten deel je planten, stekjes en zaden gratis met iemand in de buurt. Kijk hier verder.
  </p>
  <div class="struikroven-ctas" role="group" aria-label="Wat wil je doen?">
    <a class="btn struikroven-cta" href="{{ route('aanbod') }}">Planten zoeken in je buurt</a>
    @auth
      <a class="btn btn-secondary struikroven-cta" href="{{ route('aanbieden') }}">Planten over? Bied ze gratis aan</a>
    @else
      <a class="btn btn-secondary struikroven-cta" href="{{ route('account.registreren', ['struikrover' => 1]) }}">Planten over? Bied ze gratis aan</a>
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

  <h2>Komende Struikroven-acties</h2>
  @if($acties->isEmpty())
    <div class="empty-state" role="status">
      <p>Er staan nu geen Struikroven-acties gepland op deze agenda. Zodra er iets bij komt, zie je het hier.</p>
      <p class="muted">De actieagenda van Struikroven zelf staat op <a href="https://struikroven.nu/agenda" rel="noopener noreferrer" target="_blank">struikroven.nu/agenda</a>.</p>
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

  <h2>Voor Struikrovers</h2>
  <p>Wil je Gratis planten bij een actie of in je nieuwsbrief noemen? Gebruik dit gerust.</p>
  <ul class="struikroven-materiaal list-plain">
    <li><a href="{{ route('poster', ['voor' => 'struikroven']) }}">A4-poster om te printen</a></li>
    <li><a href="{{ route('poster', ['voor' => 'struikroven', 'formaat' => 'a5']) }}">A5-flyer om te printen</a></li>
  </ul>
  <div class="struikroven-kopieer">
    <label class="struikroven-kopieer-label" for="struikroven-deeltekst">Tekst voor nieuwsbrief of socials</label>
    <textarea id="struikroven-deeltekst" class="struikroven-deeltekst" readonly rows="4">Planten over na een struikroofactie? Of zoek je juist groen voor je tuin? Op Gratis planten deel je planten, stekjes en zaden gratis met mensen in de buurt. Kijk op gratisplanten.nl/struikroven</textarea>
    <button type="button" class="btn btn-secondary btn-klein" data-kopieer="struikroven-deeltekst">Kopieer tekst</button>
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
