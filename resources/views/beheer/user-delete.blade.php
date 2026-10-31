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
    <h1 class="page-title">Gebruiker verwijderen</h1>
    <div class="beheer-actions">
      <a href="{{ route('beheer.index') }}">← Beheer</a>
    </div>
  </div>

  @if($errors->has('confirm'))
    <p class="error" role="alert">{{ $errors->first('confirm') }}</p>
  @endif

  <p>Weet je zeker dat je gebruiker <strong>#{{ $user->id }}</strong> ({{ $user->name }}) wilt verwijderen?</p>
  <p class="muted">Dit wist aanbod, berichten/gesprekken, foto’s en tokens. Feedback blijft geanonimiseerd. Er gaat geen mail naar de gebruiker.</p>

  <form class="form" method="post" action="{{ route('beheer.users.destroy', $user) }}">
    @csrf
    <label>Typ <code>{{ $confirmWord }}</code> om te bevestigen
      <input name="confirm" required maxlength="40" autocomplete="off" value="{{ old('confirm') }}">
    </label>
    <button class="btn" type="submit">Definitief verwijderen</button>
    <a href="{{ route('beheer.index') }}">Annuleren</a>
  </form>
</section>
@endsection
