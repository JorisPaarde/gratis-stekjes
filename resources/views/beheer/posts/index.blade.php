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

  <h1 class="page-title">Postlogboek</h1>
  <p class="section-intro">
    Overzicht van FB/IG-posts en conversie per post. Tracking-URL’s worden normaal
    automatisch gemint via de interne API (plantjes + Grok Bot). Handmatig toevoegen blijft mogelijk als fallback.
  </p>

  <p><a class="btn" href="{{ route('beheer.posts.create') }}">Post toevoegen</a></p>

  @if($byGroep !== [])
    <details class="stats-panel" open>
      <summary class="stats-panel-summary">
        <span class="stats-panel-title">Conversie per groep</span>
      </summary>
      <div class="stats-panel-body">
        <div class="beheer-table-wrap">
        <table class="beheer-table">
          <thead>
            <tr>
              <th>Groep</th>
              <th>Bezoekers</th>
              <th>CTA</th>
              <th>Register</th>
              <th>Accounts</th>
              <th>Bevestigd</th>
              <th>Eerste aanbod</th>
            </tr>
          </thead>
          <tbody>
            @foreach($byGroep as $slug => $stats)
              <tr>
                <td data-label="Groep"><code>{{ $slug }}</code></td>
                <td data-label="Bezoekers">{{ (int) ($stats['visitors'] ?? 0) }}</td>
                <td data-label="CTA">{{ (int) ($stats['cta_click'] ?? 0) }}</td>
                <td data-label="Register">{{ (int) ($stats['register_view'] ?? 0) }}</td>
                <td data-label="Accounts">{{ (int) ($stats['account_created'] ?? 0) }}</td>
                <td data-label="Bevestigd">{{ (int) ($stats['email_verified'] ?? 0) }}</td>
                <td data-label="Eerste aanbod">{{ (int) ($stats['first_offer'] ?? 0) }}</td>
              </tr>
            @endforeach
          </tbody>
        </table>
        </div>
      </div>
    </details>
  @endif

  @if($posts->isEmpty())
    <p class="muted">Nog geen posts. Mint via de API of voeg handmatig de eerste toe.</p>
  @else
    <div class="beheer-table-wrap">
      <table class="beheer-table beheer-posts">
        <thead>
          <tr>
            <th>Titel</th>
            <th>Datum</th>
            <th>Groep</th>
            <th>Links</th>
            <th>Bezoekers</th>
            <th>CTA</th>
            <th>Register</th>
            <th>Accounts</th>
            <th>Bevestigd</th>
            <th>Eerste aanbod</th>
            <th><span class="visually-hidden">Acties</span></th>
          </tr>
        </thead>
        <tbody>
          @foreach($posts as $post)
            @php
              $stats = $byPost[$post->id] ?? [];
              $tracking = $post->trackingUrl();
            @endphp
            <tr id="post-{{ $post->id }}">
              <td data-label="Titel">
                <strong>{{ $post->titel !== '' ? $post->titel : '—' }}</strong>
                <br><span class="muted beheer-klein">{{ $post->soort }} · #{{ $post->id }}</span>
                @if($post->notitie)
                  <br><span class="muted beheer-klein">{{ $post->notitie }}</span>
                @endif
              </td>
              <td data-label="Datum" class="beheer-datum">{{ $post->posted_on?->timezone('Europe/Amsterdam')->format('d-m-Y') }}</td>
              <td data-label="Groep">{{ $post->groep }}</td>
              <td data-label="Links">
                @php($short = $post->short_code ? $post->shortUrl() : null)
                @if($short)
                  <label class="utm-copy">
                    <span class="hint">Korte link</span>
                    <div class="utm-copy-row">
                      <input type="text" readonly value="{{ $short }}" data-utm-input id="short-{{ $post->id }}">
                      <button type="button" class="btn btn-secondary btn-klein" data-utm-copy data-target="short-{{ $post->id }}">Kopieer</button>
                    </div>
                  </label>
                @endif
                <label class="utm-copy">
                  <span class="hint">Tracking (lang)</span>
                  <div class="utm-copy-row">
                    <input type="text" readonly value="{{ $tracking }}" data-utm-input id="utm-{{ $post->id }}">
                    <button type="button" class="btn btn-secondary btn-klein" data-utm-copy data-target="utm-{{ $post->id }}">Kopieer</button>
                  </div>
                </label>
                <p class="beheer-klein muted">Doel: <code>{{ $post->resolvedTargetPath() }}</code></p>
                @if($post->link)
                  <p class="beheer-klein"><a href="{{ $post->link }}" rel="noopener noreferrer">Sociale post</a></p>
                @endif
              </td>
              <td data-label="Bezoekers">{{ (int) ($stats['visitors'] ?? 0) }}</td>
              <td data-label="CTA">{{ (int) ($stats['cta_click'] ?? 0) }}</td>
              <td data-label="Register">{{ (int) ($stats['register_view'] ?? 0) }}</td>
              <td data-label="Accounts">{{ (int) ($stats['account_created'] ?? 0) }}</td>
              <td data-label="Bevestigd">{{ (int) ($stats['email_verified'] ?? 0) }}</td>
              <td data-label="Eerste aanbod">{{ (int) ($stats['first_offer'] ?? 0) }}</td>
              <td class="beheer-acties-cel">
                <div class="beheer-acties-inner">
                  <a href="{{ route('beheer.posts.edit', $post) }}">Bewerken</a>
                  <form method="post" action="{{ route('beheer.posts.destroy', $post) }}" class="inline-form" onsubmit="return confirm('Post verwijderen?');">
                    @csrf
                    <button type="submit" class="linkish">Verwijderen</button>
                  </form>
                </div>
              </td>
            </tr>
          @endforeach
        </tbody>
      </table>
    </div>
    {{ $posts->links() }}
  @endif
</section>
@endsection

@push('scripts')
<script>
document.querySelectorAll('[data-utm-copy]').forEach(function (btn) {
  btn.addEventListener('click', function () {
    var input = document.getElementById(btn.getAttribute('data-target'));
    if (!input) return;
    input.select();
    input.setSelectionRange(0, 99999);
    navigator.clipboard.writeText(input.value).then(function () {
      btn.textContent = 'Gekopieerd';
      setTimeout(function () { btn.textContent = 'Kopieer'; }, 1500);
    }).catch(function () {
      document.execCommand('copy');
    });
  });
});
</script>
@endpush
