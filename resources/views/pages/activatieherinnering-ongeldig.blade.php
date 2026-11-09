@extends('layouts.site')

@section('title', $title)
@section('meta_description', $metaDescription)
@section('canonical', $canonical)
@section('robots', 'noindex, follow')

@section('content')
<section class="section wrap">
  <h1 class="page-title">Link werkt niet meer</h1>
  <p>Deze tipmail-link is verlopen of ongeldig. Tipmails uitzetten kan op je <a href="{{ route('account.profiel') }}">profiel</a>.</p>
</section>
@endsection
