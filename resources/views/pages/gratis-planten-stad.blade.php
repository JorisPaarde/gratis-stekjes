@extends('layouts.site')

@section('title', $title)
@section('meta_description', $metaDescription)
@section('canonical', $canonical)
@if(!empty($robots))
@section('robots', $robots)
@endif
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

  @if(!empty($relatedOrganisatie))
    <aside class="gp-related-org" aria-label="Gerelateerde organisatie">
      <p class="gp-related-org__intro">{{ $relatedOrganisatie['intro'] }}</p>
      <p class="gp-related-org__link">
        <a href="{{ $relatedOrganisatie['url'] }}">{{ $relatedOrganisatie['anchor'] }}</a>
      </p>
    </aside>
  @endif

  <div class="gp-layout">
    <div>
      @if(!empty($toonAanbod))
        <h2 id="aanbod-titel">Actueel aanbod</h2>
        @include('partials.aanbod-lijst', ['items' => $aanbod])
      @endif
      <p class="gp-cta-inline">
        @auth
          <a class="btn btn-secondary" href="{{ route('account.aanbod') }}">Zelf iets aanbieden</a>
        @else
          <a class="btn btn-secondary" href="{{ route('account.registreren') }}">Zelf iets aanbieden</a>
        @endauth
        · <a href="{{ route('aanbod', ['q' => $stad['naam']]) }}">Zoek verder rond {{ $stad['naam'] }}</a>
      </p>
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
          @php
            $stadPlekKey = 'deelplek:'.($plek['id'] ?? $plek['naam']);
          @endphp
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
            · <button type="button" class="zoek-regel-meer-knop" data-plek-open="{{ $stadPlekKey }}" aria-haspopup="dialog">Meer info</button>
            @if(!empty($plek['bron_url']))
              · <a href="{{ $plek['bron_url'] }}" rel="noopener noreferrer" target="_blank">{{ $plek['bron_label'] ?? 'Bron' }}</a>
            @endif
          </li>
        @endforeach
      </ul>
      <script type="application/json" data-plek-items>{!! json_encode(array_map(function ($plek) {
          return $plek + ['kind' => 'deelplek', 'key' => 'deelplek:'.($plek['id'] ?? $plek['naam'])];
      }, $deelplekken), JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP) !!}</script>
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
