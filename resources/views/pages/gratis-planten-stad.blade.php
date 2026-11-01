@extends('layouts.site')

@section('title', $title)
@section('meta_description', $metaDescription)
@section('canonical', $canonical)
@section('og_image', is_file(public_path('assets/og/stad/'.$stad['slug'].'.jpg')) ? '/assets/og/stad/'.$stad['slug'].'.jpg' : '/assets/og/gratis-planten.jpg')
@section('body_class', 'page-gratis-planten-stad')

@push('head')
  <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" integrity="sha256-p4NxAoJBhIIN+hmNHrzRCf9tD/miZyoHS5obTRR9BMY=" crossorigin="">
  <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js" integrity="sha256-20nQCchB9co0qIjJZRGuk2/Z9VM+kNiyxNV1lvTlZBo=" crossorigin=""></script>
  <script>
    window.STEKJES_STAD = {
      lat: @json($stad['lat']),
      lng: @json($stad['lng']),
      straal_km: @json($stad['straal_km']),
      naam: @json($stad['naam']),
      aanbod: @json($aanbod),
      deelplekken: @json($deelplekken)
    };
  </script>
  @vite(['resources/css/gratis-planten.css', 'resources/css/aanbod.css'])
@endpush

@section('content')
<section class="section wrap gratis-planten-stad">
  <p class="gp-breadcrumb"><a href="{{ route('gratis-planten.index') }}">Alle steden</a> · {{ $stad['provincie'] }}</p>
  <h1 class="page-title">{{ $title }}</h1>
  <p class="section-intro">{{ $stad['intro'] }}</p>

  <div class="gp-layout">
    <div>
      <h2 id="aanbod-titel">Actueel aanbod</h2>
      @if(count($aanbod) === 0)
        <div class="gp-empty soft-card" role="status">
          <p>Er is nog geen aanbod binnen ongeveer {{ (int) $stad['straal_km'] }} km van {{ $stad['naam'] }}.</p>
          <p>Zoek iets verder weg, of wees de eerste: zet een plant, stekje of zaad op de site en laat zien dat er in {{ $stad['naam'] }} gedeeld wordt.</p>
          <p class="gp-acties">
            <a class="btn" href="{{ route('aanbod', ['q' => $stad['naam']]) }}"><x-icon name="kaart" :size="16" /> Zoek in de omgeving</a>
            @auth
              <a class="btn btn-secondary" href="{{ route('account.aanbod') }}"><x-icon name="plus" :size="16" /> Zelf iets aanbieden</a>
            @else
              <a class="btn btn-secondary" href="{{ route('account.registreren') }}"><x-icon name="plus" :size="16" /> Account maken en aanbieden</a>
            @endauth
          </p>
        </div>
      @else
        <ul class="zoek-lijst gp-aanbod-lijst">
          @foreach($aanbod as $item)
            <li class="zoek-item">
              <h3>{{ $item['voornaam'] ?: 'Iemand' }} · {{ $item['type'] === 'ophalen' ? 'zoekt' : 'deelt' }}</h3>
              @include('partials.lid-van', ['verenigingen' => $item['verenigingen'] ?? []])
              <p>{{ $item['tekst'] }}</p>
              <p class="muted">
                {{ $item['locatie_label'] }}
                @if(isset($item['afstand_km']))
                  · ongeveer {{ $item['afstand_km'] }} km van het centrum
                @endif
              </p>
              @auth
                <p><a href="{{ route('account.bericht-starten', ['aanbod_id' => $item['id'], 'user_id' => $item['user_id']]) }}">Bericht sturen</a></p>
              @else
                <p class="muted"><a href="{{ route('account.inloggen') }}">Log in</a> om een bericht te sturen.</p>
              @endauth
            </li>
          @endforeach
        </ul>
        <p class="gp-cta-inline">
          @auth
            <a class="btn btn-secondary" href="{{ route('account.aanbod') }}">Zelf iets aanbieden</a>
          @else
            <a class="btn btn-secondary" href="{{ route('account.registreren') }}">Zelf iets aanbieden</a>
          @endauth
          · <a href="{{ route('aanbod', ['q' => $stad['naam']]) }}">Zoek verder rond {{ $stad['naam'] }}</a>
        </p>
      @endif
    </div>

    <div>
      <h2 id="kaart-titel">Kaart rond {{ $stad['naam'] }}</h2>
      <div id="stad-kaart" class="gp-kaart" role="img" aria-label="Kaart rond {{ $stad['naam'] }} met ongeveer de plekken waar je iets kunt ophalen"></div>
      <p class="muted gp-kaart-note">
        <span class="gp-legenda"><span class="partner-marker-dot gp-legenda-aanbod" aria-hidden="true"></span> Aanbod van leden</span>
        <span class="gp-legenda"><span class="partner-marker-dot--deelplek" aria-hidden="true"></span> Vaste deelplek</span>
        <br>De kaart toont ook vaste deelplekken, dus je kunt markers zien als er nog geen aanbod is. Plekken zijn bij benadering, binnen ongeveer {{ (int) $stad['straal_km'] }} km van het centrum · © OpenStreetMap.
      </p>
    </div>
  </div>

  <section class="gp-deelplekken" aria-labelledby="deelplekken-titel">
    <h2 id="deelplekken-titel">
      @if(($stad['country'] ?? 'NL') === 'BE')
        Zadenbibs en deelplekken in of bij {{ $stad['naam'] }}
      @elseif(!empty($plantenasiels))
        Plantenasiel en deelplekken in of bij {{ $stad['naam'] }}
      @else
        Deelplekken in of bij {{ $stad['naam'] }}
      @endif
    </h2>
    @if(count($deelplekken) > 0)
      <p class="section-intro">
        @if(($stad['country'] ?? 'NL') === 'BE')
          Vaste zadenbibs en andere deelplekken, naast het aanbod van mens tot mens. De gegevens komen uit openbare bronnen.
        @else
          Vaste plekken om planten of zaden te delen, naast het aanbod van mens tot mens. De gegevens komen uit openbare bronnen.
        @endif
      </p>
      <ul class="gp-deelplek-lijst">
        @foreach($deelplekken as $plek)
          <li>
            <strong>{{ $plek['naam'] }}</strong>
            <span class="muted">
              {{ $plek['type_label'] }}
              @if($plek['plaats'])
                · {{ $plek['plaats'] }}
              @endif
              @if(isset($plek['afstand_km']))
                · ongeveer {{ $plek['afstand_km'] }} km van het centrum
              @endif
            </span>
            @if(!empty($plek['bron_url']))
              · <a href="{{ $plek['bron_url'] }}" rel="noopener noreferrer" target="_blank">Bron</a>
            @endif
          </li>
        @endforeach
      </ul>
    @else
      <p class="section-intro">Ik ken geen vaste deelplek binnen ongeveer {{ (int) $stad['straal_km'] }} km. Misschien wel elders in {{ $stad['provincie'] }} of in de rest van het land.</p>
    @endif
    <p>
      <a href="{{ route('deelplekken', ['plaats' => $stad['naam']]) }}">Deelplekken bij {{ $stad['naam'] }}</a>
      @if(!empty($plantenasiels))
        · <a href="{{ route('deelplekken', ['plaats' => $stad['naam'], 'type' => 'plantenasiel']) }}">Plantenasiels bij {{ $stad['naam'] }}</a>
      @endif
      · <a href="{{ route('deelplekken', ['provincie' => $stad['provincie']]) }}">Alle deelplekken in de provincie {{ $stad['provincie'] }}</a>
      · <a href="{{ route('deelplekken') }}">Alle deelplekken</a>
    </p>
  </section>

  @if(count($nearby) > 0)
    <section class="gp-nearby" aria-labelledby="nearby-titel">
      <h2 id="nearby-titel">Nabije steden</h2>
      <ul class="gp-stad-lijst gp-stad-lijst--inline">
        @foreach($nearby as $n)
          <li>
            <a href="{{ route('gratis-planten.show', ['stad' => $n['slug']]) }}">
              @if(($n['country'] ?? 'NL') === 'BE')
                Gratis planten, stekjes en zaden in {{ $n['naam'] }}
              @else
                Gratis planten en stekjes in {{ $n['naam'] }}
              @endif
            </a>
            <span class="muted"> (ongeveer {{ $n['afstand_km'] }} km)</span>
          </li>
        @endforeach
      </ul>
      <p><a href="{{ route('gratis-planten.index') }}">Alle steden</a></p>
    </section>
  @endif

  <section class="gp-faq" aria-labelledby="faq-titel">
    <h2 id="faq-titel">Veelgestelde vragen</h2>
    <div class="gp-faq-list">
      @foreach($faq as $item)
        <details class="gp-faq-item">
          <summary>{{ $item['vraag'] }}</summary>
          <p>{{ $item['antwoord'] }}</p>
        </details>
      @endforeach
    </div>
  </section>
</section>
@endsection

@push('scripts')
  @vite(['resources/js/stad-kaart.js'])
@endpush
