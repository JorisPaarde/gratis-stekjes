@php
  $tellers = \App\Support\BeheerTellers::alle();
  $tabs = [
    ['beheer.index', 'Overzicht', 0],
    ['beheer.gebruikers', 'Gebruikers', $tellers['onbevestigd'] + $tellers['mailfout']],
    ['beheer.verenigingen', 'Verenigingen', $tellers['verenigingen']],
    ['beheer.aanmeldingen', 'Aanmeldingen', $tellers['aanmeldingen']],
    ['beheer.meldingen', 'Meldingen', $tellers['meldingen']],
    ['beheer.ervaringen', 'Ervaringen', $tellers['ervaringen']],
    ['beheer.feedback', 'Feedback', $tellers['feedback']],
    ['beheer.agenda', 'Agenda', 0],
    ['beheer.kaartmarkers', 'Kaartmarkers', 0],
    ['beheer.statistieken', 'Statistieken', 0],
  ];
  $huidig = request()->route()?->getName() ?? '';
@endphp
<div class="beheer-header">
  <p class="beheer-merk">Beheer</p>
  @guest
    {{-- Alleen bij inloggen met beheerwachtwoord; met een account log je uit via het menu. --}}
    <form method="post" action="{{ route('beheer.logout') }}">
      @csrf
      <button class="btn btn-secondary btn-klein" type="submit">Uitloggen</button>
    </form>
  @endguest
</div>
<nav class="beheer-tabs" aria-label="Beheeronderdelen">
  @foreach($tabs as [$route, $label, $aantal])
    @php $actief = $huidig === $route || str_starts_with($huidig, $route.'.'); @endphp
    <a href="{{ route($route) }}" @class(['is-actief' => $actief]) @if($actief) aria-current="page" @endif>{{ $label }}@if($aantal > 0) <span class="nav-badge" aria-label="{{ $aantal }} wacht op actie">{{ $aantal }}</span>@endif</a>
  @endforeach
</nav>

@if(session('status'))
  <p class="flash flash-ok" role="status">{{ session('status') }}</p>
@endif
@foreach(['marker', 'evenement', 'deelplek', 'user'] as $veld)
  @if($errors->has($veld))
    <p class="error" role="alert">{{ $errors->first($veld) }}</p>
  @endif
@endforeach
