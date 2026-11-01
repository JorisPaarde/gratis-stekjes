  <h1 class="page-title" id="agenda">Agenda-evenementen</h1>
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
      <a href="{{ route('beheer.agenda') }}">Annuleren</a>
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
              <a href="{{ route('beheer.agenda', ['edit_evenement' => $ev->id]) }}">Bewerken</a>
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
