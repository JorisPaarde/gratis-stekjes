@extends('layouts.site')

@section('title', $title)
@section('meta_description', $metaDescription)
@section('canonical', $canonical)

@section('content')
<section class="section wrap prose">
  <h1 class="page-title">{{ $title }}</h1>
  <p>Drie spelregels: het is <strong>gratis</strong>, je haalt alles <strong>zelf op</strong>, en de enige tegenprestatie is een <strong>praatje</strong>.</p>
  <ol class="list-plain">
    <li>Je maakt één account. Daarmee kun je iets aanbieden én iets ophalen.</li>
    <li>Je zet op de site wat je gratis weggeeft: planten, stekjes of zaden. Je geeft een postcode of plaats op, geen straatadres.</li>
    <li>Je zoekt in de buurt en stuurt een bericht via de site. Je e-mailadres blijft verborgen.</li>
    <li>Jullie spreken samen een ophaalmoment af. Je adres deel je zelf, als je dat wilt.</li>
    <li>In elk gesprek kies je of je verder contact wilt. Alleen als jullie allebei ja zeggen, zien jullie elkaars contactgegevens.</li>
  </ol>
  <p><a class="btn" href="{{ route('account.registreren') }}">Doe mee</a></p>
</section>
@endsection
