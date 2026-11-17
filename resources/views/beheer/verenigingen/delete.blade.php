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

  <h1 class="page-title">{{ $bulk ? 'Selectie verwijderen' : 'Vereniging verwijderen' }}</h1>

  @if($errors->has('confirm') || $errors->has('vereniging') || $errors->has('ids'))
    <p class="error" role="alert">{{ $errors->first('confirm') ?: ($errors->first('vereniging') ?: $errors->first('ids')) }}</p>
  @endif

  <div class="beheer-gevaar">
    <h2>Weet je het zeker?</h2>
    @if($bulk)
      <p>Je verwijdert <strong>{{ $verenigingen->count() }}</strong> verborgen vereniging{{ $verenigingen->count() === 1 ? '' : 'en' }}:</p>
    @else
      <p>Je verwijdert deze verborgen vereniging:</p>
    @endif
    <ul class="beheer-delete-lijst">
      @foreach($verenigingen as $v)
        <li>
          <strong>{{ $v->naam }}</strong> · {{ $v->plaats }}
          @if($v->leden_count > 0)
            <span class="muted">({{ $v->leden_count }} {{ $v->leden_count === 1 ? 'lid' : 'leden' }} gekoppeld)</span>
          @endif
        </li>
      @endforeach
    </ul>
    <p class="muted">Dit kan niet meer terug. Gekoppelde leden raken alleen de koppeling kwijt, hun account blijft bestaan.</p>
  </div>

  <form class="form beheer-delete-form" method="post" action="{{ $bulk ? route('beheer.verenigingen.bulk-destroy') : route('beheer.verenigingen.destroy', $verenigingen->first()) }}">
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
      <a class="btn btn-secondary" href="{{ route('beheer.verenigingen') }}">Annuleren</a>
    </div>
  </form>
</section>
@endsection
