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
  <h1 class="page-title">{{ config('site.name') }}</h1>
  <p class="section-intro struikroven-lead">
    Planten over van een Struikroofactie, of op zoek naar groen voor je tuin?
    Hier deel je planten, stekjes en zaden gratis met iemand in de buurt.
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

  <section class="struikroven-over" aria-labelledby="struikroven-over-titel">
    <h2 id="struikroven-over-titel">Wie zijn de Struikrovers?</h2>
    <p>
      Struikroven redt planten van de sloop. Als huizen worden gesloopt of opgeknapt, verdwijnt vaak veel gezond groen uit voor- en achtertuinen. Zonde, want volwassen planten en struiken geven karakter aan een buurt.
    </p>
    <p>
      Tijdens een struikroofactie halen buurtbewoners samen die planten uit de grond en geven ze een tweede leven in hun eigen tuin, op hun balkon, in de buurt of in een logeertuin. Meedoen is gratis en iedereen is welkom, jong en oud.
    </p>
    <p class="struikroven-over-links">
      <a href="https://struikroven.nu" rel="noopener" target="_blank">Meer over Struikroven</a>
      <a href="https://struikroven.nu/agenda" rel="noopener" target="_blank">Bekijk de agenda van Struikroven</a>
      <a href="{{ route('partners') }}">Meer initiatieven</a>
    </p>
  </section>

  <h2>Wat is {{ config('site.name') }}?</h2>
  <p>
    Alles is gratis. Er wordt niets verkocht. Je spreekt zelf af met de aanbieder
    en haalt de planten op. Op de site staan geen straatadressen: alleen een postcode
    of plaats, zodat je ziet wat er in de buurt is.
  </p>
  <p>
    Wat je overhoudt of mist na een Struikroofactie, kun je hier gratis delen met iemand in de buurt.
    @if($org)
      Meer op de
      <a href="{{ route('organisaties.show', ['slug' => 'struikroven']) }}">organisatiepagina</a>.
    @endif
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
</section>
@endsection
