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

  <h1 class="page-title">{{ $bulk ? 'Selectie verwijderen' : 'Deelplek verwijderen' }}</h1>

  @if($errors->has('confirm') || $errors->has('deelplek') || $errors->has('ids'))
    <p class="error" role="alert">{{ $errors->first('confirm') ?: ($errors->first('deelplek') ?: $errors->first('ids')) }}</p>
  @endif

  <div class="beheer-gevaar">
    <h2>Weet je het zeker?</h2>
    @if($bulk)
      <p>Je verwijdert <strong>{{ $plekken->count() }}</strong> deelplek{{ $plekken->count() === 1 ? '' : 'ken' }}:</p>
    @else
      <p>Je verwijdert deze deelplek:</p>
    @endif
    <ul class="beheer-delete-lijst">
      @foreach($plekken as $plek)
        <li><strong>{{ $plek->naam }}</strong> · {{ $plek->plaats }} <span class="status {{ $plek->statusChipClass() }}">{{ $plek->statusLabel() }}</span></li>
      @endforeach
    </ul>
    <p class="muted">Dit kan niet meer terug. De plek verdwijnt uit API, kaart en stadspagina’s. Bij de volgende deploy komt hij niet terug.</p>
  </div>

  <form class="form beheer-delete-form" method="post" action="{{ $bulk ? route('beheer.deelplekken.bulk-destroy') : route('beheer.deelplekken.destroy', $plekken->first()) }}">
    @csrf
    @foreach($ids as $id)
      <input type="hidden" name="ids[]" value="{{ $id }}">
    @endforeach
    <label class="check">
      <input type="checkbox" name="confirm" value="1" required @checked(old('confirm'))>
      Ja, ik weet het zeker
    </label>
    <div class="beheer-acties-blok">
      <button class="btn btn-gevaar" type="submit">Definitief verwijderen</button>
      <a class="btn btn-secondary" href="{{ route('beheer.deelplekken.index', ['zichtbaar' => 'verborgen']) }}">Annuleren</a>
    </div>
  </form>
</section>
@endsection
