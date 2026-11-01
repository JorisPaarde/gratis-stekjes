@extends('layouts.site')

@section('title', $title)
@section('meta_description', 'Beheer')
@section('canonical', $canonical)

@push('head')
  @vite(['resources/css/admin.css'])
  <meta name="robots" content="noindex, nofollow">
@endpush

@section('content')
<section class="section wrap beheer-page">
  @include('beheer.partials.nav')
  @include('beheer.secties.'.$sectie)
</section>
@endsection
