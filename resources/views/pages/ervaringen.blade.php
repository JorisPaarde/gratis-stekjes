@extends('layouts.site')

@section('title', $title)
@section('meta_description', $metaDescription)
@section('canonical', $canonical)

@push('head')
  @if(!empty($forceNoindex))
    <meta name="robots" content="noindex, nofollow">
  @endif
@endpush

@section('content')
<section class="section wrap">
  <h1 class="page-title">Ervaringen</h1>
  <p class="section-intro">Korte verhalen van leden die planten en stekjes delen — met of zonder foto. Alleen plaatsnaam, nooit een adres.</p>

  @if($empty)
    <div class="empty-state" role="status">
      <p>Nog geen goedgekeurde ervaringen. Zodra er iets staat, vind je het hier.</p>
      @auth
        <p><a class="btn" href="{{ route('account.ervaringen.create') }}">Deel jouw ervaring</a></p>
      @else
        <p class="muted"><a href="{{ route('account.inloggen') }}">Log in</a> om zelf een ervaring te delen.</p>
      @endauth
    </div>
  @else
    <ul class="ervaringen-list" style="list-style:none;padding:0;margin:0;display:grid;gap:1.25rem">
      @foreach($items as $item)
        <li class="soft-card">
          @if(!empty($item['has_photo']) && !empty($item['photo_url']))
            <p style="margin:0 0 .75rem">
              <img src="{{ $item['photo_url'] }}" alt="" width="640" height="480" loading="lazy" decoding="async" style="width:100%;max-height:22rem;object-fit:cover;border-radius:var(--radius, .75rem)">
            </p>
          @endif
          <p style="margin:0 0 .5rem">{{ $item['body'] }}</p>
          <p class="muted" style="margin:0;font-size:.9rem">
            {{ $item['naam'] }}
            · {{ $item['plaatsnaam'] }}
            @if(!empty($item['soort'])) · {{ $item['soort'] }} @endif
            @if(!empty($item['datum'])) · {{ $item['datum'] }} @endif
          </p>
        </li>
      @endforeach
    </ul>
    @auth
      <p style="margin-top:1.5rem"><a class="btn" href="{{ route('account.ervaringen.create') }}">Deel jouw ervaring</a></p>
    @endauth
  @endif
</section>
@endsection
