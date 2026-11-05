@extends('layouts.site')

@section('title', $title)
@section('meta_description', $metaDescription)
@section('canonical', $canonical)

@push('head')
  @vite(['resources/css/partners.css'])
@endpush

@section('content')
<section class="section wrap partners-page">
  @include('partials.plekken-tabs')
  <h1 class="page-title">{{ $h1 }}</h1>
  <p class="section-intro">Initiatieven en acties waar je aan mee kunt doen, plus een gids met organisaties rond gratis planten. Het zijn geen officiële partnerschappen, alleen doorverwijzingen naar plekken rond gratis of gedeeld groen. Check altijd de externe link voor actuele informatie.</p>

  @php
  $uitgelicht = app(\App\Services\UitgelichteInitiatieven::class)->all();
@endphp
  @if(count($uitgelicht))
    <h2 id="doe-mee">Doe mee</h2>
    <div class="partners-uitgelicht">
      @foreach($uitgelicht as $init)
        <article class="partners-uitgelicht-kaart" id="{{ $init['anker'] }}">
          <span class="badge partners-doe-mee">Doe mee</span>
          @if($init['afbeelding'])
            @include('partials.initiatief-foto', ['afbeelding' => $init['afbeelding']])
          @else
            @include('partials.illustraties.stek', ['naam' => $init['illustratie']])
          @endif
          @php
            $intern = collect($init['acties'])->firstWhere('extern', false);
            $titelUrl = $init['pagina_url'] ?? ($intern['url'] ?? null);
          @endphp
          <h3>@if($titelUrl)<a href="{{ $titelUrl }}">{{ $init['naam'] }}</a>@else{{ $init['naam'] }}@endif</h3>
          @if($init['organisatie'] !== '')<p class="muted">{{ $init['organisatie'] }}</p>@endif
          <p>{{ $init['tekst'] }}</p>
          <ul class="partners-uitgelicht-acties">
            @foreach($init['acties'] as $actie)
              <li><a href="{{ $actie['url'] }}" @if($actie['extern']) rel="noopener noreferrer" target="_blank" @endif>{{ $actie['label'] }}</a></li>
            @endforeach
          </ul>
        </article>
      @endforeach
    </div>
  @endif

  <h2 id="agenda">Agenda</h2>
  @if($volgende->isNotEmpty())
    <ul class="partners-event-list">
      @foreach($volgende as $event)
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
        </li>
      @endforeach
    </ul>
  @else
    <p class="muted">Er staan nu geen evenementen gepland.</p>
  @endif
  <p><a class="btn btn-secondary" href="{{ route('agenda') }}"><x-icon name="kalender" :size="16" /> Hele agenda met filters</a></p>

  <h2 id="organisaties">Organisaties en initiatieven</h2>
  <p class="section-intro">Organisaties rond gratis of gedeeld groen waar ik naar link. Geen officiële partnerschappen; check hun eigen site voor actuele informatie.</p>

  @if(count($soorten) > 1)
    <nav class="partners-soorten" aria-label="Filter op soort">
      <a href="{{ route('partners') }}#organisaties" @class(['dp-chip', 'is-actief' => $soort === '']) @if($soort === '') aria-current="true" @endif>Alles <span>{{ array_sum($tellingen) }}</span></a>
      @foreach($soorten as $key => $label)
        <a href="{{ route('partners', ['soort' => $key]) }}#organisaties" @class(['dp-chip', 'is-actief' => $soort === $key]) @if($soort === $key) aria-current="true" @endif>{{ $label }} <span>{{ $tellingen[$key] ?? 0 }}</span></a>
      @endforeach
    </nav>
  @endif

  <div class="grid-cards partners-gids">
    @foreach($gids as $org)
      <article class="soft-card">
        <h3><a href="{{ $org['url'] }}">{{ $org['naam'] }}</a></h3>
        <p class="muted">{{ $soortLabels[$org['type']] ?? $org['type'] }} · {{ $org['plaats'] }}</p>
        <p>{{ \Illuminate\Support\Str::limit((string) $org['kort'], 200) }}</p>
      </article>
    @endforeach
  </div>

  @if(count($verenigingen) > 0 && $soort === '')
    <h3 id="verenigingen">Verenigingen van leden</h3>
    <p>Volkstuinen, tuinverenigingen en buurttuinen waar leden van Gratis planten bij horen.</p>
    <ul>
      @foreach($verenigingen as $v)
        <li><a href="{{ $v->url() }}">{{ $v->naam }}</a> <span class="muted">· {{ $v->plaats }} · {{ $v->leden_count }} {{ $v->leden_count === 1 ? 'lid' : 'leden' }}</span></li>
      @endforeach
    </ul>
    <p class="muted">Lid van een volkstuin of tuinvereniging? <a href="{{ route('account.verenigingen') }}">Koppel je account</a>, dan krijgt je vereniging een eigen pagina.</p>
  @endif

  <p class="partners-kaart-hint">Bezoeklocaties (plantenasiel, plantenbieb, ruilkast, …) staan op <a href="{{ route('deelplekken') }}">deelplekken</a> en op de <a href="{{ route('aanbod') }}">aanbodkaart</a>. Zelf aanmelden: <a href="{{ route('voor-organisaties') }}">voor organisaties</a>.</p>
</section>
@endsection
