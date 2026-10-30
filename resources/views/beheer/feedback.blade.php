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
  <div class="beheer-header">
    <h1 class="page-title">Feedback</h1>
    <div class="beheer-actions">
      <a href="{{ route('beheer.index') }}">← Beheer</a>
      <a href="{{ route('beheer.statistieken') }}">Statistieken</a>
      <form method="post" action="{{ route('beheer.logout') }}">
        @csrf
        <button class="btn btn-secondary" type="submit">Uitloggen</button>
      </form>
    </div>
  </div>

  <p class="muted">Nieuwste eerst. Geen namen of e-mailadressen — alleen pad, tekst en tijdstip (Europe/Amsterdam).</p>

  @if(session('status'))
    <p class="flash flash-ok" role="status">{{ session('status') }}</p>
  @endif

  @if($items->isEmpty())
    <div class="empty-state" role="status">
      <p>Nog geen feedback.</p>
    </div>
  @else
    <div class="beheer-table-wrap">
      <table class="beheer-table">
        <thead>
          <tr>
            <th>Datum</th>
            <th>Pagina</th>
            <th>Tekst</th>
            <th>Status</th>
            <th></th>
          </tr>
        </thead>
        <tbody>
          @foreach($items as $item)
            <tr @class(['beheer-feedback-unread' => ! $item->isRead()])>
              <td>{{ $item->created_at?->timezone('Europe/Amsterdam')->format('d-m-Y H:i') }}</td>
              <td><code>{{ $item->page_path }}</code></td>
              <td class="beheer-feedback-body">{{ $item->body }}</td>
              <td>{{ $item->isRead() ? 'gelezen' : 'nieuw' }}</td>
              <td>
                @unless($item->isRead())
                  <form method="post" action="{{ route('beheer.feedback.read') }}">
                    @csrf
                    <input type="hidden" name="id" value="{{ $item->id }}">
                    <button type="submit" class="linkish">Markeer gelezen</button>
                  </form>
                @endunless
              </td>
            </tr>
          @endforeach
        </tbody>
      </table>
    </div>
  @endif
</section>
@endsection
