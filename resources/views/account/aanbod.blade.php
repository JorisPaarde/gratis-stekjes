@extends('layouts.site')

@section('title', $title)
@section('meta_description', $metaDescription)
@section('canonical', $canonical)

@push('head')
  @vite(['resources/css/plantsoort-autocomplete.css', 'resources/css/aanbod-fotos.css'])
@endpush

@section('content')
<section class="section wrap">
  <h1 class="page-title">Mijn aanbod</h1>
  <p class="section-intro">Kies een plantensoort of typ vrij wat je hebt of zoekt. Met een foto mag de soortnaam optioneel blijven. Aanbod staat {{ (int) config('stekjes.aanbod_ttl_days', 60) }} dagen in zoeken en op de kaart; daarna blijft het hier zichtbaar zodat je kunt verlengen.</p>
  <p class="muted"><x-icon name="waarschuwing" :size="16" /> Geen invasieve soorten (EU-Unielijst). <a href="{{ $invasieveUitlegUrl }}">Meer uitleg</a>.</p>

  @if($errors->any())
    <p class="error" role="alert">{{ $errors->first() }}</p>
  @endif

  @include('account.partials.aanbod-form')

  <h2>Jouw items</h2>
  @if(count($mine) === 0)
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
            <form class="form" method="post" action="{{ route('account.aanbod.update', $row) }}" enctype="multipart/form-data" data-invasieve-form data-aanbod-foto-form>
              @csrf
              @include('partials.honeypot')
              <label>Type *
                <select name="type" required>
                  <option value="uitdelen" @selected(old('type', $row->type) === 'uitdelen')>Ik deel uit</option>
                  <option value="ophalen" @selected(old('type', $row->type) === 'ophalen')>Ik zoek / wil ophalen</option>
                </select>
              </label>
              <div
                class="plantsoort-autocomplete"
                data-plantsoort-autocomplete
                data-plantsoort-url="{{ $plantensoortenZoekUrl }}"
              >
                <label for="aanbod-tekst-{{ $row->id }}">Plantensoort
                  <input
                    type="text"
                    name="tekst"
                    id="aanbod-tekst-{{ $row->id }}"
                    maxlength="2000"
                    value="{{ old('tekst', $row->tekst) }}"
                    data-invasieve-input
                    data-plantsoort-input
                    data-aanbod-tekst
                    aria-autocomplete="list"
                    aria-controls="aanbod-plantsoort-listbox-{{ $row->id }}"
                    aria-expanded="false"
                    autocomplete="off"
                  >
                </label>
                <input type="hidden" name="plantensoort_id" value="{{ old('plantensoort_id', $row->plantensoort_id) }}" data-plantsoort-id>
                <ul
                  id="aanbod-plantsoort-listbox-{{ $row->id }}"
                  class="plantsoort-listbox"
                  role="listbox"
                  hidden
                  data-plantsoort-listbox
                ></ul>
                <p class="visually-hidden" role="status" aria-live="polite" data-plantsoort-status></p>
              </div>
              <p class="error" hidden data-invasieve-hint role="status"></p>

              @if($row->fotos->isNotEmpty())
                <fieldset class="aanbod-fotos-edit">
                  <legend>Huidige foto’s</legend>
                  <ul class="aanbod-foto-thumbs">
                    @foreach($row->fotos as $foto)
                      <li>
                        <img src="{{ $foto->thumbUrl() }}" alt="{{ $foto->toPublicArray()['alt'] }}" width="96" height="96" loading="lazy">
                        <label class="checkbox">
                          <input type="checkbox" name="remove_fotos[]" value="{{ $foto->id }}">
                          <span>Verwijderen</span>
                        </label>
                      </li>
                    @endforeach
                  </ul>
                </fieldset>
              @endif

              @php $slotsLeft = max(0, $fotoMaxCount - $row->fotos->count()); @endphp
              @if($slotsLeft > 0)
                <label for="aanbod-fotos-{{ $row->id }}">Extra foto’s (nog {{ $slotsLeft }})
                  <input
                    type="file"
                    name="fotos[]"
                    id="aanbod-fotos-{{ $row->id }}"
                    accept="image/jpeg,image/png,image/webp,.jpg,.jpeg,.png,.webp"
                    multiple
                    data-aanbod-fotos
                    data-aanbod-fotos-max="{{ $slotsLeft }}"
                  >
                </label>
              @endif

              <input type="hidden" name="edit_id" value="{{ $row->id }}">
              <label class="checkbox">
                <input type="checkbox" name="gratis_akkoord" value="1" required checked>
                <span>Alleen gratis, geen verkoop.</span>
              </label>
              <button class="btn" type="submit">Opslaan</button>
              <a class="btn btn-secondary" href="{{ route('account.aanbod') }}">Annuleren</a>
            </form>
          @else
            @if($row->fotos->isNotEmpty())
              <ul class="aanbod-foto-thumbs">
                @foreach($row->fotos as $foto)
                  <li>
                    <img src="{{ $foto->thumbUrl() }}" alt="{{ $foto->toPublicArray()['alt'] }}" width="120" height="120" loading="lazy">
                  </li>
                @endforeach
              </ul>
            @endif
            @if(trim((string) $row->tekst) !== '')
              <p>{{ $row->tekst }}</p>
            @endif
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

@include('account.partials.aanbod-form-scripts')
