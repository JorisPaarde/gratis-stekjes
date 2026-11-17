@extends('layouts.site')

@section('title', $title)
@section('meta_description', $metaDescription)
@section('canonical', $canonical)

@section('content')
<section class="section wrap prose">
  <h1 class="page-title">Contact</h1>
  <p>Vragen over {{ config('site.name') }}? Stuur me gerust een mail: <a href="mailto:{{ config('site.contact_email') }}">{{ config('site.contact_email') }}</a>.</p>
  <p>Meer over mij lees je op de pagina <a href="{{ route('over') }}">Over</a>. Nieuw hier? Lees eerst <a href="{{ route('hoe-werkt-het') }}">hoe het werkt</a>. Wil je <a href="{{ route('voor-organisaties') }}">een plek of organisatie aanmelden</a>? Dat kan via het aanmeldformulier.</p>
</section>
@endsection
