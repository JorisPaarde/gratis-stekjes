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
  @include('beheer.partials.nav')

  <p class="muted"><a href="{{ route('beheer.organisaties') }}">← Alle organisaties</a></p>
  <h1 class="page-title">Initiatiefpagina: {{ $org['naam'] }}</h1>
  <p class="muted">Organisatie-slug (JSON): <code>{{ $org['slug'] }}</code>. Agenda-acties koppel je in Beheer → Agenda via die slug.</p>

  @if($errors->any())
    <ul class="error-list" role="alert">
      @foreach($errors->all() as $error)
        <li class="error">{{ $error }}</li>
      @endforeach
    </ul>
  @endif

  <form class="form" method="post" action="{{ route('beheer.organisaties.update', ['organisatieSlug' => $org['slug']]) }}">
    @csrf

    <fieldset>
      <legend>Publieke pagina</legend>
      <label>Initiatief-slug
        <input name="initiatief_slug" maxlength="80" pattern="[a-z0-9]+(?:-[a-z0-9]+)*" value="{{ old('initiatief_slug', $init->initiatief_slug) }}" placeholder="leeg = geen pagina">
      </label>
      <p class="hint">Alleen a-z, 0-9 en streepjes. Mag niet botsen met vaste routes ({{ $reservedHint }}).</p>

      <label>Pagina-titel (H1)
        <input name="pagina_titel" maxlength="200" value="{{ old('pagina_titel', $init->pagina_titel) }}">
      </label>
      <label>Korte naam (in «Doe je mee met …?» en acties)
        <input name="korte_naam" maxlength="120" value="{{ old('korte_naam', $init->korte_naam) }}">
      </label>
      <label>Meta-beschrijving
        <input name="meta_description" maxlength="400" value="{{ old('meta_description', $init->meta_description) }}">
      </label>
    </fieldset>

    <fieldset>
      <legend>Wie zijn ze</legend>
      <label>Kop boven de intro
        <input name="intro_titel" maxlength="200" value="{{ old('intro_titel', $init->intro_titel) }}" placeholder="Wie zijn de …?">
      </label>
      <label>Introductietekst
        <textarea name="intro_tekst" rows="8" maxlength="10000">{{ old('intro_tekst', $init->intro_tekst) }}</textarea>
      </label>
      <p class="hint">Gewone tekst; een lege regel tussen alinea’s.</p>
    </fieldset>

    <fieldset>
      <legend>Links</legend>
      <label>Eigen site / agenda — URL
        <input type="url" name="site_url" maxlength="500" value="{{ old('site_url', $init->site_url) }}" placeholder="https://…">
      </label>
      <label>Linktekst
        <input name="site_link_tekst" maxlength="160" value="{{ old('site_link_tekst', $init->site_link_tekst) }}" placeholder="Meer over …">
      </label>
      <label>Externe actieagenda (URL, voor lege-acties-tekst)
        <input type="url" name="agenda_url" maxlength="500" value="{{ old('agenda_url', $init->agenda_url) }}" placeholder="https://…/agenda">
      </label>
      <label>Secundaire link (label; gaat naar /organisaties/…, die bij een slug doorverwijst)
        <input name="secundaire_link_label" maxlength="160" value="{{ old('secundaire_link_label', $init->secundaire_link_label) }}" placeholder="Over de stichting">
      </label>
    </fieldset>

    <fieldset>
      <legend>Aanmeldvinkje</legend>
      <label>Query-parameter (bijv. struikrover → ?struikrover=1)
        <input name="aanmeld_param" maxlength="40" pattern="[a-z][a-z0-9_]*" value="{{ old('aanmeld_param', $init->aanmeld_param) }}">
      </label>
      <label>Label bij registreren
        <input name="aanmeld_label" maxlength="120" value="{{ old('aanmeld_label', $init->aanmeld_label) }}" placeholder="Ik ben …">
      </label>
    </fieldset>

    <fieldset>
      <legend>Acties &amp; materiaal</legend>
      <label>Tekst als er geen komende acties zijn
        <textarea name="acties_leeg_tekst" rows="3" maxlength="2000">{{ old('acties_leeg_tekst', $init->acties_leeg_tekst) }}</textarea>
      </label>
      <label>Kop «Voor …»
        <input name="leden_titel" maxlength="160" value="{{ old('leden_titel', $init->leden_titel) }}">
      </label>
      <label>Intro bij poster/flyer
        <textarea name="leden_intro" rows="2" maxlength="2000">{{ old('leden_intro', $init->leden_intro) }}</textarea>
      </label>
      <label>Kopieertekst (nieuwsbrief / groepsapp)
        <textarea name="kopieertekst" rows="4" maxlength="5000">{{ old('kopieertekst', $init->kopieertekst) }}</textarea>
      </label>
    </fieldset>

    <button class="btn" type="submit">Opslaan</button>
  </form>
</section>
@endsection
