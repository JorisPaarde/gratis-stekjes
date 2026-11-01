@extends('layouts.site')

@section('title', $title)
@section('meta_description', $metaDescription)
@section('canonical', $canonical)

@section('content')
<section class="section wrap prose">
  <h1 class="page-title">Mijn verenigingen</h1>
  <p class="section-intro">Ben je lid van een volkstuin, tuinvereniging of buurttuin? Koppel je account. Op de pagina van je vereniging zien andere leden dan wat jij deelt.</p>

  @if(session('status'))
    <p class="ok" role="status">{{ session('status') }}</p>
  @endif
  @if($errors->any())
    <p class="error" role="alert">{{ $errors->first() }}</p>
  @endif

  @if($mijn->isNotEmpty())
    <ul class="vereniging-lijst">
      @foreach($mijn as $v)
        <li class="soft-card vereniging-item">
          <div>
            <strong>{{ $v->naam }}</strong>
            <span class="muted">· {{ $v->plaats }} · {{ $v->typeLabel() }}</span>
            @if($v->status === \App\Models\Vereniging::STATUS_PENDING)
              <p class="muted">Wacht op controle. Zodra ik de vereniging heb goedgekeurd, komt hij op de site.</p>
            @elseif($v->isApproved())
              <p><a href="{{ $v->url() }}">Bekijk de pagina van {{ $v->naam }}</a></p>
            @endif
          </div>
          <form method="post" action="{{ route('account.verenigingen.update', $v) }}" class="vereniging-zichtbaar">
            @csrf
            <input type="hidden" name="zichtbaar" value="0">
            <label class="checkbox">
              <input type="checkbox" name="zichtbaar" value="1" @checked($v->pivot->zichtbaar) onchange="this.form.submit()">
              Toon bij mijn aanbod dat ik hier lid van ben
            </label>
            <noscript><button class="btn btn-secondary" type="submit">Opslaan</button></noscript>
          </form>
          <form method="post" action="{{ route('account.verenigingen.destroy', $v) }}">
            @csrf
            @method('DELETE')
            <button type="submit" class="linkish">Ontkoppelen</button>
          </form>
        </li>
      @endforeach
    </ul>
    <p class="muted">Staat het vinkje uit, dan ziet niemand dat je lid bent. Op de verenigingspagina staat alleen het aantal leden, nooit namen.</p>
  @endif

  @if($mijn->count() < $max)
    <h2>Vereniging toevoegen</h2>
    <form class="form" method="post" action="{{ route('account.verenigingen.store') }}">
      @csrf
      <label>Naam van je vereniging *
        <input name="vereniging" list="vereniging-opties" required maxlength="200" autocomplete="off" value="{{ old('vereniging') }}" placeholder="Bijvoorbeeld Volkstuinvereniging De Eendracht">
        <datalist id="vereniging-opties">
          @foreach($opties as $o)
            <option value="{{ $o['label'] }}"></option>
          @endforeach
        </datalist>
        <span class="hint">Begin te typen en kies uit de lijst. Staat hij er niet bij, vul dan ook plaats en soort in.</span>
      </label>
      <label>Plaats
        <input name="plaats" maxlength="120" value="{{ old('plaats') }}" placeholder="Bijvoorbeeld Haarlem">
      </label>
      <label>Soort vereniging
        <select name="type">
          @foreach($types as $key => $label)
            <option value="{{ $key }}" @selected(old('type', 'volkstuin') === $key)>{{ $label }}</option>
          @endforeach
        </select>
      </label>
      <button class="btn" type="submit">Toevoegen</button>
    </form>
  @else
    <p class="muted">Je bent bij het maximum van {{ $max }} verenigingen.</p>
  @endif

  <p class="muted" style="margin-top:1rem"><a href="{{ route('account.index') }}">Terug naar account</a></p>
</section>
@endsection
