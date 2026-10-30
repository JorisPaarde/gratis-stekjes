@extends('layouts.site')

@section('title', $title)
@section('meta_description', $metaDescription)
@section('canonical', $canonical)

@section('content')
<section class="section wrap prose">
  <h1 class="page-title">Hoe werkt het</h1>
  <p>Drie spelregels: <strong>gratis</strong>, alleen <strong>fysiek ophalen</strong>, en de enige tegenprestatie is een <strong>praatje</strong>.</p>
  <ol class="list-plain">
    <li>Je maakt één account. Daarmee kun je zowel iets aanbieden als iets ophalen.</li>
    <li>Je zet op de site wat je gratis weggeeft, met een benaderde plek (postcode of plaats).</li>
    <li>Je zoekt in de buurt en stuurt een bericht via de site. E-mailadressen blijven verborgen.</li>
    <li>Jullie spreken onderling een ophaalmoment af. Exacte adressen deel je zelf, als je dat wilt.</li>
    <li>In elk gesprek kies je of je verder contact wilt. Alleen bij wederzijds ja zien jullie elkaars contactgegevens.</li>
  </ol>
  <p><a class="btn" href="{{ route('account.registreren') }}">Doe mee</a></p>
</section>
@endsection
