@extends('layouts.site')

@section('title', $title)
@section('meta_description', $metaDescription)
@section('canonical', $canonical)

@push('head')
  @vite(['resources/css/stektips.css'])
@endpush

@section('content')
<section class="section wrap stektips-page">
  <h1 class="page-title">{{ $title }}</h1>
  <div class="stack">
    <article class="soft-card">
      <h3><a href="{{ route('stektips.artikel', ['slug' => 'wilgenwater']) }}">Wilgenwater</a></h3>
      <p>Knip jonge wilgentakjes in stukjes, zet ze 24 tot 48 uur in koud water (liefst regenwater) of laat ze trekken in kokend water. Het bevat IBA (een wortelhormoon) en salicylzuur. De hoeveelheid is laag en niet bekend. Het helpt dus een beetje, maar geeft geen garantie.</p>
    </article>
    <article class="soft-card">
      <h3><a href="{{ route('stektips.artikel', ['slug' => 'stekpoeder']) }}">Stekpoeder</a></h3>
      <p>Poeder met een wortelhormoon (meestal IBA). Het helpt vooral bij lastige soorten. Bij bessen is het niet nodig. Probeer liever eerst een <a href="{{ route('stektips.artikel', ['slug' => 'wilgenwater']) }}">duurzamer alternatief</a>.</p>
    </article>
    <article class="soft-card">
      <h3>Bakjes en potten</h3>
      <p>Gebruik oude bakjes met gaatjes in de bodem. Neem 1 tot 2 liter voor vijg en moerbei. Een bakje met een plastic zak eroverheen werkt als minikas.</p>
    </article>
    <article class="soft-card">
      <h3><a href="{{ route('stektips.artikel', ['slug' => 'stekgrond-zelf-maken']) }}">Substraat</a></h3>
      <p>Stekgrond is gezeefde potgrond zonder mest. Meng er scherp zand door, zodat het water weg kan. Hou het niet te nat, anders gaan stekken rotten.</p>
    </article>
    <article class="soft-card">
      <h3>Gereedschap</h3>
      <p>Een scherpe, schone schaar of een mes, en labels met de soort en de datum. Zet stekken altijd met de goede kant naar boven.</p>
    </article>
  </div>
  <p class="stektips-meer"><a href="{{ route('stektips.index') }}">Naar stektips</a></p>
</section>
@endsection
