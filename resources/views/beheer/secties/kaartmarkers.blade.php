  <h1 class="page-title" id="markers">Kaartmarkers</h1>
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
      <a href="{{ route('beheer.kaartmarkers') }}">Annuleren</a>
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
              <a href="{{ route('beheer.kaartmarkers', ['edit' => $m->id]) }}">Bewerken</a>
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
