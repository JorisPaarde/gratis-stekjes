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

  <h1 class="page-title">{{ $title }}</h1>

  @if($errors->any())
    <ul class="error-list" role="alert">
      @foreach($errors->all() as $error)
        <li class="error">{{ $error }}</li>
      @endforeach
    </ul>
  @endif

  <form class="form" method="post" action="{{ $action }}">
    @csrf
    <label>Datum *
      <input type="date" name="posted_on" required value="{{ old('posted_on', optional($post->posted_on)->format('Y-m-d') ?? now()->toDateString()) }}">
    </label>
    <label>Titel * <span class="hint">korte omschrijving van de post</span>
      <input type="text" name="titel" required maxlength="200" value="{{ old('titel', $post->titel) }}">
    </label>
    <label>Groep * <span class="hint">bijv. naam van de Facebookgroep</span>
      <input type="text" name="groep" required maxlength="120" value="{{ old('groep', $post->groep) }}">
    </label>
    <label>Soort post *
      <select name="soort" required>
        @foreach($soorten as $soort)
          <option value="{{ $soort }}" @selected(old('soort', $post->soort) === $soort)>{{ $soort }}</option>
        @endforeach
      </select>
    </label>
    <label>Link naar de post
      <input type="url" name="link" maxlength="500" value="{{ old('link', $post->link) }}" placeholder="https://…">
    </label>
    <label>utm_source * <span class="hint">standaard facebook</span>
      <input type="text" name="utm_source" required maxlength="80" value="{{ old('utm_source', $post->utm_source ?: 'facebook') }}">
    </label>
    <label>Notitie
      <textarea name="notitie" maxlength="500" rows="3">{{ old('notitie', $post->notitie) }}</textarea>
    </label>
    <button class="btn" type="submit">Opslaan</button>
    <a class="btn btn-secondary" href="{{ route('beheer.posts.index') }}">Annuleren</a>
  </form>
</section>
@endsection
