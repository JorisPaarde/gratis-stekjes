@extends('layouts.site')

@section('title', $title)
@section('meta_description', $metaDescription)
@section('canonical', $canonical)

@push('head')
  @vite(['resources/css/admin.css'])
  <meta name="robots" content="noindex, nofollow">
@endpush

@section('content')
<section class="section wrap beheer-page">
  <div class="beheer-header">
    <h1 class="page-title">Statistieken</h1>
    <div class="beheer-actions">
      <a href="{{ route('beheer.index') }}">← Beheer</a>
      <form method="post" action="{{ route('beheer.logout') }}">
        @csrf
        <button class="btn btn-secondary" type="submit">Uitloggen</button>
      </form>
    </div>
  </div>

  <p class="muted">Alleen aggregaten — geen namen, e-mailadressen of per-gebruikersrijen. {{ $roleDefinition }}</p>

  <h2>Accounts</h2>
  <ul class="stats-kpis">
    <li><span class="stats-kpi-value">{{ $signupsTotal }}</span> <span class="stats-kpi-label">aanmeldingen totaal</span></li>
    <li><span class="stats-kpi-value">{{ $confirmed }}</span> <span class="stats-kpi-label">bevestigd</span></li>
    <li><span class="stats-kpi-value">{{ $unconfirmed }}</span> <span class="stats-kpi-label">onbevestigd</span></li>
    <li><span class="stats-kpi-value">{{ $uitdelers }}</span> <span class="stats-kpi-label">uitdelers</span></li>
    <li><span class="stats-kpi-value">{{ $ophalers }}</span> <span class="stats-kpi-label">ophalers / alleen-account</span></li>
  </ul>

  <h2>Aanmeldingen per dag (30 dagen)</h2>
  <div class="beheer-table-wrap">
    <table class="beheer-table stats-day-table">
      <thead>
        <tr>
          <th>Datum</th>
          <th>Aantal</th>
          <th></th>
        </tr>
      </thead>
      <tbody>
        @foreach($signupsPerDay as $row)
          <tr>
            <td>{{ $row['label'] }}</td>
            <td>{{ $row['count'] }}</td>
            <td class="stats-bar-cell">
              <span class="stats-bar" style="--pct: {{ $signupsPerDayMax > 0 ? round(($row['count'] / $signupsPerDayMax) * 100) : 0 }}%" title="{{ $row['count'] }}"></span>
            </td>
          </tr>
        @endforeach
      </tbody>
    </table>
  </div>

  <h2>Aanbiedingen &amp; berichten</h2>
  <ul class="stats-kpis">
    <li><span class="stats-kpi-value">{{ $offersTotal }}</span> <span class="stats-kpi-label">aanbiedingen totaal</span></li>
    <li><span class="stats-kpi-value">{{ $offersActive }}</span> <span class="stats-kpi-label">actief (zichtbaar + niet verlopen)</span></li>
    <li><span class="stats-kpi-value">{{ $messagesTotal }}</span> <span class="stats-kpi-label">berichten</span></li>
  </ul>

  <h2>Bezoekers per pagina</h2>
  <p class="muted">Eigen teller: pad + datum + aantal. Geen cookies, geen IP, geen user-agent. Totaal laatste 14 dagen: {{ $pageViewsTotal }}.</p>

  <h3>Top pagina’s (14 dagen)</h3>
  @if($pageViewsTop->isEmpty())
    <p class="muted">Nog geen pageviews geregistreerd.</p>
  @else
    <div class="beheer-table-wrap">
      <table class="beheer-table">
        <thead>
          <tr>
            <th>Pad</th>
            <th>Views</th>
          </tr>
        </thead>
        <tbody>
          @foreach($pageViewsTop as $row)
            <tr>
              <td><code>{{ $row['path'] }}</code></td>
              <td>{{ $row['count'] }}</td>
            </tr>
          @endforeach
        </tbody>
      </table>
    </div>
  @endif

  <h3>Per dag (14 dagen)</h3>
  @foreach($pageViewsByDay as $day)
    <details class="stats-day-block" @if($day['total'] > 0) open @endif>
      <summary>{{ $day['label'] }} — {{ $day['total'] }} views</summary>
      @if($day['pages']->isEmpty())
        <p class="muted">Geen pageviews.</p>
      @else
        <div class="beheer-table-wrap">
          <table class="beheer-table">
            <thead>
              <tr>
                <th>Pad</th>
                <th>Views</th>
              </tr>
            </thead>
            <tbody>
              @foreach($day['pages'] as $page)
                <tr>
                  <td><code>{{ $page['path'] }}</code></td>
                  <td>{{ $page['count'] }}</td>
                </tr>
              @endforeach
            </tbody>
          </table>
        </div>
      @endif
    </details>
  @endforeach
</section>
@endsection
