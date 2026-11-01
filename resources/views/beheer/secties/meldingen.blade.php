  <h1 class="page-title" id="meldingen">Meldingen</h1>
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
