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
  <div class="beheer-header">
    <div class="stats-heading">
      <h1 class="page-title">Statistieken</h1>
      <p class="stats-subtitle muted">Aggregaten van accounts, aanbod, berichten en pageviews — geen namen of e-mailadressen.</p>
    </div>
    <div class="beheer-actions">
      <a href="{{ route('beheer.index') }}">← Beheer</a>
      <form method="post" action="{{ route('beheer.logout') }}">
        @csrf
        <button class="btn btn-secondary" type="submit">Uitloggen</button>
      </form>
    </div>
  </div>

  <p class="muted stats-role-note">{{ $roleDefinition }}</p>

  <h2 class="stats-section-title">Overzicht</h2>
  <ul class="stats-kpi-grid">
    <li class="stats-kpi-card">
      <span class="stats-kpi-value">{{ $signupsTotal }}</span>
      <span class="stats-kpi-label">aanmeldingen totaal</span>
    </li>
    <li class="stats-kpi-card">
      <span class="stats-kpi-value">{{ $confirmed }} <span class="stats-kpi-sep">/</span> {{ $unconfirmed }}</span>
      <span class="stats-kpi-label">bevestigd / onbevestigd</span>
    </li>
    <li class="stats-kpi-card">
      <span class="stats-kpi-value">{{ $uitdelers }} <span class="stats-kpi-sep">/</span> {{ $ophalers }}</span>
      <span class="stats-kpi-label">uitdelers / ophalers</span>
    </li>
    <li class="stats-kpi-card">
      <span class="stats-kpi-value">{{ $offersTotal }} <span class="stats-kpi-sep">/</span> {{ $offersActive }}</span>
      <span class="stats-kpi-label">aanbiedingen (totaal / actief)</span>
    </li>
    <li class="stats-kpi-card">
      <span class="stats-kpi-value">{{ $messagesTotal }}</span>
      <span class="stats-kpi-label">berichten</span>
    </li>
    <li class="stats-kpi-card">
      <span class="stats-kpi-value">{{ $pageViewsToday }}</span>
      <span class="stats-kpi-label">bezoekers vandaag</span>
    </li>
  </ul>

  <h2 class="stats-section-title">Aanmeldingen per dag (30 dagen)</h2>
  <div class="stats-chart" role="img" aria-label="Staafdiagram aanmeldingen per dag, laatste 30 dagen">
    <div class="stats-chart-bars" style="--chart-max: {{ max(1, $signupsPerDayMax) }}">
      @foreach($signupsPerDay as $row)
        @php
          $pct = $signupsPerDayMax > 0 ? round(($row['count'] / $signupsPerDayMax) * 100) : 0;
        @endphp
        <div class="stats-chart-col" title="{{ $row['label'] }}: {{ $row['count'] }}">
          <span class="stats-chart-value">{{ $row['count'] > 0 ? $row['count'] : '' }}</span>
          <span class="stats-chart-track" aria-hidden="true">
            <span class="stats-chart-bar" style="height: {{ $pct }}%"></span>
          </span>
          <span class="stats-chart-label">{{ $row['label'] }}</span>
        </div>
      @endforeach
    </div>
  </div>

  <h2 class="stats-section-title">Pageviews per dag (14 dagen)</h2>
  <div class="stats-chart stats-chart--views" role="img" aria-label="Staafdiagram pageviews per dag, laatste 14 dagen">
    <div class="stats-chart-bars" style="--chart-max: {{ max(1, $pageViewsPerDayMax) }}">
      @foreach($pageViewsPerDay as $row)
        @php
          $pct = $pageViewsPerDayMax > 0 ? round(($row['total'] / $pageViewsPerDayMax) * 100) : 0;
        @endphp
        <div class="stats-chart-col" title="{{ $row['label'] }}: {{ $row['total'] }}">
          <span class="stats-chart-value">{{ $row['total'] > 0 ? $row['total'] : '' }}</span>
          <span class="stats-chart-track" aria-hidden="true">
            <span class="stats-chart-bar" style="height: {{ $pct }}%"></span>
          </span>
          <span class="stats-chart-label">{{ $row['shortLabel'] }}</span>
        </div>
      @endforeach
    </div>
  </div>

  <h2 class="stats-section-title">Bezoekers per pagina</h2>
  <p class="muted">Eigen teller: pad + datum + aantal. Geen cookies, geen IP, geen user-agent. Totaal laatste 14 dagen: {{ $pageViewsTotal }}.</p>

  <h3 class="stats-subsection-title">Top pagina’s (14 dagen)</h3>
  @if($pageViewsTop->isEmpty())
    <p class="muted">Nog geen pageviews geregistreerd.</p>
  @else
    <div class="beheer-table-wrap">
      <table class="beheer-table stats-table">
        <thead>
          <tr>
            <th>Pad</th>
            <th class="stats-num">Views</th>
          </tr>
        </thead>
        <tbody>
          @foreach($pageViewsTop as $row)
            <tr>
              <td><code>{{ $row['path'] }}</code></td>
              <td class="stats-num">{{ $row['count'] }}</td>
            </tr>
          @endforeach
        </tbody>
      </table>
    </div>
  @endif

  <h3 class="stats-subsection-title">Per dag (14 dagen)</h3>
  <div class="stats-day-list">
    @foreach($pageViewsByDay as $day)
      <details class="stats-day-block" @if($day['total'] > 0) open @endif>
        <summary>{{ $day['label'] }} — {{ $day['total'] }} views</summary>
        @if($day['pages']->isEmpty())
          <p class="muted">Geen pageviews.</p>
        @else
          <div class="beheer-table-wrap">
            <table class="beheer-table stats-table">
              <thead>
                <tr>
                  <th>Pad</th>
                  <th class="stats-num">Views</th>
                </tr>
              </thead>
              <tbody>
                @foreach($day['pages'] as $page)
                  <tr>
                    <td><code>{{ $page['path'] }}</code></td>
                    <td class="stats-num">{{ $page['count'] }}</td>
                  </tr>
                @endforeach
              </tbody>
            </table>
          </div>
        @endif
      </details>
    @endforeach
  </div>
</section>
@endsection
