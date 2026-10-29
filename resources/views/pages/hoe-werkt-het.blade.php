@extends('layouts.site')

@section('title', $title)
@section('meta_description', $metaDescription)
@section('canonical', $canonical)

@section('content')
<section class="section wrap prose">
  <h1 class="page-title">Hoe werkt het</h1>
  <p>Drie spelregels: <strong>gratis</strong>, alleen <strong>fysiek ophalen</strong>, en de enige tegenprestatie is een <strong>praatje</strong>.</p>
  <ol class="list-plain">
    <li>Je maakt een account.</li>
    <li>Uitdelers zetten op de site wat ze gratis weggeven, met een benaderde plek (postcode of plaats).</li>
    <li>Ophalers zoeken in de buurt en sturen een bericht via de site. E-mailadressen blijven verborgen.</li>
    <li>Jullie spreken onderling een ophaalmoment af. Exacte adressen deel je zelf, als je dat wilt.</li>
    <li>In elk gesprek kies je of je verder contact wilt. Alleen bij wederzijds ja zien jullie elkaars contactgegevens.</li>
  </ol>
  <p><a class="btn" href="{{ route('account.registreren') }}">Doe mee</a></p>
</section>
@endsection
