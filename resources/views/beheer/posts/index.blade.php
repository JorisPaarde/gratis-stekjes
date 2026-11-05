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
  <p class="section-intro">Noteer social posts, maak een UTM-link en zie later de conversie per post en per groep.</p>

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
                <td><code>{{ $slug }}</code></td>
                <td>{{ (int) ($stats['visitors'] ?? 0) }}</td>
                <td>{{ (int) ($stats['cta_click'] ?? 0) }}</td>
                <td>{{ (int) ($stats['register_view'] ?? 0) }}</td>
                <td>{{ (int) ($stats['account_created'] ?? 0) }}</td>
                <td>{{ (int) ($stats['email_verified'] ?? 0) }}</td>
                <td>{{ (int) ($stats['first_offer'] ?? 0) }}</td>
              </tr>
            @endforeach
          </tbody>
        </table>
        </div>
      </div>
    </details>
  @endif

  <div class="beheer-lijst">
    @forelse($posts as $post)
      @php $stats = $byPost[$post->id] ?? []; @endphp
      <article class="beheer-kaart" id="post-{{ $post->id }}">
        <header class="beheer-kaart-kop">
          <h2>{{ $post->groep }} · {{ $post->soort }}</h2>
          <p class="muted">{{ $post->posted_on?->timezone('Europe/Amsterdam')->format('d-m-Y') }}</p>
        </header>
        @if($post->link)
          <p><a href="{{ $post->link }}" rel="noopener noreferrer">Originele post</a></p>
        @endif
        @if($post->notitie)
          <p>{{ $post->notitie }}</p>
        @endif

        <label class="utm-copy">
          <span class="hint">UTM-link (utm_source={{ $post->utm_source }}, medium=social, campaign={{ $post->groep_slug }}, content={{ $post->id }})</span>
          <div class="utm-copy-row">
            <input type="text" readonly value="{{ $post->trackingUrl() }}" data-utm-input id="utm-{{ $post->id }}">
            <button type="button" class="btn btn-secondary btn-klein" data-utm-copy data-target="utm-{{ $post->id }}">Kopieer</button>
          </div>
        </label>

        <p class="muted">
          Bezoekers {{ (int) ($stats['visitors'] ?? 0) }}
          · CTA {{ (int) ($stats['cta_click'] ?? 0) }}
          · Register {{ (int) ($stats['register_view'] ?? 0) }}
          · Accounts {{ (int) ($stats['account_created'] ?? 0) }}
          · Bevestigd {{ (int) ($stats['email_verified'] ?? 0) }}
          · Eerste aanbod {{ (int) ($stats['first_offer'] ?? 0) }}
        </p>

        <p class="beheer-acties">
          <a href="{{ route('beheer.posts.edit', $post) }}">Bewerken</a>
          <form method="post" action="{{ route('beheer.posts.destroy', $post) }}" class="inline-form" onsubmit="return confirm('Post verwijderen?');">
            @csrf
            <button type="submit" class="btn btn-secondary btn-klein">Verwijderen</button>
          </form>
        </p>
      </article>
    @empty
      <p class="muted">Nog geen posts. Voeg de eerste toe om UTM-links te maken.</p>
    @endforelse
  </div>

  {{ $posts->links() }}
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
