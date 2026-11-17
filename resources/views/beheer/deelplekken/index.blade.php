@extends('layouts.site')

@section('title', $title)
@section('meta_description', 'Beheer')
@section('canonical', $canonical)

@push('head')
  @vite(['resources/css/admin.css'])
  <meta name="robots" content="noindex, nofollow">
@endpush

@section('content')
<section class="section wrap beheer-page">
  @include('beheer.partials.nav')

  <h1 class="page-title">Deelplekken</h1>
  <p class="muted">Verborgen plekken kun je hier definitief verwijderen. Zichtbare plekken blijven op de kaart staan.</p>

  <nav class="beheer-filters" aria-label="Filter deelplekken">
    <a href="{{ route('beheer.deelplekken.index', ['zichtbaar' => 'verborgen']) }}" @class(['is-actief' => $filter === 'verborgen'])>Alleen verborgen</a>
    <a href="{{ route('beheer.deelplekken.index', ['zichtbaar' => 'zichtbaar']) }}" @class(['is-actief' => $filter === 'zichtbaar'])>Alleen zichtbaar</a>
    <a href="{{ route('beheer.deelplekken.index', ['zichtbaar' => 'alle']) }}" @class(['is-actief' => $filter === 'alle'])>Alles</a>
  </nav>

  @if($plekken->isEmpty())
    <p class="muted">Geen deelplekken in dit filter.</p>
  @else
    <form method="post" action="{{ route('beheer.deelplekken.bulk-destroy.confirm') }}" class="beheer-deelplekken-form">
      @csrf
      <div class="beheer-bulk-balk">
        <button type="submit" class="btn btn-gevaar btn-klein" @disabled($filter === 'zichtbaar')>Selectie verwijderen</button>
        <span class="muted beheer-klein">Alleen verborgen plekken; daarna volgt een bevestiging.</span>
      </div>

      <div class="beheer-table-wrap">
        <table class="beheer-table beheer-deelplekken-table">
          <thead>
            <tr>
              <th class="beheer-check-col"><span class="visually-hidden">Selecteer</span></th>
              <th>Naam</th>
              <th>Type</th>
              <th>Plaats</th>
              <th>Status</th>
              <th></th>
            </tr>
          </thead>
          <tbody>
            @foreach($plekken as $plek)
              <tr>
                <td class="beheer-check-col" data-label="Selecteer">
                  @if(! $plek->visible)
                    <label class="beheer-check">
                      <input type="checkbox" name="ids[]" value="{{ $plek->id }}">
                      <span class="visually-hidden">{{ $plek->naam }}</span>
                    </label>
                  @endif
                </td>
                <td data-label="Naam">
                  <a href="{{ route('beheer.deelplekken.show', $plek) }}">{{ $plek->naam }}</a>
                  <div class="muted beheer-klein">{{ $plek->slug }}</div>
                </td>
                <td data-label="Type">{{ $typeLabels[$plek->type] ?? $plek->type }}</td>
                <td data-label="Plaats">{{ $plek->plaats }}</td>
                <td data-label="Status">
                  @if($plek->visible)
                    <span class="status status--ok">Zichtbaar</span>
                  @else
                    <span class="status status--uit">Verborgen</span>
                  @endif
                </td>
                <td class="beheer-actions">
                  <a href="{{ route('beheer.deelplekken.show', $plek) }}">Bekijken</a>
                  @if(! $plek->visible)
                    <a class="beheer-link-gevaar" href="{{ route('beheer.deelplekken.destroy.confirm', $plek) }}">Verwijderen</a>
                  @endif
                </td>
              </tr>
            @endforeach
          </tbody>
        </table>
      </div>
    </form>
  @endif
</section>
@endsection
