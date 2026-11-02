@extends('layouts.site')

@section('title', $title)
@section('meta_description', $metaDescription)
@section('canonical', $canonical)

@push('head')
  @vite(['resources/css/meedoen.css'])
@endpush

@section('content')
<section class="section wrap prose over-page">
  <h1 class="page-title">Wie zit er achter {{ config('site.name') }}?</h1>

  @include('partials.maker')

  <h2>Wat {{ config('site.name') }} is</h2>
  <p>Een plek waar mensen in Nederland en België gratis planten, stekjes en zaden aan elkaar geven. Drie spelregels: het is <strong>gratis</strong>, je haalt alles <strong>zelf op</strong>, en de enige tegenprestatie is een <strong>praatje</strong>. Met één account kun je zowel weggeven als ophalen. <a href="{{ route('hoe-werkt-het') }}">Zo werkt het</a>.</p>

  <h2>Gratis en zonder reclame</h2>
  <p>Meedoen kost niets en er wordt niets verkocht. Er zit geen reclame op de site en geen meetcode van derden die je volgt. De site is er om te helpen, niet om aan jouw aandacht te verdienen.</p>

  <h2>Samen verder</h2>
  <p>Ik zoek mensen en organisaties die mee willen bouwen: een tuincentrum, bibliotheek, volkstuinvereniging, gemeente of school, of gewoon iemand die een poster ophangt. <a href="{{ route('help-mee') }}">Kijk hoe je mee kunt helpen</a> of <a href="{{ route('help-mee') }}#partner">word partner</a>.</p>

  <p class="muted"><a href="{{ route('privacy') }}">Privacyverklaring</a> · <a href="{{ route('contact') }}">Contact</a></p>
</section>
@endsection
