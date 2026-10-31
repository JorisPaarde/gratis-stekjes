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

  @if(count($rows) === 0)
    <div class="empty-state" role="status">
      <p>Nog geen gesprekken. Zoek iets in de buurt en stuur een bericht. Contactgegevens zie je pas als jullie allebei ja zeggen.</p>
      <p><a class="btn" href="{{ route('aanbod') }}">Zoek aanbod</a></p>
    </div>
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
