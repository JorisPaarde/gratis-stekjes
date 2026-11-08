{{-- Formulier nieuw aanbod; gedeeld door /aanbieden en /account/aanbod. --}}
<form class="form" method="post" action="{{ route('account.aanbod.store') }}" enctype="multipart/form-data" data-invasieve-form data-aanbod-foto-form>
  @csrf
  @include('partials.honeypot')
  @isset($terug)<input type="hidden" name="terug" value="{{ $terug }}">@endisset
  <label>Type *
    <select name="type" required>
      <option value="uitdelen" @selected(old('type', 'uitdelen') === 'uitdelen')>Ik deel uit</option>
      <option value="ophalen" @selected(old('type') === 'ophalen')>Ik zoek / wil ophalen</option>
    </select>
  </label>
  <div
    class="plantsoort-autocomplete"
    data-plantsoort-autocomplete
    data-plantsoort-url="{{ $plantensoortenZoekUrl }}"
  >
    <label for="aanbod-tekst">Plantensoort *
      <span class="hint">Typ de naam van je plant (een gebruikelijke naam volstaat). Ik toon suggesties; staat jouw soort er niet bij, typ dan vrij verder. Foto is optioneel.</span>
      <input
        type="text"
        name="tekst"
        id="aanbod-tekst"
        maxlength="2000"
        required
        placeholder="Bijvoorbeeld monstera of munt"
        value="{{ old('tekst') }}"
        data-invasieve-input
        data-plantsoort-input
        data-aanbod-tekst
        aria-required="true"
        aria-describedby="aanbod-tekst-fout"
        aria-autocomplete="list"
        aria-controls="aanbod-plantsoort-listbox"
        aria-expanded="false"
        autocomplete="off"
      >
    </label>
    <p class="error" id="aanbod-tekst-fout" hidden data-aanbod-tekst-fout role="alert"></p>
    <input type="hidden" name="plantensoort_id" value="{{ old('plantensoort_id') }}" data-plantsoort-id>
    <ul
      id="aanbod-plantsoort-listbox"
      class="plantsoort-listbox"
      role="listbox"
      hidden
      data-plantsoort-listbox
    ></ul>
    <p class="visually-hidden" role="status" aria-live="polite" data-plantsoort-status></p>
  </div>
  <p class="error" hidden data-invasieve-hint role="status"></p>

<label class="aanbod-aantal-veld">Aantal
  <input type="number" name="aantal" min="1" max="{{ \App\Models\Aanbod::AANTAL_MAX }}" required inputmode="numeric" value="{{ old('aantal', 1) }}">
</label>

  <label for="aanbod-fotos">Foto’s (max. {{ $fotoMaxCount }}, JPEG/PNG/WebP, max. {{ $fotoMaxMb }} MB)
    <span class="hint">Optioneel. Voor je privacy haal ik de locatie uit je foto’s.</span>
    <input
      type="file"
      name="fotos[]"
      id="aanbod-fotos"
      accept="image/jpeg,image/png,image/webp,.jpg,.jpeg,.png,.webp"
      multiple
      data-aanbod-fotos
      data-aanbod-fotos-max="{{ $fotoMaxCount }}"
    >
  </label>

  <label class="checkbox">
    <input type="checkbox" name="gratis_akkoord" value="1" required @checked(old('gratis_akkoord'))>
    <span>Alleen gratis, geen verkoop.</span>
  </label>
  <button class="btn" type="submit">Plaatsen</button>
</form>
