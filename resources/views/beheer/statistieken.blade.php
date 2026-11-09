@extends('layouts.site')

@section('title', $title)
@section('meta_description', $metaDescription)
@section('canonical', $canonical)

@push('head')
  @vite(['resources/css/admin.css'])
  <meta name="robots" content="noindex, nofollow">
@endpush

@section('content')
<section class="section wrap beheer-page stats-page">
  @include('beheer.partials.nav')

  <h1 class="page-title">Statistieken</h1>

  <details class="stats-panel stats-panel--overview" open>
    <summary class="stats-panel-summary">
      <span class="stats-panel-title">Overzicht · 30 dagen</span>
      <span class="stats-panel-hint muted">aanmeldingen + pageviews</span>
    </summary>
    <div class="stats-panel-body">
      {!! $svgOverview !!}
    </div>
  </details>

  <ul class="stats-kpi-grid">
    <li>
      <details class="stats-kpi-card">
        <summary>
          <span class="stats-kpi-top">
            <span class="stats-kpi-value">{{ $leden }}</span>
            @if($svgSignupSpark !== ''){!! $svgSignupSpark !!}@endif
          </span>
          <span class="stats-kpi-label">Leden</span>
        </summary>
        <div class="stats-kpi-expand">
          {!! $svgSignups !!}
        </div>
      </details>
    </li>

    <li>
      <details class="stats-kpi-card">
        <summary>
          <span class="stats-kpi-top">
            <span class="stats-kpi-value">{{ $confirmed }} <span class="stats-kpi-sep">/</span> {{ $unconfirmed }}</span>
          </span>
          <span class="stats-kpi-label">bevestigd / onbevestigd</span>
        </summary>
        <div class="stats-kpi-expand">
          {!! $svgConfirmed !!}
        </div>
      </details>
    </li>

    <li>
      <details class="stats-kpi-card">
        <summary>
          <span class="stats-kpi-top">
            <span class="stats-kpi-value">{{ $struikrovers }}</span>
          </span>
          <span class="stats-kpi-label">Struikrovers</span>
        </summary>
        <div class="stats-kpi-expand">
          <p class="stats-kpi-caption">{{ $struikrovers }} van {{ $leden }} leden heeft «Ik ben Struikrover» aangevinkt.</p>
        </div>
      </details>
    </li>

    <li>
      <details class="stats-kpi-card">
        <summary>
          <span class="stats-kpi-top">
            <span class="stats-kpi-value">{{ $stekchallenge }}</span>
          </span>
          <span class="stats-kpi-label">STEKchallenge</span>
        </summary>
        <div class="stats-kpi-expand">
          <p class="stats-kpi-caption">{{ $stekchallenge }} van {{ $leden }} leden heeft «Ik doe mee aan de STEKchallenge» aangevinkt.</p>
        </div>
      </details>
    </li>

    <li>
      <details class="stats-kpi-card">
        <summary>
          <span class="stats-kpi-top">
            <span class="stats-kpi-value">{{ $ledenMetAanbod }}</span>
          </span>
          <span class="stats-kpi-label">Leden die iets aangeboden hebben</span>
        </summary>
        <div class="stats-kpi-expand">
          <p class="stats-kpi-caption">{{ $ledenMetAanbod }} van {{ $leden }} leden heeft iets aangeboden.</p>
          {!! $svgLedenProgress !!}
          {!! $svgLedenAanbod !!}
        </div>
      </details>
    </li>

    <li>
      <details class="stats-kpi-card">
        <summary>
          <span class="stats-kpi-top">
            <span class="stats-kpi-value">{{ $offersTotal }} <span class="stats-kpi-sep">/</span> {{ $offersActive }}</span>
            @if($svgOfferSpark !== ''){!! $svgOfferSpark !!}@endif
          </span>
          <span class="stats-kpi-label">aanbod (totaal / actief)</span>
        </summary>
        <div class="stats-kpi-expand">
          {!! $svgOffers !!}
          <p class="stats-kpi-caption">Per type (listing, niet account)</p>
          {!! $svgOffersType !!}
        </div>
      </details>
    </li>

    <li>
      <details class="stats-kpi-card">
        <summary>
          <span class="stats-kpi-top">
            <span class="stats-kpi-value">{{ $messagesTotal }}</span>
            @if($svgMessageSpark !== ''){!! $svgMessageSpark !!}@endif
          </span>
          <span class="stats-kpi-label">berichten</span>
        </summary>
        <div class="stats-kpi-expand">
          {!! $svgMessages !!}
        </div>
      </details>
    </li>

    <li>
      <details class="stats-kpi-card">
        <summary>
          <span class="stats-kpi-top">
            <span class="stats-kpi-value{{ $feedbackUnread > 0 ? ' stats-kpi-value--let-op' : '' }}">{{ $feedbackUnread }}</span>
            @if($svgFeedbackSpark !== ''){!! $svgFeedbackSpark !!}@endif
          </span>
          <span class="stats-kpi-label">
            @can('see-admin-dashboard')
              <a href="{{ route('beheer.feedback') }}">Feedback ongelezen</a>
            @else
              Feedback ongelezen
            @endcan
            · {{ $feedbackTotal }} totaal
          </span>
        </summary>
        <div class="stats-kpi-expand">
          <p class="stats-kpi-caption">
            @can('see-admin-dashboard')
              <a href="{{ route('beheer.feedback') }}">Bekijk alle feedback</a> —
            @endcan
            {{ $feedbackTotal }} totaal, {{ $feedbackUnread }} ongelezen.
          </p>
          {!! $svgFeedback !!}
        </div>
      </details>
    </li>

    <li>
      <details class="stats-kpi-card">
        <summary>
          <span class="stats-kpi-top">
            <span class="stats-kpi-value">{{ $pageViewsToday }}</span>
            @if($svgViewsSpark !== ''){!! $svgViewsSpark !!}@endif
          </span>
          <span class="stats-kpi-label">pageviews vandaag</span>
        </summary>
        <div class="stats-kpi-expand">
          {!! $svgViews !!}
          <p class="stats-kpi-caption">Top pagina’s · 14 dagen ({{ $pageViewsTotal }} views)</p>
          {!! $svgTopPages !!}
        </div>
      </details>
    </li>
  </ul>

  <details class="stats-panel" open>
    <summary class="stats-panel-summary">
      <span class="stats-panel-title">Trechter · per week</span>
      <span class="stats-panel-hint muted">van klik tot eerste aanbod</span>
    </summary>
    <div class="stats-panel-body">
      <p class="muted stats-funnel-intro">Unieke bezoekers zijn een schatting (daghash). Percentages tonen we pas vanaf {{ $minFormStarts }} formulierstarts in die week; daaronder staat «te weinig data» omdat de percentages nog niet betrouwbaar zijn.</p>

      <ul class="stats-funnel-legend" aria-label="Trechterstappen">
        @foreach($funnelLegendLabels as $key => $legendLabel)
          <li>
            <span class="stats-funnel-legend-icon" aria-hidden="true">
              <x-icon :name="$funnelIcons[$key]" :size="16" />
            </span>
            <span>{{ $legendLabel }}</span>
          </li>
        @endforeach
      </ul>

      <div class="stats-funnel-weeks">
        @foreach($funnelWeeks as $week)
          @php
            $firstOfferCount = (int) ($week['steps'][\App\Models\ConversionEvent::TYPE_FIRST_OFFER] ?? 0);
            $ctaCount = (int) ($week['steps'][\App\Models\ConversionEvent::TYPE_CTA_CLICK] ?? 0);
          @endphp
          <details
            class="stats-funnel-week-block"
            data-week-key="{{ $week['week_key'] }}"
            @if(! empty($week['open_default'])) open @endif
          >
            <summary class="stats-funnel-week-summary">
              <span class="stats-funnel-week-label">{{ $week['label'] }}</span>
              <span class="stats-funnel-week-peek">
                <span
                  class="stats-funnel-week-peek-nums"
                  title="bezoekers → klik → eerste aanbod"
                >{{ (int) $week['unique_visitors'] }} → {{ $ctaCount }} → {{ $firstOfferCount }}</span>
                @if($week['too_few'])
                  <span class="stats-funnel-week-peek-sparse hint">te weinig data</span>
                @endif
              </span>
            </summary>
            <div class="stats-funnel-week-body">
              @if($week['too_few'])
                <p class="stats-funnel-sparse hint">Te weinig data: minder dan {{ $minFormStarts }} formulierstarts deze week, percentages nog niet betrouwbaar.</p>
              @endif
              <ul class="stats-funnel-steps">
                @foreach($funnelLabels as $key => $label)
                  @php
                    $stepCount = $key === 'unique_visitors'
                      ? (int) $week['unique_visitors']
                      : (int) ($week['steps'][$key] ?? 0);
                    $stepRate = $week['rates'][$key] ?? null;
                    $ariaParts = [$label, (string) $stepCount];
                    if (! $week['too_few'] && $stepRate !== null) {
                        $ariaParts[] = $stepRate.'%';
                    }
                    $ariaLabel = implode(', ', $ariaParts);
                  @endphp
                  <li
                    class="stats-funnel-cell"
                    title="{{ $ariaLabel }}"
                    aria-label="{{ $ariaLabel }}"
                  >
                    <span class="stats-funnel-cell-icon" aria-hidden="true">
                      <x-icon :name="$funnelIcons[$key]" :size="18" />
                    </span>
                    <span class="stats-funnel-step-value">{{ $stepCount }}</span>
                    @if(! $week['too_few'] && $stepRate !== null)
                      <span class="hint stats-funnel-step-rate" aria-hidden="true">{{ $stepRate }}%</span>
                    @endif
                  </li>
                @endforeach
              </ul>
            </div>
          </details>
        @endforeach
      </div>
    </div>
  </details>

  <details class="stats-panel" open>
    <summary class="stats-panel-summary">
      <span class="stats-panel-title">Aanbodzoeken · deze week</span>
      <span class="stats-panel-hint muted">geen plaats- of vrije planttekst</span>
    </summary>
    <div class="stats-panel-body">
      <p class="muted">Week {{ $aanbodSearchWeek['label'] }}. Hoofdtelling = zelf gezocht (formulier). Auto = SEO/plant_filter bij paginalaad. Per zoekactie: modus, typefilter, resultaatgrootte en catalogusplant bij exacte match — nooit de getypte postcode/plaats of onbekende planttekst.</p>
      @if(($aanbodSearchWeek['total'] ?? 0) === 0 && ($aanbodSearchWeek['auto_count'] ?? 0) === 0)
        <p class="muted">Nog geen zoekacties gemeten deze week.</p>
      @else
        <ul class="stats-path-list">
          <li>Zoekacties (zelf) <span>{{ $aanbodSearchWeek['total'] }}</span></li>
          <li>Auto (SEO/paginalaad) <span>{{ $aanbodSearchWeek['auto_count'] ?? 0 }}</span></li>
          <li>Lege resultaten (zelf)
            <span>
              {{ $aanbodSearchWeek['empty_count'] }}
              @if($aanbodSearchWeek['empty_pct'] !== null)
                ({{ $aanbodSearchWeek['empty_pct'] }}%)
              @endif
            </span>
          </li>
          <li>Modus locatie <span>{{ $aanbodSearchWeek['modus']['locatie'] }}</span></li>
          <li>Modus plant <span>{{ $aanbodSearchWeek['modus']['plant'] }}</span></li>
          <li>Modus beide <span>{{ $aanbodSearchWeek['modus']['beide'] }}</span></li>
        </ul>
      @endif

      <h2 class="stats-subhead">Top 10 gezochte planten</h2>
      @if($aanbodSearchPlants === [])
        <p class="muted">Nog geen catalogusmatches deze week.</p>
      @else
        <table class="beheer-table">
          <thead>
            <tr><th>Plant</th><th>Zoekacties</th><th>Lege resultaten</th></tr>
          </thead>
          <tbody>
            @foreach($aanbodSearchPlants as $row)
              <tr>
                <td>{{ $row['naam'] }}</td>
                <td>{{ $row['count'] }}</td>
                <td>
                  {{ $row['empty_count'] }}
                  @if($row['empty_pct'] !== null)
                    <span class="hint">({{ $row['empty_pct'] }}%)</span>
                  @endif
                </td>
              </tr>
            @endforeach
          </tbody>
        </table>
      @endif
    </div>
  </details>

  <details class="stats-panel">
    <summary class="stats-panel-summary">
      <span class="stats-panel-title">Validatiefouten · 30 dagen</span>
      <span class="stats-panel-hint muted">alleen veld + foutcode</span>
    </summary>
    <div class="stats-panel-body">
      @if($validationErrors === [])
        <p class="muted">Nog geen validatiefouten gemeten.</p>
      @else
        <table class="beheer-table">
          <thead>
            <tr><th>Veld</th><th>Foutcode</th><th>Aantal</th></tr>
          </thead>
          <tbody>
            @foreach($validationErrors as $row)
              <tr>
                <td><code>{{ $row['field_name'] }}</code></td>
                <td><code>{{ $row['error_code'] }}</code></td>
                <td>{{ $row['count'] }}</td>
              </tr>
            @endforeach
          </tbody>
        </table>
      @endif
    </div>
  </details>

  <details class="stats-panel">
    <summary class="stats-panel-summary">
      <span class="stats-panel-title">Apparaat en bron · 30 dagen</span>
    </summary>
    <div class="stats-panel-body stats-breakdown">
      <div>
        <h2 class="stats-subhead">Apparaat (registratiepagina)</h2>
        @if($deviceBreakdown === [])
          <p class="muted">Nog geen data.</p>
        @else
          <ul class="stats-path-list">
            @foreach($deviceBreakdown as $device => $count)
              <li><code>{{ $device }}</code> <span>{{ $count }}</span></li>
            @endforeach
          </ul>
        @endif
      </div>
      <div>
        <h2 class="stats-subhead">Bron (accounts, utm_source)</h2>
        @if($sourceBreakdown === [])
          <p class="muted">Nog geen data.</p>
        @else
          <ul class="stats-path-list">
            @foreach($sourceBreakdown as $src => $count)
              <li><code>{{ $src }}</code> <span>{{ $count }}</span></li>
            @endforeach
          </ul>
        @endif
      </div>
    </div>
  </details>

  <details class="stats-panel">
    <summary class="stats-panel-summary">
      <span class="stats-panel-title">Per dag · pageviews</span>
      <span class="stats-panel-hint muted">alleen bij uitklappen</span>
    </summary>
    <div class="stats-panel-body stats-day-list">
      @forelse($pageViewsByDay as $day)
        <details class="stats-day-block">
          <summary>{{ $day['label'] }} — {{ $day['total'] }}</summary>
          @if($day['pages']->isEmpty())
            <p class="muted">Geen pageviews.</p>
          @else
            <ul class="stats-path-list">
              @foreach($day['pages'] as $page)
                <li><code>{{ $page['path'] }}</code> <span>{{ $page['count'] }}</span></li>
              @endforeach
            </ul>
          @endif
        </details>
      @empty
        <p class="muted">Nog geen pageviews.</p>
      @endforelse
    </div>
  </details>
</section>
@endsection
