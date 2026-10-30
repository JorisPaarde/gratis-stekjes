@extends('layouts.site')

@section('title', $title)
@section('meta_description', $metaDescription)
@section('canonical', $canonical)

@section('content')
<section class="section wrap prose">
  <h1 class="page-title">Contact</h1>
  <p>Vragen over {{ config('site.name') }}? Mail mij via <a href="mailto:{{ config('site.contact_email') }}">{{ config('site.contact_email') }}</a>.</p>
  <p>{{ config('site.name') }} is een project van <a href="https://jpwebcreation.nl" rel="noopener noreferrer">JP Web Creation</a>. Meer over mij: <a href="{{ route('over') }}">Over</a>.</p>
</section>
@endsection
