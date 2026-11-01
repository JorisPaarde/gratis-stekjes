@if($rijen->isEmpty())
  <p class="muted">Nog geen leden.</p>
@else
  <ul class="beheer-lijst">
    @foreach($rijen as $u)
      <li>
        <a href="{{ route('beheer.gebruikers.show', $u) }}">{{ $u->name }}</a>
        @if($u->locatie_label) <span class="muted">· {{ $u->locatie_label }}</span>@endif
        · @include('beheer.partials.status', ['u' => $u])
        <span class="muted">· {{ $u->created_at?->diffForHumans() }}</span>
      </li>
    @endforeach
  </ul>
@endif
