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
    <form method="post" action="{{ route('beheer.logout') }}">
      @csrf
      <button class="btn btn-secondary" type="submit">Uitloggen</button>
    </form>
  </div>

  @if(session('status'))
    <p class="flash flash-ok" role="status">{{ session('status') }}</p>
  @endif
  @if($errors->has('marker'))
    <p class="error" role="alert">{{ $errors->first('marker') }}</p>
  @endif

  <h2 id="markers">Partnermarkers</h2>
  <p class="muted">Plekken op de aanbodkaart: partner, voedselbos, plantenasiel, plantenbieb of ruilkast. Coördinaten via PDOK op plaats/postcode-4. Organisaties zonder bezoeklocatie horen op de partnerspagina, niet als marker.</p>

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
          <tr><td colspan="5">Nog geen markers.</td></tr>
        @endforelse
      </tbody>
    </table>
  </div>

  <h2 id="meldingen">Meldingen</h2>
  @if(!$hasReportsTable)
    <p class="muted">Meldingentabel volgt met de berichten-release. Daarna verschijnen openstaande meldingen hier.</p>
  @elseif($reports->isEmpty())
    <p class="muted">Geen meldingen.</p>
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
