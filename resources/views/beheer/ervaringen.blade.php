@extends('layouts.site')

@section('title', $title)
@section('meta_description', $metaDescription)
@section('canonical', $canonical)

@push('head')
  @vite(['resources/css/admin.css'])
  <meta name="robots" content="noindex, nofollow">
@endpush

@section('content')
<section class="section wrap beheer-page">
  @include('beheer.partials.nav')

  <h1 class="page-title">Ervaringen</h1>

  <p class="muted">Nieuwste eerst; in afwachting bovenaan. Goedkeuren maakt ze zichtbaar op /ervaringen.</p>


  @if($rows->isEmpty())
    <div class="empty-state" role="status">
      <p>Nog geen ervaringen.</p>
    </div>
  @else
    <ul class="beheer-feedback-cards">
      @foreach($rows as $row)
        @php($item = $row['model'])
        <li @class(['beheer-feedback-card', 'beheer-feedback-unread' => $item->isPending()])>
          <div class="beheer-feedback-card-meta">
            <time datetime="{{ $item->created_at?->toIso8601String() }}">{{ $item->created_at?->timezone('Europe/Amsterdam')->format('d-m-Y H:i') }}</time>
            <span>{{ $item->user?->name ?? 'onbekend' }} · {{ $item->plaatsnaam }}</span>
            @if($row['soort'])
              <code>{{ $row['soort'] }}</code>
            @endif
          </div>
          <p class="beheer-feedback-body">{{ $item->body }}</p>
          @if($item->hasPhoto())
            <p><img src="{{ route('beheer.ervaringen.foto', $item) }}" alt="" width="320" height="240" loading="lazy" style="max-width:16rem;border-radius:.5rem"></p>
          @endif
          <div class="beheer-feedback-card-footer" style="display:flex;gap:.75rem;flex-wrap:wrap;align-items:center">
            <span @class(['beheer-feedback-status', $item->isPending() ? 'is-nieuw' : 'is-gelezen'])>
              {{ $item->status }}
            </span>
            @if($item->isPending())
              <form method="post" action="{{ route('beheer.ervaringen.approve') }}">
                @csrf
                <input type="hidden" name="id" value="{{ $item->id }}">
                <button type="submit" class="linkish">Goedkeuren</button>
              </form>
              <form method="post" action="{{ route('beheer.ervaringen.reject') }}">
                @csrf
                <input type="hidden" name="id" value="{{ $item->id }}">
                <button type="submit" class="linkish">Afwijzen</button>
              </form>
            @endif
            <form method="post" action="{{ route('beheer.ervaringen.destroy') }}" onsubmit="return confirm('Definitief verwijderen?');">
              @csrf
              <input type="hidden" name="id" value="{{ $item->id }}">
              <button type="submit" class="linkish">Verwijderen</button>
            </form>
          </div>
        </li>
      @endforeach
    </ul>
  @endif
</section>
@endsection
