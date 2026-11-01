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
    <h1 class="page-title">Beheer</h1>
    <div class="beheer-actions">
      <a href="{{ route('beheer.statistieken') }}">Statistieken</a>
      <a href="{{ route('beheer.feedback') }}">Feedback @if(($feedbackOngelezen ?? 0) > 0) <span class="nav-badge" aria-label="{{ $feedbackOngelezen }} ongelezen">{{ $feedbackOngelezen }}</span>@endif</a>
      <a href="{{ route('beheer.ervaringen') }}">Ervaringen</a>
      <form method="post" action="{{ route('beheer.logout') }}">
        @csrf
        <button class="btn btn-secondary" type="submit">Uitloggen</button>
      </form>
    </div>
  </div>

  @if(session('status'))
    <p class="flash flash-ok" role="status">{{ session('status') }}</p>
  @endif
  @if($errors->has('marker'))
    <p class="error" role="alert">{{ $errors->first('marker') }}</p>
  @endif
  @if($errors->has('evenement'))
    <p class="error" role="alert">{{ $errors->first('evenement') }}</p>
  @endif
  @if($errors->has('deelplek'))
    <p class="error" role="alert">{{ $errors->first('deelplek') }}</p>
  @endif
  @if($errors->has('user'))
    <p class="error" role="alert">{{ $errors->first('user') }}</p>
  @endif

  <h2 id="gebruikers">Gebruikers</h2>
  <p class="muted">Bevestigingsstatus en laatste mailpoging (geen e-mailadressen in mail_log). Tijdstip in Europe/Amsterdam.</p>
  @if(count($users) === 0)
    <p class="muted">Nog geen gebruikers.</p>
  @else
    <div class="beheer-table-wrap">
      <table class="beheer-table">
        <thead>
          <tr>
            <th>ID</th>
            <th>Naam</th>
            <th>Bevestigd</th>
            <th>Laatste mail</th>
            <th></th>
          </tr>
        </thead>
        <tbody>
          @foreach($users as $row)
            @php
              /** @var \App\Models\User $u */
              $u = $row['user'];
              /** @var \App\Models\MailLog|null $mail */
              $mail = $row['last_mail'];
              $isSelf = auth()->check() && (int) auth()->id() === (int) $u->id;
            @endphp
            <tr>
              <td>{{ $u->id }}</td>
              <td>{{ $u->name }}{!! str_contains((string) $u->name, '@') ? ' <span class="muted" title="Wordt openbaar getoond als «Lid»">(e-mail als naam)</span>' : '' !!}@if(($pionierId ?? null) === (int) $u->id) <img src="/assets/badges/pionier.svg" alt="Pionier" title="Pionier" width="22" height="23" style="vertical-align:middle">@endif</td>
              <td>{{ $u->hasVerifiedEmail() ? 'ja' : 'nee' }}</td>
              <td>
                @if($mail)
                  {{ $mail->type }} · {{ $mail->isOk() ? 'ok' : 'fout' }}
                  · {{ $mail->created_at?->timezone('Europe/Amsterdam')->format('d-m-Y H:i') }}
                  @if(! $mail->isOk() && $mail->error)
                    <div class="muted">{{ \Illuminate\Support\Str::limit($mail->error, 80) }}</div>
                  @endif
                @else
                  <span class="muted">geen</span>
                @endif
              </td>
              <td class="beheer-actions">
                @unless($u->hasVerifiedEmail())
                  <form method="post" action="{{ route('beheer.users.resend-verification') }}">
                    @csrf
                    <input type="hidden" name="user_id" value="{{ $u->id }}">
                    <button type="submit" class="linkish">Bevestigingsmail opnieuw sturen</button>
                  </form>
                @endunless
                @if(($pionierId ?? null) !== (int) $u->id)
                  <form method="post" action="{{ route('beheer.users.pionier', $u) }}" onsubmit="return confirm('Pionier-badge naar dit lid verplaatsen? Er is er maar één.');">
                    @csrf
                    <button type="submit" class="linkish">Maak pionier</button>
                  </form>
                @endif
                @unless($isSelf)
                  <a class="linkish" href="{{ route('beheer.users.destroy.confirm', $u) }}">Verwijderen</a>
                @endunless
              </td>
            </tr>
          @endforeach
        </tbody>
      </table>
    </div>
  @endif

  <h2 id="verenigingen">Verenigingen van leden</h2>
  @if(count($verenigingen ?? []) === 0)
    <p class="muted">Nog geen verenigingen.</p>
  @else
    <div class="beheer-table-wrap">
      <table class="beheer-table">
        <thead>
          <tr><th>Naam</th><th>Plaats</th><th>Soort</th><th>Leden</th><th>Status</th><th></th></tr>
        </thead>
        <tbody>
          @foreach($verenigingen as $v)
            <tr>
              <td>@if($v->isApproved())<a href="{{ $v->url() }}">{{ $v->naam }}</a>@else{{ $v->naam }}@endif</td>
              <td>{{ $v->plaats }}</td>
              <td>{{ $v->typeLabel() }}</td>
              <td>{{ $v->leden_count }}</td>
              <td>{{ ['pending' => 'wacht op controle', 'approved' => 'zichtbaar', 'hidden' => 'verborgen'][$v->status] ?? $v->status }}</td>
              <td class="beheer-actions">
                @foreach(['approved' => 'Goedkeuren', 'hidden' => 'Verbergen'] as $status => $label)
                  @if($v->status !== $status && ! ($status === 'approved' && $v->json_slug))
                    <form method="post" action="{{ route('beheer.verenigingen.status', $v) }}">
                      @csrf
                      <input type="hidden" name="status" value="{{ $status }}">
                      <button type="submit" class="linkish">{{ $label }}</button>
                    </form>
                  @endif
                @endforeach
              </td>
            </tr>
          @endforeach
        </tbody>
      </table>
    </div>
  @endif

  <h2 id="deelplek-aanmeldingen">Deelplek-aanmeldingen</h2>
  <p class="muted">Aanmeldingen via <a href="{{ route('voor-organisaties.aanmelden') }}">/voor-organisaties/aanmelden</a>. Pas na goedkeuring zichtbaar op deelplekken en stadspagina’s. Contact-e-mail is niet publiek.</p>
  @if(count($pendingDeelplekken) === 0)
    <p class="muted">Geen aanmeldingen in afwachting.</p>
  @else
    <div class="beheer-table-wrap">
      <table class="beheer-table">
        <thead>
          <tr>
            <th>Naam</th>
            <th>Type</th>
            <th>Plaats</th>
            <th>Contact</th>
            <th></th>
          </tr>
        </thead>
        <tbody>
          @foreach($pendingDeelplekken as $plek)
            <tr>
              <td>
                {{ $plek->naam }}
                @if($plek->beschrijving)
                  <div class="muted">{{ \Illuminate\Support\Str::limit($plek->beschrijving, 120) }}</div>
                @endif
                @if($plek->bron_url)
                  <div><a href="{{ $plek->bron_url }}" rel="noopener noreferrer" target="_blank">Website</a></div>
                @endif
              </td>
              <td>{{ $deelplekTypeLabels[$plek->type] ?? $plek->type }}</td>
              <td>{{ $plek->plaats }}<br><span class="muted">{{ $plek->adres }}</span></td>
              <td>
                @if($plek->isUnreadable('contact_email'))
                  {{ \App\Casts\SafeEncrypted::VIEW_FALLBACK }}
                @else
                  {{ $plek->contact_email }}
                @endif
              </td>
              <td class="beheer-actions">
                <form method="post" action="{{ route('beheer.deelplekken.approve') }}">
                  @csrf
                  <input type="hidden" name="id" value="{{ $plek->id }}">
                  <button type="submit" class="linkish">Goedkeuren</button>
                </form>
                <form method="post" action="{{ route('beheer.deelplekken.reject') }}" onsubmit="return confirm('Aanmelding afwijzen?');">
                  @csrf
                  <input type="hidden" name="id" value="{{ $plek->id }}">
                  <button type="submit" class="linkish">Afwijzen</button>
                </form>
              </td>
            </tr>
          @endforeach
        </tbody>
      </table>
    </div>
  @endif

  <h2 id="agenda">Agenda-evenementen</h2>
  <p class="muted">Stekjesmarkten, plantenruilbeurzen en ruildagen op <a href="{{ route('partners') }}">/initiatieven</a>. Verborgen items verdwijnen van de publieke agenda (niet definitief verwijderd).</p>

  <form class="form beheer-evenement-form" method="post" action="{{ route('beheer.evenementen.save') }}">
    @csrf
    <input type="hidden" name="id" value="{{ $editingEvenement->id ?? '' }}">
    <p><strong>{{ $editingEvenement ? 'Evenement bewerken' : 'Nieuw evenement' }}</strong></p>
    <label>Titel
      <input name="titel" required maxlength="200" value="{{ old('titel', $editingEvenement->titel ?? '') }}">
    </label>
    <label>Type
      <select name="type" required>
        @foreach($evenementTypeLabels as $val => $label)
          <option value="{{ $val }}" @selected(old('type', $editingEvenement->type ?? 'stekjesmarkt') === $val)>{{ $label }}</option>
        @endforeach
      </select>
    </label>
    <label>Startdatum
      <input type="date" name="start_datum" required value="{{ old('start_datum', isset($editingEvenement) ? $editingEvenement->start_datum?->format('Y-m-d') : '') }}">
    </label>
    <label>Einddatum (optioneel)
      <input type="date" name="eind_datum" value="{{ old('eind_datum', isset($editingEvenement) ? $editingEvenement->eind_datum?->format('Y-m-d') : '') }}">
    </label>
    <label>Plaats (gemeente)
      <input name="plaats" required maxlength="120" value="{{ old('plaats', $editingEvenement->plaats ?? '') }}">
    </label>
    <label>Provincie
      <select name="provincie" required>
        <option value="">Kies provincie</option>
        @foreach($provincies as $p)
          <option value="{{ $p }}" @selected(old('provincie', $editingEvenement->provincie ?? '') === $p)>{{ $p }}</option>
        @endforeach
      </select>
    </label>
    <label>Organisator
      <input name="organisator" maxlength="160" value="{{ old('organisator', $editingEvenement->organisator ?? '') }}">
    </label>
    <label>Externe link (optioneel)
      <input name="url" maxlength="500" placeholder="https://" value="{{ old('url', $editingEvenement->url ?? '') }}">
    </label>
    <label>Bron (intern)
      <input name="bron" maxlength="500" value="{{ old('bron', $editingEvenement->bron ?? '') }}">
    </label>
    <label>Laatst gecontroleerd
      <input type="date" name="gecontroleerd_op" value="{{ old('gecontroleerd_op', isset($editingEvenement) ? $editingEvenement->gecontroleerd_op?->format('Y-m-d') : '') }}">
    </label>
    <label class="check">
      <input type="checkbox" name="visible" value="1" @checked(old('visible', $editingEvenement->visible ?? true))>
      Zichtbaar op de agenda
    </label>
    <button class="btn" type="submit">Opslaan</button>
    @if($editingEvenement)
      <a href="{{ route('beheer.index') }}">Annuleren</a>
    @endif
  </form>

  <div class="beheer-table-wrap">
    <table class="beheer-table">
      <thead>
        <tr>
          <th>Titel</th>
          <th>Type</th>
          <th>Datum</th>
          <th>Plaats</th>
          <th>Zichtbaar</th>
          <th></th>
        </tr>
      </thead>
      <tbody>
        @forelse($evenementen as $ev)
          <tr>
            <td>{{ $ev->titel }}</td>
            <td>{{ $evenementTypeLabels[$ev->type] ?? $ev->type }}</td>
            <td>
              {{ $ev->start_datum?->format('Y-m-d') }}
              @if($ev->eind_datum)
                – {{ $ev->eind_datum->format('Y-m-d') }}
              @endif
            </td>
            <td>{{ $ev->plaats }}<br><span class="muted">{{ $ev->provincie }}</span></td>
            <td>{{ $ev->visible ? 'ja' : 'nee' }}</td>
            <td class="beheer-actions">
              <a href="{{ route('beheer.index', ['edit_evenement' => $ev->id]) }}">Bewerken</a>
              @if($ev->visible)
                <form method="post" action="{{ route('beheer.evenementen.hide') }}" onsubmit="return confirm('Evenement verbergen?');">
                  @csrf
                  <input type="hidden" name="id" value="{{ $ev->id }}">
                  <button type="submit" class="linkish">Verbergen</button>
                </form>
              @endif
            </td>
          </tr>
        @empty
          <tr><td colspan="6">
            <div class="empty-state" role="status">
              <p>Nog geen evenementen. Voeg hierboven een stekjesmarkt, plantenruilbeurs of ruildag toe — geen verzonnen data.</p>
            </div>
          </td></tr>
        @endforelse
      </tbody>
    </table>
  </div>

  <h2 id="markers">Kaartmarkers</h2>
  <p class="muted">Plekken op de aanbodkaart: initiatief, voedselbos, plantenasiel, plantenbieb of ruilkast. Coördinaten via PDOK (NL) of Nominatim (België) op plaats/postcode. Organisaties zonder bezoeklocatie horen op de initiatievenpagina, niet als marker.</p>

  <form class="form beheer-marker-form" method="post" action="{{ route('beheer.markers.save') }}">
    @csrf
    <input type="hidden" name="id" value="{{ $editing->id ?? '' }}">
    <p><strong>{{ $editing ? 'Marker bewerken' : 'Nieuwe marker' }}</strong></p>
    <label>Naam
      <input name="naam" required maxlength="160" value="{{ old('naam', $editing->naam ?? '') }}">
    </label>
    <label>Type
      <select name="type" required>
        @foreach($typeLabels as $val => $label)
          <option value="{{ $val }}" @selected(old('type', $editing->type ?? 'partner') === $val)>{{ $label }}</option>
        @endforeach
      </select>
    </label>
    <label>Land
      <select name="country" required>
        @foreach(($countries ?? ['NL' => 'Nederland', 'BE' => 'België']) as $code => $label)
          <option value="{{ $code }}" @selected(old('country', $editing->country ?? 'NL') === $code)>{{ $label }}</option>
        @endforeach
      </select>
    </label>
    <label>Plaats / postcode
      <input name="plaats" required maxlength="120" value="{{ old('plaats', $editing->plaats ?? '') }}" placeholder="Haarlem, 2011, 9000 of Gent">
    </label>
    <label>Korte beschrijving
      <input name="beschrijving" maxlength="500" value="{{ old('beschrijving', $editing->beschrijving ?? '') }}">
    </label>
    <label>Website (optioneel)
      <input name="url" maxlength="500" placeholder="https://" value="{{ old('url', $editing->url ?? '') }}">
    </label>
    <label>Bron (intern, niet publiek)
      <input name="bron" maxlength="500" value="{{ old('bron', $editing->bron ?? '') }}">
    </label>
    <label class="check">
      <input type="checkbox" name="visible" value="1" @checked(old('visible', $editing->visible ?? true))>
      Zichtbaar op de kaart
    </label>
    <button class="btn" type="submit">Opslaan</button>
    @if($editing)
      <a href="{{ route('beheer.index') }}">Annuleren</a>
    @endif
  </form>

  <div class="beheer-table-wrap">
    <table class="beheer-table">
      <thead>
        <tr>
          <th>Naam</th>
          <th>Type</th>
          <th>Plaats</th>
          <th>Zichtbaar</th>
          <th></th>
        </tr>
      </thead>
      <tbody>
        @forelse($markers as $m)
          <tr>
            <td>{{ $m->naam }}</td>
            <td>{{ $typeLabels[$m->type] ?? $m->type }}</td>
            <td>{{ $m->plaats }}</td>
            <td>{{ $m->visible ? 'ja' : 'nee' }}</td>
            <td class="beheer-actions">
              <a href="{{ route('beheer.index', ['edit' => $m->id]) }}">Bewerken</a>
              <form method="post" action="{{ route('beheer.markers.delete') }}" onsubmit="return confirm('Marker verwijderen?');">
                @csrf
                <input type="hidden" name="id" value="{{ $m->id }}">
                <button type="submit" class="linkish">Verwijderen</button>
              </form>
            </td>
          </tr>
        @empty
          <tr><td colspan="5">
            <div class="empty-state" role="status">
              <p>Nog geen kaartmarkers. Voeg hierboven een initiatief, voedselbos of deelplek-marker toe, of bekijk de publieke <a href="{{ route('deelplekken') }}">deelplekken</a>.</p>
            </div>
          </td></tr>
        @endforelse
      </tbody>
    </table>
  </div>

  <h2 id="meldingen">Meldingen</h2>
  @if(!$hasReportsTable)
    <p class="muted">Meldingentabel volgt met de berichten-release. Daarna verschijnen openstaande meldingen hier.</p>
  @elseif(count($reports) === 0)
    <div class="empty-state" role="status">
      <p>Geen openstaande meldingen. Nieuwe meldingen verschijnen hier zodra iemand iets signaleert.</p>
    </div>
  @else
    <div class="beheer-table-wrap">
      <table class="beheer-table">
        <thead>
          <tr>
            <th>ID</th>
            <th>Reporter</th>
            <th>Gemeld</th>
            <th>Reden</th>
            <th>Status</th>
            <th></th>
          </tr>
        </thead>
        <tbody>
          @foreach($reports as $r)
            <tr>
              <td>{{ $r->id }}</td>
              <td>{{ $r->reporter_id }}</td>
              <td>{{ $r->reported_user_id }}</td>
              <td>
                @if($r->isUnreadable('reason'))
                  {{ \App\Casts\SafeEncrypted::VIEW_FALLBACK }}
                @else
                  {{ \Illuminate\Support\Str::limit((string) ($r->reason ?? ''), 80) }}
                @endif
              </td>
              <td>{{ $r->handled ? 'afgehandeld' : 'open' }}</td>
              <td>
                @unless($r->handled)
                  <form method="post" action="{{ route('beheer.reports.handled') }}">
                    @csrf
                    <input type="hidden" name="id" value="{{ $r->id }}">
                    <button type="submit" class="linkish">Afgehandeld</button>
                  </form>
                @endunless
              </td>
            </tr>
          @endforeach
        </tbody>
      </table>
    </div>
  @endif
</section>
@endsection
