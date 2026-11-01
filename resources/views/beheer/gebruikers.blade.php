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

  <div class="beheer-titelrij">
    <h1 class="page-title">Gebruikers</h1>
    @if($onbevestigdOud > 0)
      <form method="post" action="{{ route('beheer.gebruikers.herinner') }}" onsubmit="return confirm('Bevestigingsmail opnieuw sturen naar {{ min(25, $onbevestigdOud) }} onbevestigde leden?');">
        @csrf
        <button class="btn btn-secondary btn-klein" type="submit">Herinner {{ min(25, $onbevestigdOud) }} onbevestigde {{ $onbevestigdOud === 1 ? 'lid' : 'leden' }}</button>
      </form>
    @endif
  </div>

  {{-- Zoekterm via POST naar de sessie: geen e-mailadres in URL, logs of geschiedenis. --}}
  <form class="beheer-zoek" method="post" action="{{ route('beheer.gebruikers.zoek') }}">
    @csrf
    <input type="hidden" name="filter" value="{{ $filter }}">
    <label class="visually-hidden" for="beheer-q">Zoek</label>
    <input id="beheer-q" type="search" name="zoek" value="{{ $q }}" placeholder="Zoek op naam, e-mail, plaats of ID" autocomplete="off">
    <label class="visually-hidden" for="beheer-sort">Sorteer</label>
    <select id="beheer-sort" name="sort" onchange="this.form.submit()">
      @foreach($sorteringen as $key => $label)
        <option value="{{ $key }}" @selected($sort === $key)>{{ $label }}</option>
      @endforeach
    </select>
    <button class="btn btn-klein" type="submit">Zoek</button>
    @if($q !== '')
      <button class="linkish" type="submit" name="wissen" value="1">Wis zoekterm</button>
    @endif
  </form>

  <nav class="beheer-filters" aria-label="Filter">
    @foreach($filters as $key => $label)
      <a href="{{ route('beheer.gebruikers', array_filter(['filter' => $key === 'alle' ? null : $key, 'sort' => $sort === 'nieuw' ? null : $sort])) }}" @class(['filter-chip', 'is-actief' => $filter === $key])>{{ $label }}</a>
    @endforeach
  </nav>

  <p class="muted">{{ $gebruikers->total() }} {{ $gebruikers->total() === 1 ? 'gebruiker' : 'gebruikers' }}@if($q !== '') voor «{{ $q }}»@endif.</p>

  @if($gebruikers->isEmpty())
    <p class="beheer-alles-goed">Geen gebruikers die hierbij passen.</p>
  @else
    <div class="beheer-table-wrap">
      <table class="beheer-table beheer-gebruikers">
        <thead>
          <tr>
            <th>Naam</th>
            <th>Plaats</th>
            <th>Status</th>
            <th>Lid sinds</th>
            <th>Laatst actief</th>
            <th>Aanbod</th>
            <th>Gesprekken</th>
            <th>Laatste mail</th>
            <th><span class="visually-hidden">Acties</span></th>
          </tr>
        </thead>
        <tbody>
          @foreach($gebruikers as $u)
            @php
              $mail = $laatsteMail[$u->id] ?? null;
              $mailFout = $mail && ! $mail->isOk() && $mail->created_at?->gte(now()->subDays(7));
            @endphp
            <tr>
              <td data-label="Naam">
                <a class="beheer-naam" href="{{ route('beheer.gebruikers.show', $u) }}">{{ $u->name }}</a>
                @if($pionierId === (int) $u->id)<img src="/assets/badges/pionier.svg" alt="Pionier" title="Pionier" width="20" height="21" class="beheer-icoon">@endif
                @if(\App\Support\Naam::lijktOpEmail((string) $u->name))<span class="status status--wacht" title="Wordt openbaar als «Lid» getoond">e-mail als naam</span>@endif
                <span class="muted beheer-id">#{{ $u->id }}</span>
                <br><span class="muted beheer-klein beheer-email">{{ $u->email }}</span>
                @if(!empty($badges[$u->id]))
                  <span class="beheer-badge-rij" aria-label="Badges: {{ collect($badges[$u->id])->pluck('label')->implode(', ') }}">
                    @foreach($badges[$u->id] as $b)<img src="{{ $b['img'] }}" alt="" title="{{ $b['label'] }}" width="18" height="18">@endforeach
                  </span>
                @endif
              </td>
              <td data-label="Plaats">{{ $u->locatie_label ?: '—' }}</td>
              <td data-label="Status">@include('beheer.partials.status', ['u' => $u, 'mailFout' => $mailFout])</td>
              <td data-label="Lid sinds" class="beheer-datum"><span title="{{ $u->created_at?->diffForHumans() }}">{{ $u->created_at?->timezone('Europe/Amsterdam')->translatedFormat('j M Y') }}</span></td>
              <td data-label="Laatst actief" class="beheer-datum">{{ $u->last_seen_at ? $u->last_seen_at->diffForHumans(['parts' => 1, 'short' => true]) : '—' }}</td>
              <td data-label="Aanbod">{{ $u->aanbod_actief }} <span class="muted">/ {{ $u->aanbod_totaal }}</span></td>
              <td data-label="Gesprekken">{{ $gesprekken[$u->id] ?? 0 }}</td>
              <td data-label="Laatste mail">
                @if($mail)
                  {{ \App\Support\MailLabels::type($mail->type) }}
                  @if(! $mail->isOk())<span class="status status--fout">{{ \App\Support\MailLabels::fout($mail->error) }}</span>@endif
                  <br><span class="muted beheer-klein">{{ $mail->created_at?->diffForHumans(['parts' => 1]) }}</span>
                @else
                  <span class="muted">—</span>
                @endif
              </td>
              <td class="beheer-acties-cel"><div class="beheer-acties-inner">
                @if(! $u->hasVerifiedEmail() && $u->active)
                  <form method="post" action="{{ route('beheer.users.resend-verification') }}">
                    @csrf
                    <input type="hidden" name="user_id" value="{{ $u->id }}">
                    <button type="submit" class="linkish" title="Bevestigingsmail opnieuw sturen">Herstuur</button>
                  </form>
                @endif
                <a href="{{ route('beheer.gebruikers.show', $u) }}">Bekijk</a>
              </div></td>
            </tr>
          @endforeach
        </tbody>
      </table>
    </div>
    {{ $gebruikers->onEachSide(1)->links('beheer.partials.paginatie') }}
  @endif
</section>
@endsection
