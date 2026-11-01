  <h1 class="page-title" id="deelplek-aanmeldingen">Deelplek-aanmeldingen</h1>
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
