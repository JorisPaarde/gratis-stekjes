@php
  $isAdmin = \Illuminate\Support\Facades\Gate::allows('see-admin-dashboard');
  $tellers = $isAdmin ? \App\Support\BeheerTellers::alle() : [
    'onbevestigd' => 0, 'mailfout' => 0, 'verenigingen' => 0, 'aanmeldingen' => 0,
    'meldingen' => 0, 'feedback' => 0, 'ervaringen' => 0,
  ];
  $huidig = request()->route()?->getName() ?? '';
  $isRoute = fn (string $r) => $huidig === $r || str_starts_with($huidig, $r.'.');

  // Groepen: hoofdtab → onderdelen [route, label, teller]. Hoofdtab linkt naar het eerste onderdeel met werk.
  $groepen = [
    'keuren' => ['Keuren', [
      ['beheer.verenigingen', 'Verenigingen', $tellers['verenigingen']],
      ['beheer.aanmeldingen', 'Deelplekken', $tellers['aanmeldingen']],
      ['beheer.ervaringen', 'Ervaringen', $tellers['ervaringen']],
    ]],
    'inbox' => ['Inbox', [
      ['beheer.meldingen', 'Meldingen', $tellers['meldingen']],
      ['beheer.feedback', 'Feedback', $tellers['feedback']],
    ]],
    'inhoud' => ['Inhoud', [
      ['beheer.agenda', 'Agenda', 0],
      ['beheer.kaartmarkers', 'Kaartmarkers', 0],
    ]],
  ];
  $groepTab = function (string $key) use ($groepen, $isRoute): array {
      [$label, $items] = $groepen[$key];
      $doel = collect($items)->first(fn ($i) => $i[2] > 0) ?? $items[0];
      $actief = collect($items)->contains(fn ($i) => $isRoute($i[0]));

      return [$doel[0], $label, (int) collect($items)->sum(fn ($i) => $i[2]), $actief];
  };

  $tabs = $isAdmin
    ? [
      ['beheer.index', 'Overzicht', 0, $isRoute('beheer.index')],
      ['beheer.gebruikers', 'Gebruikers', $tellers['onbevestigd'] + $tellers['mailfout'], $isRoute('beheer.gebruikers')],
      $groepTab('keuren'),
      $groepTab('inbox'),
      ['beheer.statistieken', 'Statistieken', 0, $isRoute('beheer.statistieken')],
    ]
    : [
      ['beheer.statistieken', 'Statistieken', 0, $isRoute('beheer.statistieken')],
    ];
  $subGroep = $isAdmin
    ? collect($groepen)->first(fn ($g) => collect($g[1])->contains(fn ($i) => $isRoute($i[0])))
    : null;
@endphp
<div class="beheer-header">
  <p class="beheer-merk">{{ $isAdmin ? 'Beheer' : 'Statistieken' }}</p>
  @guest
    {{-- Alleen bij inloggen met beheerwachtwoord; met een account log je uit via het menu. --}}
    <form method="post" action="{{ route('beheer.logout') }}">
      @csrf
      <button class="btn btn-secondary btn-klein" type="submit">Uitloggen</button>
    </form>
  @endguest
</div>
<nav class="beheer-tabs" aria-label="Beheeronderdelen">
  @foreach($tabs as [$route, $label, $aantal, $actief])
    <a href="{{ route($route) }}" @class(['is-actief' => $actief]) @if($actief) aria-current="page" @endif>{{ $label }}@if($aantal > 0) <span class="nav-badge" aria-label="{{ $aantal }} wacht op actie">{{ $aantal }}</span>@endif</a>
  @endforeach
</nav>
@if($subGroep)
  <nav class="beheer-subtabs" aria-label="{{ $subGroep[0] }}">
    @foreach($subGroep[1] as [$route, $label, $aantal])
      @php $actief = $isRoute($route); @endphp
      <a href="{{ route($route) }}" @class(['is-actief' => $actief]) @if($actief) aria-current="page" @endif>{{ $label }}@if($aantal > 0) <span class="nav-badge">{{ $aantal }}</span>@endif</a>
    @endforeach
  </nav>
@endif

@if(session('status'))
  <p class="flash flash-ok" role="status">{{ session('status') }}</p>
@endif
@foreach(['marker', 'evenement', 'deelplek', 'user'] as $veld)
  @if($errors->has($veld))
    <p class="error" role="alert">{{ $errors->first($veld) }}</p>
  @endif
@endforeach
