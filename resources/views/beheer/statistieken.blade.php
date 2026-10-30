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
      <p class="stats-subtitle muted">Alleen aggregaten — geen namen of e-mailadressen.</p>
    </div>
    <div class="beheer-actions">
      <a href="{{ route('beheer.index') }}">← Beheer</a>
      <form method="post" action="{{ route('beheer.logout') }}">
        @csrf
        <button class="btn btn-secondary" type="submit">Uitloggen</button>
      </form>
    </div>
  </div>

  <details class="stats-panel stats-panel--overview" open>
    <summary class="stats-panel-summary">
      <span class="stats-panel-title">Overzicht · 30 dagen</span>
      <span class="stats-panel-hint muted">aanmeldingen + bezoekers</span>
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
            <span class="stats-kpi-value">{{ $pageViewsToday }}</span>
            @if($svgViewsSpark !== ''){!! $svgViewsSpark !!}@endif
          </span>
          <span class="stats-kpi-label">bezoekers vandaag</span>
        </summary>
        <div class="stats-kpi-expand">
          {!! $svgViews !!}
          <p class="stats-kpi-caption">Top pagina’s · 14 dagen ({{ $pageViewsTotal }} views)</p>
          {!! $svgTopPages !!}
        </div>
      </details>
    </li>
  </ul>

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
