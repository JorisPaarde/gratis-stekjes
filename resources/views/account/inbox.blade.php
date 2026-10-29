@extends('layouts.site')

@section('title', $title)
@section('meta_description', $metaDescription)
@section('canonical', $canonical)
@section('body_class', 'page-berichten')

@push('head')
  @vite(['resources/css/berichten.css'])
@endpush

@section('content')
<section class="section wrap">
  <h1 class="page-title">Inbox</h1>

  @if(session('error'))
    <p class="flash-error" role="alert">{{ session('error') }}</p>
  @endif

  @if($rows->isEmpty())
    <p class="muted">Nog geen gesprekken. Zoek <a href="{{ route('aanbod') }}">aanbod</a> en stuur een bericht.</p>
  @else
    <ul class="inbox-list">
      @foreach($rows as $r)
        <li class="inbox-item">
          <a href="{{ route('account.gesprek', $r['id']) }}">Gesprek met {{ $r['andere_naam'] }}</a>
        </li>
      @endforeach
    </ul>
  @endif

  <p class="muted" style="margin-top:1rem"><a href="{{ route('account.index') }}">Terug naar account</a></p>
</section>
@endsection
