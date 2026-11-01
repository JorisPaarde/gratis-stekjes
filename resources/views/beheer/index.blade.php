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

  <h1 class="page-title">Overzicht</h1>

  @php
    $t = \App\Support\BeheerTellers::alle();
    $kaarten = [
      ['Onbevestigd, ouder dan '.\App\Support\BeheerTellers::ONBEVESTIGD_DAGEN.' dagen', $t['onbevestigd'], route('beheer.gebruikers', ['filter' => 'onbevestigd']), 'Stuur een herinnering'],
      ['Mail mislukt (7 dagen)', $t['mailfout'], route('beheer.gebruikers', ['filter' => 'mailfout']), 'Bekijk wie'],
      ['Verenigingen wachten', $t['verenigingen'], route('beheer.verenigingen'), 'Goedkeuren'],
      ['Deelplek-aanmeldingen', $t['aanmeldingen'], route('beheer.aanmeldingen'), 'Bekijken'],
      ['Open meldingen', $t['meldingen'], route('beheer.meldingen'), 'Afhandelen'],
      ['Ervaringen wachten', $t['ervaringen'], route('beheer.ervaringen'), 'Beoordelen'],
      ['Ongelezen feedback', $t['feedback'], route('beheer.feedback'), 'Lezen'],
      ['Naam is e-mailadres', $t['naam_email'], route('beheer.gebruikers', ['filter' => 'naam_email']), 'Bekijk wie'],
    ];
    $open = array_values(array_filter($kaarten, fn ($k) => $k[1] > 0));
  @endphp

  <h2>Aandacht nodig</h2>
  @if($open === [])
    <p class="beheer-alles-goed">Niets dat op je wacht. Alles is bijgewerkt.</p>
  @else
    <ul class="beheer-kaarten">
      @foreach($open as [$label, $aantal, $url, $actie])
        <li>
          <a href="{{ $url }}" class="beheer-kaart">
            <span class="beheer-kaart-getal">{{ $aantal }}</span>
            <span class="beheer-kaart-label">{{ $label }}</span>
            <span class="beheer-kaart-actie">{{ $actie }} →</span>
          </a>
        </li>
      @endforeach
    </ul>
  @endif

  <h2>In het kort</h2>
  <ul class="beheer-kort">
    <li><strong>{{ $kort['leden'] }}</strong> leden <span class="muted">({{ $kort['nieuw_week'] }} nieuw deze week)</span></li>
    <li><strong>{{ $kort['actief_week'] }}</strong> actief in de afgelopen 7 dagen</li>
    <li><strong>{{ $kort['aanbod_actief'] }}</strong> actief aanbod</li>
    <li><strong>{{ $kort['gesprekken_week'] }}</strong> {{ $kort['gesprekken_week'] === 1 ? 'nieuw gesprek' : 'nieuwe gesprekken' }} deze week</li>
  </ul>

  <div class="beheer-twee">
    <div>
      <h2>Nieuwste leden</h2>
      @include('beheer.partials.gebruikers-mini', ['rijen' => $nieuwste])
      <p><a href="{{ route('beheer.gebruikers') }}">Alle gebruikers →</a></p>
    </div>
    <div>
      <h2>Laatste mailfouten</h2>
      @if($mailfouten->isEmpty())
        <p class="muted">Geen mislukte mails in de afgelopen 7 dagen.</p>
      @else
        <ul class="beheer-lijst">
          @foreach($mailfouten as $m)
            <li>
              @if($m->user)<a href="{{ route('beheer.gebruikers.show', $m->user) }}">{{ $m->user->name }}</a>@else<span class="muted">verwijderd lid</span>@endif
              · {{ \App\Support\MailLabels::type($m->type) }}
              · <span class="status status--fout">{{ \App\Support\MailLabels::fout($m->error) }}</span>
              <span class="muted">· {{ $m->created_at?->diffForHumans() }}</span>
            </li>
          @endforeach
        </ul>
      @endif
    </div>
  </div>
</section>
@endsection
