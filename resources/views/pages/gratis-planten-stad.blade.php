@extends('layouts.site')

@section('title', $title)
@section('meta_description', $metaDescription)
@section('canonical', $canonical)
@section('body_class', 'page-gratis-planten-stad')

@push('head')
  <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" integrity="sha256-p4NxAoJBhIIN+hmNHrzRCf9tD/miZyoHS5obTRR9BMY=" crossorigin="">
  <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js" integrity="sha256-20nQCchB9co0qIjJZRGuk2/Z9VM+kNiyxNV1lvTlZBo=" crossorigin=""></script>
  <script>
    window.STEKJES_STAD = {
      lat: {{ json_encode($stad['lat']) }},
      lng: {{ json_encode($stad['lng']) }},
      straal_km: {{ json_encode($stad['straal_km']) }},
      naam: {{ json_encode($stad['naam']) }},
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

  @if(!empty($plantenasiels))
  <aside class="soft-card gp-plantenasiel" aria-labelledby="plantenasiel-titel">
    <h2 id="plantenasiel-titel">Plantenasiel in {{ $stad['naam'] }}</h2>
    <p>
      @if(count($plantenasiels) === 1)
        In of bij {{ $stad['naam'] }} ken ik {{ $plantenasiels[0]['naam'] }}.
      @else
        In of bij {{ $stad['naam'] }} ken ik {{ count($plantenasiels) }} plantenasiels.
      @endif
      <a href="{{ route('deelplekken', ['plaats' => $stad['naam'], 'type' => 'plantenasiel']) }}">Bekijk plantenasiels bij {{ $stad['naam'] }}</a>
      of het <a href="{{ route('deelplekken', ['type' => 'plantenasiel']) }}">landelijke overzicht plantenasiels</a>.
    </p>
  </aside>
  @endif

  <div class="gp-layout">
    <div>
      <h2 id="aanbod-titel">Actueel aanbod</h2>
      @if(count($aanbod) === 0)
        <div class="gp-empty soft-card" role="status">
          <p>Nog geen openbaar aanbod binnen ~{{ (int) $stad['straal_km'] }} km van {{ $stad['naam'] }}.</p>
          <p>Jij kunt de eerste zijn: zet een stekje of plant online en maak delen in {{ $stad['naam'] }} zichtbaar.</p>
          <p>
            @auth
              <a class="btn" href="{{ route('account.aanbod') }}"><x-icon name="plus" :size="16" /> Zelf aanbod plaatsen</a>
            @else
              <a class="btn" href="{{ route('account.registreren') }}"><x-icon name="plus" :size="16" /> Account maken en aanbieden</a>
            @endauth
          </p>
        </div>
      @else
        <ul class="zoek-lijst gp-aanbod-lijst">
          @foreach($aanbod as $item)
            <li class="zoek-item">
              <h3>{{ $item['voornaam'] ?: 'Iemand' }} · {{ $item['type'] === 'ophalen' ? 'zoekt' : 'deelt' }}</h3>
              <p>{{ $item['tekst'] }}</p>
              <p class="muted">
                {{ $item['locatie_label'] }}
                @if(isset($item['afstand_km']))
                  · ±{{ $item['afstand_km'] }} km
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
          · <a href="{{ route('aanbod') }}">Landelijke kaart</a>
        </p>
      @endif
    </div>

    <div>
      <h2 id="kaart-titel">Kaart rond {{ $stad['naam'] }}</h2>
      <div id="stad-kaart" class="gp-kaart" role="img" aria-label="Kaartuitsnede rond {{ $stad['naam'] }} met benaderde ophaalplekken"></div>
      <p class="muted gp-kaart-note">Benaderde plekken · © OpenStreetMap. Straal ~{{ (int) $stad['straal_km'] }} km.</p>
    </div>
  </div>

  <section class="gp-deelplekken" aria-labelledby="deelplekken-titel">
    <h2 id="deelplekken-titel">
      @if(($stad['country'] ?? 'NL') === 'BE')
        Zadenbibs en deelplekken in of bij {{ $stad['naam'] }}
      @else
        Deelplekken in of bij {{ $stad['naam'] }}
      @endif
    </h2>
    @if(count($deelplekken) > 0)
      <p class="section-intro">
        @if(($stad['country'] ?? 'NL') === 'BE')
          Vaste zadenbibs en andere deelplekken (uit openbare bronnen), naast het aanbod van mens tot mens.
        @else
          Vaste plekken om planten of zaden te delen (uit openbare bronnen), naast het aanbod van mens tot mens.
        @endif
      </p>
      <ul class="gp-deelplek-lijst">
        @foreach($deelplekken as $plek)
          <li>
            <strong>{{ $plek['naam'] }}</strong>
            <span class="muted">
              — {{ $plek['type_label'] }}
              @if($plek['plaats'])
                · {{ $plek['plaats'] }}
              @endif
              @if(isset($plek['afstand_km']))
                · ±{{ $plek['afstand_km'] }} km
              @endif
            </span>
            @if(!empty($plek['bron_url']))
              · <a href="{{ $plek['bron_url'] }}" rel="noopener noreferrer" target="_blank">Bron</a>
            @endif
          </li>
        @endforeach
      </ul>
    @else
      <p class="section-intro">Geen bekende vaste deelplek binnen ~{{ (int) $stad['straal_km'] }} km in mijn openbare lijst. Elders in {{ $stad['provincie'] }} of landelijk wel mogelijk.</p>
    @endif
    <p>
      <a href="{{ route('deelplekken', ['plaats' => $stad['naam']]) }}">Deelplekken bij {{ $stad['naam'] }}</a>
      · <a href="{{ route('deelplekken', ['provincie' => $stad['provincie']]) }}">Heel {{ $stad['provincie'] }}</a>
      · <a href="{{ route('deelplekken') }}">Landelijk overzicht</a>
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
            <span class="muted"> (±{{ $n['afstand_km'] }} km)</span>
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
