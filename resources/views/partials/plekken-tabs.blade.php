{{-- Tabs voor «Plekken & initiatieven»: deelplekken, agenda en initiatieven. --}}
@php
  $plekkenTabs = [
      ['deelplekken', 'Deelplekken', 'locatie'],
      ['agenda', 'Agenda', 'kalender'],
      ['partners', 'Initiatieven', 'delen'],
  ];
@endphp
<nav class="plekken-tabs" aria-label="Plekken en initiatieven">
  @foreach($plekkenTabs as [$route, $label, $icoon])
    <a href="{{ route($route) }}" @class(['is-actief' => request()->routeIs($route)]) @if(request()->routeIs($route)) aria-current="page" @endif><x-icon :name="$icoon" :size="16" /> {{ $label }}</a>
  @endforeach
</nav>
