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

  <p><a href="{{ route('beheer.gebruikers') }}">← Alle gebruikers</a></p>
  <div class="beheer-titelrij">
    <h1 class="page-title">{{ $u->name }}</h1>
    @if($isPionier)<img src="/assets/badges/pionier.svg" alt="Pionier" title="Pionier" width="48" height="50">@endif
  </div>
  <p>@include('beheer.partials.status', ['u' => $u, 'mailFout' => $mails->first() && ! $mails->first()->isOk()]) <span class="muted">· #{{ $u->id }}</span></p>

  <div class="beheer-twee">
    <div class="soft-card">
      <h2>Profiel</h2>
      <dl class="beheer-dl">
        <dt>E-mail</dt><dd>{{ $u->email }}@if($u->pending_email) <span class="muted">(wijziging naar {{ $u->pending_email }} wacht)</span>@endif</dd>
        <dt>Plaats</dt><dd>{{ $u->locatie_label ?: '—' }}{{ $u->country === 'BE' ? ' (België)' : '' }}</dd>
        <dt>Adres ingevuld</dt><dd>{{ $u->volledigAdres() !== null ? 'ja' : 'nee' }}{{ $u->volledigAdres() !== null ? ($u->adres_delen !== false ? ' · wordt gedeeld na wederzijds ja' : ' · delen staat uit') : '' }}</dd>
        <dt>Telefoon ingevuld</dt><dd>{{ trim((string) ($u->isUnreadable('telefoon') ? '' : $u->telefoon)) !== '' ? 'ja' : 'nee' }}</dd>
        <dt>Lid sinds</dt><dd>{{ $u->created_at?->timezone('Europe/Amsterdam')->format('d-m-Y') }} <span class="muted">({{ $u->created_at?->diffForHumans() }})</span></dd>
        <dt>Bevestigd</dt><dd>{{ $u->email_verified_at ? $u->email_verified_at->timezone('Europe/Amsterdam')->format('d-m-Y') : 'nee' }}</dd>
        <dt>Laatst actief</dt><dd>{{ $u->last_seen_at?->diffForHumans() ?? 'onbekend' }}</dd>
        <dt>Berichtmail</dt><dd>{{ $u->berichtmail !== false ? 'aan' : 'uit' }}</dd>
        <dt>Gesprekken</dt><dd>{{ $gesprekken }} <span class="muted">({{ $overdrachten }} overdracht{{ $overdrachten === 1 ? '' : 'en' }} afgerond)</span></dd>
        <dt>Verenigingen</dt>
        <dd>
          @forelse($verenigingen as $v)
            {{ $v->naam }}<span class="muted"> ({{ $v->pivot->zichtbaar ? 'zichtbaar' : 'niet zichtbaar' }}{{ $v->isApproved() ? '' : ', wacht' }})</span>@if(! $loop->last), @endif
          @empty
            —
          @endforelse
        </dd>
      </dl>
      <p class="muted">Inhoud van berichten zie je hier niet.</p>
    </div>

    <div class="soft-card">
      <h2>Badges</h2>
      <ul class="beheer-badges">
        @foreach($badges as $b)
          <li @class(['is-verdiend' => $b['earned']]) title="{{ $b['hint'] }}">
            <img src="{{ $b['img'] }}" alt="" width="40" height="40"> {{ $b['label'] }}
          </li>
        @endforeach
      </ul>
    </div>
  </div>

  <h2>Aanbod</h2>
  @if($aanbod->isEmpty())
    <p class="muted">Nog geen aanbod.</p>
  @else
    <div class="beheer-table-wrap">
      <table class="beheer-table">
        <thead><tr><th>Tekst</th><th>Soort</th><th>Status</th><th>Geplaatst</th></tr></thead>
        <tbody>
          @foreach($aanbod as $a)
            <tr>
              <td data-label="Tekst">{{ \Illuminate\Support\Str::limit((string) $a->tekst, 80) }}</td>
              <td data-label="Soort">{{ $a->type === 'ophalen' ? 'zoekt' : 'deelt' }}</td>
              <td data-label="Status">
                @if($a->visible && $a->expires_at?->isFuture())<span class="status status--ok">Actief tot {{ $a->expires_at->timezone('Europe/Amsterdam')->format('d-m') }}</span>
                @elseif(! $a->visible)<span class="status status--uit">Verborgen</span>
                @else<span class="status status--wacht">Verlopen</span>@endif
              </td>
              <td data-label="Geplaatst">{{ $a->created_at?->diffForHumans() }}</td>
            </tr>
          @endforeach
        </tbody>
      </table>
    </div>
  @endif

  <h2>Mails</h2>
  @if($mails->isEmpty())
    <p class="muted">Nog geen mails gelogd.</p>
  @else
    <div class="beheer-table-wrap">
      <table class="beheer-table">
        <thead><tr><th>Mail</th><th>Status</th><th>Wanneer</th></tr></thead>
        <tbody>
          @foreach($mails as $m)
            <tr>
              <td data-label="Mail">{{ \App\Support\MailLabels::type($m->type) }}</td>
              <td data-label="Status">
                @if($m->isOk())
                  <span class="status status--ok">Verstuurd</span>
                @else
                  <span class="status status--fout">{{ \App\Support\MailLabels::fout($m->error) }}</span>
                  @if($m->error)
                    <details class="beheer-details"><summary>Technisch</summary><code>{{ $m->error }}</code></details>
                  @endif
                @endif
              </td>
              <td data-label="Wanneer"><span title="{{ $m->created_at?->timezone('Europe/Amsterdam')->format('d-m-Y H:i') }}">{{ $m->created_at?->diffForHumans() }}</span></td>
            </tr>
          @endforeach
        </tbody>
      </table>
    </div>
  @endif

  <h2>Acties</h2>
  <div class="beheer-acties-blok">
    @if(! $u->hasVerifiedEmail())
      <form method="post" action="{{ route('beheer.users.resend-verification') }}">
        @csrf
        <input type="hidden" name="user_id" value="{{ $u->id }}">
        <button class="btn btn-secondary btn-klein" type="submit">Bevestigingsmail opnieuw sturen</button>
      </form>
    @endif
    @unless($isPionier)
      <form method="post" action="{{ route('beheer.users.pionier', $u) }}" onsubmit="return confirm('Pionier-badge naar {{ $u->name }} verplaatsen? Er is er maar één.');">
        @csrf
        <button class="btn btn-secondary btn-klein" type="submit">Maak pionier</button>
      </form>
    @endunless
    @if($naamIsEmail)
      <form method="post" action="{{ route('beheer.gebruikers.reset-naam', $u) }}">
        @csrf
        <button class="btn btn-secondary btn-klein" type="submit">Naam terugzetten naar «Lid»</button>
      </form>
    @endif
    @unless($isZelf)
      <form method="post" action="{{ route('beheer.gebruikers.actief', $u) }}" onsubmit="return confirm('{{ $u->active ? 'Account blokkeren? '.$u->name.' wordt uitgelogd en kan niet meer inloggen.' : 'Blokkade opheffen?' }}');">
        @csrf
        <button class="btn btn-secondary btn-klein" type="submit">{{ $u->active ? 'Blokkeren' : 'Blokkade opheffen' }}</button>
      </form>
    @endunless
  </div>

  @unless($isZelf)
    <div class="beheer-gevaar">
      <h2>Account verwijderen</h2>
      <p>Wist account, aanbod, foto's, gesprekken en badges. Dit kan niet terug.</p>
      <a class="btn btn-gevaar btn-klein" href="{{ route('beheer.users.destroy.confirm', $u) }}">Verwijderen…</a>
    </div>
  @endunless
</section>
@endsection
