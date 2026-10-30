@extends('layouts.site')

@section('title', $title)
@section('meta_description', $metaDescription)
@section('canonical', $canonical)

@section('content')
<section class="section wrap">
  <h1 class="page-title">Feedback</h1>
  <p class="section-intro">Vertel me wat beter kan of wat je goed vindt. Ik lees alles zelf — geen mail, geen tracking.</p>

  @if($errors->any())
    <p class="error" role="alert">{{ $errors->first() }}</p>
  @endif

  <form class="form soft-card feedback-form" method="post" action="{{ route('feedback.store') }}">
    @csrf
    @include('partials.honeypot')
    <input type="hidden" name="page_path" value="{{ $pagePath }}">

    <label for="feedback-body">Wat kan beter of wat vind je goed?
      <textarea
        id="feedback-body"
        name="body"
        rows="6"
        maxlength="{{ $maxBody }}"
        required
        autofocus
      >{{ old('body') }}</textarea>
    </label>
    <p class="muted">Maximaal {{ $maxBody }} tekens. Pagina: <code>{{ $pagePath }}</code></p>

    <div class="form-actions">
      <button class="btn" type="submit">Verstuur feedback</button>
      <a class="btn btn-secondary" href="{{ $pagePath === '/' ? route('home') : url($pagePath) }}">Terug</a>
    </div>
  </form>
</section>
@endsection
