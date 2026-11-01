@extends('layouts.site')

@section('title', $title)
@section('meta_description', $metaDescription)
@section('canonical', $canonical)

@push('head')
  @vite(['resources/css/admin.css'])
  <meta name="robots" content="noindex, nofollow">
  <meta name="referrer" content="no-referrer">
@endpush

@section('content')
<section class="section wrap beheer-page beheer-gebruikers-page">
  <div class="beheer-header">
    <h1 class="page-title">Gebruikers</h1>
    <div class="beheer-actions">
      <a href="{{ route('beheer.index') }}">← Beheer</a>
      <a href="{{ route('beheer.statistieken') }}">Statistieken</a>
      <a href="{{ route('beheer.feedback') }}">Feedback</a>
      <form method="post" action="{{ route('beheer.logout') }}">
        @csrf
        <button class="btn btn-secondary" type="submit">Uitloggen</button>
      </form>
    </div>
  </div>

  <form class="form beheer-zoek" method="post" action="{{ route('beheer.gebruikers.zoek') }}" role="search">
    @csrf
    <label>Zoek op naam of e-mail
      <input type="search" name="zoek" value="{{ $zoek }}" maxlength="254" autocomplete="off">
    </label>
    <button class="btn" type="submit">Zoeken</button>
    @if($zoek !== '')
      <button class="btn btn-secondary" type="submit" name="wissen" value="1">Wissen</button>
    @endif
  </form>

  @error('zoek')
    <p class="error" role="alert">{{ $message }}</p>
  @enderror

  <p class="muted">
    {{ $users->total() }} {{ $users->total() === 1 ? 'account' : 'accounts' }}@if($zoek !== '') gevonden voor «{{ $zoek }}»@endif.
    Nieuwste eerst. Data in Europe/Amsterdam.
  </p>

  @if($rows->isEmpty())
    <div class="empty-state" role="status">
      <p>{{ $zoek !== '' ? 'Geen accounts gevonden.' : 'Nog geen accounts.' }}</p>
    </div>
  @else
    <div class="table-wrap">
      <table class="beheer-table beheer-gebruikers-table">
        <thead>
          <tr>
            <th>ID</th>
            <th>Naam</th>
            <th>E-mail</th>
            <th>Aangemeld</th>
            <th>Aanbod</th>
            <th>Badges</th>
          </tr>
        </thead>
        <tbody>
          @foreach($rows as $row)
            @php /** @var \App\Models\User $u */ $u = $row['user']; @endphp
            <tr>
              <td>{{ $u->id }}</td>
              <td>{{ $u->name }}@if(\App\Support\Naam::lijktOpEmail((string) $u->name, (string) $u->email)) <span class="muted" title="Wordt openbaar getoond als «Lid»">(e-mail als naam)</span>@endif</td>
              <td>{{ $u->email }}@unless($u->hasVerifiedEmail()) <span class="muted">(niet bevestigd)</span>@endunless</td>
              <td><time datetime="{{ $u->created_at?->toIso8601String() }}">{{ $u->created_at?->timezone('Europe/Amsterdam')->format('d-m-Y') }}</time></td>
              <td>{{ (int) ($u->aanbod_count ?? 0) }}</td>
              <td>{{ $row['badges'] === [] ? '–' : implode(', ', $row['badges']) }}</td>
            </tr>
          @endforeach
        </tbody>
      </table>
    </div>

    @if($users->lastPage() > 1)
      <nav class="beheer-paginering" aria-label="Pagina's">
        @if($users->previousPageUrl())
          <a href="{{ $users->previousPageUrl() }}">← Vorige</a>
        @endif
        <span>Pagina {{ $users->currentPage() }} van {{ $users->lastPage() }}</span>
        @if($users->nextPageUrl())
          <a href="{{ $users->nextPageUrl() }}">Volgende →</a>
        @endif
      </nav>
    @endif
  @endif
</section>
@endsection
