@extends('layouts.site')

@section('title', $title)
@section('meta_description', $metaDescription)
@section('canonical', $canonical)

@section('content')
<section class="section wrap prose">
  <h1 class="page-title">Contact</h1>
  <p>Vragen over {{ config('site.name') }}? Stuur me gerust een mail: <a href="mailto:{{ config('site.contact_email') }}">{{ config('site.contact_email') }}</a>.</p>
  <p>{{ config('site.name') }} is een project van <a href="https://jpwebcreation.nl" rel="noopener noreferrer">JP Web Creation</a>. Meer over mij lees je op de pagina <a href="{{ route('over') }}">Over</a>.</p>
</section>
@endsection
