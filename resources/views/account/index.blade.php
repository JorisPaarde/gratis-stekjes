@extends('layouts.site')

@section('title', $title)
@section('meta_description', $metaDescription)
@section('canonical', $canonical)

@section('content')
<section class="section wrap">
  <h1>Hallo {{ $user->name }}</h1>
  <p class="section-intro">Je e-mail is bevestigd. Vul je locatie in en plaats gratis aanbod op de kaart.</p>

  <div class="soft-card">
    <h2>Je account</h2>
    <p><strong>E-mail:</strong> {{ $user->email }}</p>
    @if($user->hasLocation())
      <p><strong>Locatie:</strong> {{ $user->locatie_label }}</p>
    @else
      <p class="muted">Nog geen locatie — nodig om aanbod te plaatsen.</p>
    @endif
    <p>
      <a href="{{ route('account.profiel') }}">Profiel</a>
      · <a href="{{ route('account.aanbod') }}">Mijn aanbod</a>
      · <a href="{{ route('aanbod') }}">Kaart</a>
    </p>
    <p class="muted">Berichten: <em>binnenkort</em>.</p>
  </div>

  <p style="margin-top:1.25rem">
    <form method="post" action="{{ route('account.uitloggen') }}" style="display:inline">
      @csrf
      <button class="btn btn-secondary" type="submit">Uitloggen</button>
    </form>
  </p>
</section>
@endsection
