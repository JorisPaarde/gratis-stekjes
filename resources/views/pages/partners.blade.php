@extends('layouts.site')

@section('title', $title)
@section('meta_description', $metaDescription)
@section('canonical', $canonical)

@push('head')
  @vite(['resources/css/partners.css'])
@endpush

@section('content')
<section class="section wrap partners-page">
  <h1 class="page-title">{{ $h1 }}</h1>
  <p class="section-intro">Hier verzamel ik stekjesmarkten, plantenruilbeurzen en ruildagen voor 2026, plus vaste ruilinitiatieven zonder vaste datum. Het zijn geen officiële partnerschappen, alleen doorverwijzingen naar plekken rond gratis of gedeeld groen. Check altijd de externe link voor actuele informatie.</p>

  <h2 id="agenda">Agenda 2026</h2>
  <p class="section-intro">Komende evenementen chronologisch, gegroepeerd per maand. Filter op provincie of type als je wilt.</p>

  <form class="form partners-agenda-filters" method="get" action="{{ route('partners') }}" style="max-width:100%;margin:1rem 0">
    <div class="partners-filter-grid">
      <label>Type
        <select name="type">
          <option value="">Alle types</option>
          @foreach($typeLabels as $key => $label)
            <option value="{{ $key }}" @selected($filters['type'] === $key)>{{ $label }}</option>
          @endforeach
        </select>
      </label>
      <label>Provincie
        <select name="provincie">
          <option value="">Alle provincies</option>
          @foreach($provincies as $p)
            <option value="{{ $p }}" @selected($filters['provincie'] === $p)>{{ $p }}</option>
          @endforeach
        </select>
      </label>
      <button class="btn" type="submit">Filter</button>
      @if($filters['type'] !== '' || $filters['provincie'] !== '')
        <a class="btn btn-secondary" href="{{ route('partners') }}">Wis filters</a>
      @endif
    </div>
  </form>

  @if($hasUpcoming)
    <div class="partners-agenda">
      @foreach($upcomingByMonth as $yearMonth => $events)
        <section class="partners-month" aria-labelledby="maand-{{ $yearMonth }}">
          <h3 id="maand-{{ $yearMonth }}" class="partners-month-title">{{ $evenementen->monthLabel($yearMonth) }}</h3>
          <ul class="partners-event-list">
            @foreach($events as $event)
              <li class="partners-event soft-card">
                <p class="partners-event-meta">
                  <span class="badge">{{ $typeLabels[$event->type] ?? $event->type }}</span>
                  <time datetime="{{ $event->start_datum->format('Y-m-d') }}">{{ $evenementen->formatDateRange($event) }}</time>
                </p>
                <h4 class="partners-event-title">
                  @if($event->url !== '')
                    <a href="{{ $event->url }}" rel="noopener noreferrer" target="_blank">{{ $event->titel }}</a>
                  @else
                    {{ $event->titel }}
                  @endif
                </h4>
                <p>{{ $event->plaats }} · {{ $event->provincie }}</p>
                @if($event->organisator !== '')
                  <p class="muted">Organisatie: {{ $event->organisator }}</p>
                @endif
                @if($event->gecontroleerd_op)
                  <p class="muted">Laatst gecontroleerd {{ $event->gecontroleerd_op->format('Y-m-d') }}</p>
                @endif
              </li>
            @endforeach
          </ul>
        </section>
      @endforeach
    </div>
  @elseif($hasPast)
    <p class="muted partners-agenda-empty" role="status">Geen komende evenementen voor deze filters. Eerdere staan hieronder onder Geweest.</p>
  @elseif($filters['type'] !== '' || $filters['provincie'] !== '')
    <div class="empty-state partners-agenda-empty" role="status">
      <p>Geen evenementen voor deze filters. <a href="{{ route('partners') }}">Toon alles</a></p>
    </div>
  @else
    <div class="empty-state partners-agenda-empty" role="status">
      <p>Nog geen data voor 2026 bekend; ken jij er een? <a href="{{ route('feedback.create', ['page_path' => '/initiatieven']) }}">Meld het</a> via het feedbackformulier.</p>
    </div>
  @endif

  @if($hasPast)
    <details class="partners-past">
      <summary>Geweest ({{ $pastEvents->count() }})</summary>
      <ul class="partners-event-list partners-event-list--past">
        @foreach($pastEvents as $event)
          <li class="partners-event soft-card">
            <p class="partners-event-meta">
              <span class="badge">{{ $typeLabels[$event->type] ?? $event->type }}</span>
              <time datetime="{{ $event->start_datum->format('Y-m-d') }}">{{ $evenementen->formatDateRange($event) }}</time>
            </p>
            <h4 class="partners-event-title">
              @if($event->url !== '')
                <a href="{{ $event->url }}" rel="noopener noreferrer" target="_blank">{{ $event->titel }}</a>
              @else
                {{ $event->titel }}
              @endif
            </h4>
            <p>{{ $event->plaats }} · {{ $event->provincie }}</p>
            @if($event->organisator !== '')
              <p class="muted">Organisatie: {{ $event->organisator }}</p>
            @endif
          </li>
        @endforeach
      </ul>
    </details>
  @endif

  <h2 id="vaste-initiatieven">Vaste ruilinitiatieven</h2>
  <p class="section-intro">Initiatieven zonder vaste datum waar ik naar link: plantenasiels, plantenbiebs, ruilkasten, voedselbossen en meer. De teksten zijn kort en feitelijk, gebaseerd op hun eigen websites.</p>

  <div class="stack partners-stack">
    <article class="soft-card">
      <h3><a href="https://voedselbosbouw.org/" rel="noopener noreferrer">Voedselbosbouw.org</a></h3>
      <p>Stichting Voedselbosbouw Nederland begeleidt boeren en grondeigenaren bij agrarische voedselbossen, deelt kennis en lobbyt voor deze vorm van landbouw. Ik richt me op stekjes van mens tot mens; zij op het bredere voedselbos-verhaal.</p>
      <p class="muted">Niet te bezoeken via mijn kaart; zie hun eigen site.</p>
    </article>
    <article class="soft-card">
      <h3><a href="https://meerbomen.nu/" rel="noopener noreferrer">MeerBomen Nu</a></h3>
      <p>Landelijk initiatief dat kansarme zaailingen redt en gratis tweede-kansbomen en -struiken uitdeelt via bomenhubs en vrijwilligers. Het verschil met mij: zij doen bomen en acties, ik stekjes van mens tot mens.</p>
      <p class="muted">Niet te bezoeken via mijn kaart.</p>
    </article>
  </div>

  <h3>Andere links</h3>
  <p class="section-intro">Losse verwijzingen naar verwante plekken en netwerken.</p>
  <div class="grid-cards partners-links">
    <article class="soft-card">
      <h3><a href="https://velt.nu/zaden-en-plantenruilbeurzen" rel="noopener noreferrer">Velt</a></h3>
      <p>Velt-groepen organiseren zaden- en plantenruilbeurzen in Nederland en België: delen van zaden en planten, zonder dat er geld aan te pas hoeft te komen.</p>
    </article>
    <article class="soft-card">
      <h3><a href="https://zadenbibliotheeknederland.nl/" rel="noopener noreferrer">Zadenbibliotheek Nederland</a></h3>
      <p>Netwerk van zadenbibliotheken waar je zaden kosteloos kunt lenen, ruilen en inbrengen. Lokale collecties verschillen per locatie.</p>
      <p class="muted">Zaden; ik doe stekjes.</p>
    </article>
    <article class="soft-card">
      <h3><a href="https://voedseluithetbos.nl/" rel="noopener noreferrer">Voedsel uit het Bos</a></h3>
      <p>Vereniging gericht op opleidingen, kennisbank, podcast en community rond goed aangelegde en beheerde voedselbossen in Nederland.</p>
    </article>
    <article class="soft-card">
      <h3><a href="https://netwerkvoedselbosbouw.nl/" rel="noopener noreferrer">Netwerk Voedselbosbouw</a></h3>
      <p>Netwerk rond voedselbosprofessionals (boer, ontwerper, kweker, investeerder, beleid), voortgekomen uit de Green Deal Voedselbossen. Publiceert onder andere een voedselbossenkaart.</p>
    </article>
    <article class="soft-card">
      <h3><a href="https://www.permacultuurnederland.org/" rel="noopener noreferrer">Permacultuur Nederland</a></h3>
      <p>Website en kennisnetwerk over permacultuur in Nederland: eetbare tuinen, artikelen, cursusmateriaal en verwijzing naar de landelijke community.</p>
    </article>
    <article class="soft-card">
      <h3><a href="https://www.eetbaargoed.nl/" rel="noopener noreferrer">Eetbaargoed</a></h3>
      <p>Kwekerij van Arjan en Esther (Vaassen) met eetbare planten, fruitbomen en struiken voor voedselbossen en eetbare tuinen.</p>
    </article>
  </div>

  <p class="partners-kaart-hint">Bezoeklocaties (plantenasiel, plantenbieb, ruilkast, …) staan op <a href="{{ route('deelplekken') }}">deelplekken</a> en op de <a href="{{ route('aanbod') }}">aanbodkaart</a>. Meer over organisaties: <a href="{{ route('organisaties.index') }}">organisaties</a>. Zelf aanmelden: <a href="{{ route('voor-organisaties') }}">voor organisaties</a>.</p>
</section>
@endsection
