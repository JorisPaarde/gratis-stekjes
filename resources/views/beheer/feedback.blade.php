@extends('layouts.site')

@section('title', $title)
@section('meta_description', $metaDescription)
@section('canonical', $canonical)

@push('head')
  @vite(['resources/css/admin.css'])
  <meta name="robots" content="noindex, nofollow">
@endpush

@section('content')
<section class="section wrap beheer-page beheer-feedback-page">
  @include('beheer.partials.nav')

  <h1 class="page-title">Feedback</h1>

  <p class="muted">Nieuwste eerst. Geen namen of e-mailadressen — alleen pad, tekst en tijdstip (Europe/Amsterdam).</p>


  @if($items->isEmpty())
    <div class="empty-state" role="status">
      <p>Nog geen feedback.</p>
    </div>
  @else
    <ul class="beheer-feedback-cards">
      @foreach($items as $item)
        <li @class(['beheer-feedback-card', 'beheer-feedback-unread' => ! $item->isRead()])>
          <div class="beheer-feedback-card-meta">
            <time datetime="{{ $item->created_at?->toIso8601String() }}">{{ $item->created_at?->timezone('Europe/Amsterdam')->format('d-m-Y H:i') }}</time>
            <code>{{ $item->page_path }}</code>
          </div>
          <p class="beheer-feedback-body">
            @if($item->isUnreadable('body'))
              {{ \App\Casts\SafeEncrypted::VIEW_FALLBACK }}
            @else
              {{ $item->body }}
            @endif
          </p>
          <div class="beheer-feedback-card-footer">
            <span @class(['beheer-feedback-status', $item->isRead() ? 'is-gelezen' : 'is-nieuw'])>
              {{ $item->isRead() ? 'gelezen' : 'nieuw' }}
            </span>
            <div class="beheer-feedback-acties">
              @unless($item->isRead())
                <form method="post" action="{{ route('beheer.feedback.read') }}">
                  @csrf
                  <input type="hidden" name="id" value="{{ $item->id }}">
                  <button type="submit" class="linkish">Markeer gelezen</button>
                </form>
              @endunless
              <form method="post" action="{{ route('beheer.feedback.destroy', ['feedback' => $item->id]) }}" onsubmit="return confirm('Deze feedback definitief verwijderen?');">
                @csrf
                @method('DELETE')
                <button type="submit" class="linkish beheer-feedback-verwijder">Verwijderen</button>
              </form>
            </div>
          </div>
        </li>
      @endforeach
    </ul>
  @endif
</section>
@endsection
