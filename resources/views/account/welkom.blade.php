@extends('layouts.site')

@section('title', $title)
@section('meta_description', $metaDescription)
@section('canonical', $canonical)

@section('content')
<section class="section wrap">
  <h1 class="page-title">Welkom bij Gratis planten</h1>
  <p class="section-intro">Je e-mailadres is bevestigd. Heb je een plant, stekje of zakje zaden over? Zet je eerste aanbod online, dan kunnen buren het vinden.</p>

  <p class="account-hoofdactie">
    <a class="btn btn-groot" href="{{ route('account.welkom.cta') }}">Zet je eerste stekje online</a>
  </p>
  <p class="muted" style="margin-top:0">
    <a href="{{ route('aanbod') }}">Liever eerst kijken? Bekijk het aanbod</a>
  </p>
</section>
@endsection
