@extends('layouts.site')

@section('title', $title)
@section('meta_description', $metaDescription)
@section('canonical', $canonical)

@section('content')
<section class="section wrap">
  <h1>Hallo {{ $user->name }}</h1>
  <p class="section-intro">Je e-mail is bevestigd. Aanbod, kaart en berichten volgen in volgende releases.</p>

  <div class="soft-card">
    <h2>Je account</h2>
    <p><strong>E-mail:</strong> {{ $user->email }}</p>
    <p class="muted">Profiel, locatie en aanbod: <em>binnenkort</em>.</p>
  </div>

  <p style="margin-top:1.25rem">
    <form method="post" action="{{ route('account.uitloggen') }}" style="display:inline">
      @csrf
      <button class="btn btn-secondary" type="submit">Uitloggen</button>
    </form>
  </p>
</section>
@endsection
