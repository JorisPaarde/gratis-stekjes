@extends('layouts.site')

@section('title', $title)
@section('meta_description', $metaDescription)
@section('canonical', $canonical)

@section('content')
<section class="section wrap">
  <h1 class="page-title">Mijn aanbod</h1>
  <p class="section-intro">Vrije tekst over wat je hebt of zoekt. Aanbod staat {{ (int) config('stekjes.aanbod_ttl_days', 60) }} dagen in zoeken en op de kaart; daarna blijft het hier zichtbaar zodat je kunt verlengen.</p>
  <p class="muted"><x-icon name="waarschuwing" :size="16" /> Geen invasieve soorten (EU-Unielijst). <a href="{{ $invasieveUitlegUrl }}">Meer uitleg</a>.</p>

  @if($errors->any())
    <p class="error" role="alert">{{ $errors->first() }}</p>
  @endif

  <form class="form" method="post" action="{{ route('account.aanbod.store') }}" data-invasieve-form>
    @csrf
    @include('partials.honeypot')
    <label>Type *
      <select name="type" required>
        <option value="uitdelen" @selected(old('type', 'uitdelen') === 'uitdelen')>Ik deel uit</option>
        <option value="ophalen" @selected(old('type') === 'ophalen')>Ik zoek / wil ophalen</option>
      </select>
    </label>
    <label>Wat heb je of zoek je? *
      <textarea name="tekst" id="aanbod-tekst" required maxlength="2000" placeholder="Bijv. zwarte bessenstekken, vanaf november, zaterdagochtend" data-invasieve-input>{{ old('tekst') }}</textarea>
    </label>
    <p class="error" hidden data-invasieve-hint role="status"></p>
    <label class="checkbox">
      <input type="checkbox" name="gratis_akkoord" value="1" required @checked(old('gratis_akkoord'))>
      <span>Alleen gratis, geen verkoop.</span>
    </label>
    <button class="btn" type="submit">Plaatsen</button>
  </form>

  <h2>Jouw items</h2>
  @if($mine->isEmpty())
    <div class="empty-state" role="status">
      <p>Je hebt nog geen aanbod. Vul het formulier hierboven in, of bekijk eerst wat er in jouw buurt staat.</p>
      <p><a class="btn btn-secondary" href="{{ route('aanbod') }}">Naar aanbod &amp; kaart</a></p>
    </div>
  @else
    <ul class="zoek-lijst">
      @foreach($mine as $row)
        @php
          $expired = $row->isExpired();
          $editing = (int) old('edit_id', request('edit')) === (int) $row->id;
        @endphp
        <li class="zoek-item">
          <span class="badge">{{ $row->type }}</span>
          @if(! $row->visible || $expired)
            <span class="muted">
              (
              @if(! $row->visible)
                verborgen
              @endif
              @if(! $row->visible && $expired)
                ,
              @endif
              @if($expired)
                verlopen (niet meer in zoeken/kaart)
              @endif
              )
            </span>
          @endif
          @if($editing)
            <form class="form" method="post" action="{{ route('account.aanbod.update', $row) }}" data-invasieve-form>
              @csrf
              @include('partials.honeypot')
              <label>Type *
                <select name="type" required>
                  <option value="uitdelen" @selected(old('type', $row->type) === 'uitdelen')>Ik deel uit</option>
                  <option value="ophalen" @selected(old('type', $row->type) === 'ophalen')>Ik zoek / wil ophalen</option>
                </select>
              </label>
              <label>Tekst *
                <textarea name="tekst" required maxlength="2000" data-invasieve-input>{{ old('tekst', $row->tekst) }}</textarea>
              </label>
              <p class="error" hidden data-invasieve-hint role="status"></p>
              <input type="hidden" name="edit_id" value="{{ $row->id }}">
              <label class="checkbox">
                <input type="checkbox" name="gratis_akkoord" value="1" required checked>
                <span>Alleen gratis, geen verkoop.</span>
              </label>
              <button class="btn" type="submit">Opslaan</button>
              <a class="btn btn-secondary" href="{{ route('account.aanbod') }}">Annuleren</a>
            </form>
          @else
            <p>{{ $row->tekst }}</p>
            @if($row->expires_at)
              <p class="muted">Geldig tot {{ $row->expires_at->timezone('Europe/Amsterdam')->format('d-m-Y') }}</p>
            @endif
            <p>
              <a class="btn btn-secondary" href="{{ route('account.aanbod', ['edit' => $row->id]) }}">Wijzigen</a>
            </p>
            <form method="post" action="{{ route('account.aanbod.renew', $row) }}">
              @csrf
              <button class="btn btn-secondary" type="submit">Nog {{ (int) config('stekjes.aanbod_ttl_days', 60) }} dagen verlengen</button>
            </form>
            <form method="post" action="{{ route('account.aanbod.destroy', $row) }}" onsubmit="return confirm('Weet je het zeker?');">
              @csrf
              @method('DELETE')
              <button class="btn btn-secondary" type="submit">Verwijderen</button>
            </form>
          @endif
        </li>
      @endforeach
    </ul>
  @endif

  <p class="muted" style="margin-top:1rem">
    <a href="{{ route('account.profiel') }}">Profiel / locatie</a>
    · <a href="{{ route('aanbod') }}">Publieke kaart</a>
    · <a href="{{ $invasieveUitlegUrl }}">Invasieve soorten</a>
  </p>
</section>
@endsection

@push('scripts')
<script>
(function () {
  var terms = @json($invasieveTerms);
  var uitleg = @json($invasieveUitlegUrl);
  if (!terms || !terms.length) return;

  function normalize(text) {
    return String(text || '')
      .normalize('NFD').replace(/[\u0300-\u036f]/g, '')
      .toLowerCase()
      .replace(/[×✕]/g, 'x')
      .replace(/['’‘ʼ`´]/g, '')
      .replace(/[^a-z0-9]+/g, ' ')
      .replace(/\s+/g, ' ')
      .trim();
  }

  function findMatch(text) {
    var hay = ' ' + normalize(text) + ' ';
    for (var i = 0; i < terms.length; i++) {
      if (hay.indexOf(' ' + terms[i] + ' ') !== -1) return terms[i];
    }
    return null;
  }

  document.querySelectorAll('[data-invasieve-form]').forEach(function (form) {
    var input = form.querySelector('[data-invasieve-input]');
    var hint = form.querySelector('[data-invasieve-hint]');
    if (!input || !hint) return;
    function check() {
      var hit = findMatch(input.value);
      if (hit) {
        hint.hidden = false;
        hint.textContent = 'Let op: dit lijkt op een invasieve soort (“' + hit + '”). Die mag je niet aanbieden. Server-side wordt dit geblokkeerd. Uitleg: ' + uitleg;
      } else {
        hint.hidden = true;
        hint.textContent = '';
      }
    }
    input.addEventListener('input', check);
    check();
  });
})();
</script>
@endpush
