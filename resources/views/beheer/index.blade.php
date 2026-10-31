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
      <a href="{{ route('beheer.feedback') }}">Feedback</a>
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
            @endphp
            <tr>
              <td>{{ $u->id }}</td>
              <td>{{ $u->name }}</td>
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
              <td>{{ $plek->contact_email }}</td>
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

  <h2 id="markers">Kaartmarkers</h2>
  <p class="muted">Plekken op de aanbodkaart: initiatief, voedselbos, plantenasiel, plantenbieb of ruilkast. Coördinaten via PDOK op plaats/postcode-4. Organisaties zonder bezoeklocatie horen op de initiatievenpagina, niet als marker.</p>

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
    <label>Plaats / postcode-4
      <input name="plaats" required maxlength="120" value="{{ old('plaats', $editing->plaats ?? '') }}">
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
              <td>{{ \Illuminate\Support\Str::limit($r->reason, 80) }}</td>
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
