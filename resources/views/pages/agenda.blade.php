@extends('layouts.site')

@section('title', $title)
@section('meta_description', $metaDescription)
@section('canonical', $canonical)

@push('head')
  @vite(['resources/css/partners.css'])
@endpush

@section('content')
<section class="section wrap partners-page agenda-page">
  @include('partials.plekken-tabs')
  <h1 class="page-title">{{ $h1 }}</h1>
  <p class="section-intro">Stekjesmarkten, plantenruilbeurzen en ruildagen, per maand. Filter op provincie of type. Check altijd de link van de organisator voor actuele informatie.</p>


  <form class="form partners-agenda-filters" method="get" action="{{ route('agenda') }}" style="max-width:100%;margin:1rem 0">
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
        <a class="btn btn-secondary" href="{{ route('agenda') }}">Wis filters</a>
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
      <p>Geen evenementen voor deze filters. <a href="{{ route('agenda') }}">Toon alles</a></p>
    </div>
  @else
    <p class="muted partners-agenda-empty" role="status">Er staan nog geen evenementen gepland. Ken je er een? <a href="{{ route('feedback.create', ['page_path' => '/agenda']) }}">Meld het</a> via het feedbackformulier.</p>
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

  <p class="partners-kaart-hint">Vaste plekken zonder datum staan bij <a href="{{ route('deelplekken') }}">deelplekken</a>. Acties om aan mee te doen vind je bij <a href="{{ route('partners') }}">initiatieven</a>.</p>
</section>
@endsection
