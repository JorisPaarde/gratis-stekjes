@extends('layouts.site')

@section('title', $title)
@section('meta_description', $metaDescription)
@section('canonical', $canonical)
@section('og_image', '/assets/og/gratis-planten.jpg')
@section('body_class', 'page-gratis-planten')

@push('head')
  @vite(['resources/css/gratis-planten.css'])
@endpush

@section('content')
<section class="section wrap gratis-planten-index">
  <h1 class="page-title">{{ $title }}</h1>
  <p class="section-intro">Kies je stad en zie wat buren gratis delen. Je haalt planten, stekjes en zaden op, met een praatje als enige prijs. In België vind je ook een <strong>zadenbib</strong>.</p>

  <div class="gp-zoek" data-gp-zoek>
    <label for="gp-zoek-input">Zoek je plaats</label>
    <input id="gp-zoek-input" type="search" autocomplete="off" placeholder="Bijvoorbeeld Haarlem of Gent" data-gp-zoek-input>
    <p class="gp-sprong">Ga naar
      @foreach(array_keys($byLand) as $land)
        <a href="#land-{{ \Illuminate\Support\Str::slug($land) }}">{{ $land }}</a>@if(! $loop->last) · @endif
      @endforeach
    </p>
    <p class="gp-zoek-leeg" data-gp-zoek-leeg hidden>
      Staat jouw plaats er niet bij? <a href="{{ route('aanbod') }}" data-gp-zoek-kaart>Zoek op de kaart</a>; daar vind je aanbod in heel Nederland en België.
    </p>
  </div>

  @foreach($byLand as $land => $byProvincie)
    <section class="gp-land" aria-labelledby="land-{{ \Illuminate\Support\Str::slug($land) }}">
      <h2 id="land-{{ \Illuminate\Support\Str::slug($land) }}">{{ $land }}</h2>
      @foreach($byProvincie as $provincie => $steden)
        <section class="gp-provincie" aria-labelledby="prov-{{ \Illuminate\Support\Str::slug($land.'-'.$provincie) }}">
          <h3 id="prov-{{ \Illuminate\Support\Str::slug($land.'-'.$provincie) }}">{{ $provincie }}</h3>
          <ul class="gp-stad-lijst">
            @foreach($steden as $s)
              <li>
                <a href="{{ route('gratis-planten.show', ['stad' => $s['slug']]) }}" title="Gratis planten in {{ $s['naam'] }}">{{ $s['naam'] }}</a>
              </li>
            @endforeach
          </ul>
        </section>
      @endforeach
    </section>
  @endforeach

  <p class="muted gp-index-cta">Staat jouw plaats er niet bij of wil je in heel het land zoeken? Ga naar het <a href="{{ route('aanbod') }}">aanbod op de kaart</a>
    @auth
      of <a href="{{ route('account.aanbod') }}">bied zelf iets aan</a>.
    @else
      of <a href="{{ route('account.registreren') }}">maak een account</a> om zelf iets aan te bieden.
    @endauth
  </p>
</section>
@endsection

@push('scripts')
<script>
  (function () {
    var root = document.querySelector('[data-gp-zoek]');
    if (!root) return;
    var input = root.querySelector('[data-gp-zoek-input]');
    var leeg = root.querySelector('[data-gp-zoek-leeg]');
    var kaart = root.querySelector('[data-gp-zoek-kaart]');
    var kaartBasis = kaart.getAttribute('href');
    var norm = function (t) { return t.toLowerCase().normalize('NFD').replace(/[\u0300-\u036f]/g, '').trim(); };
    input.addEventListener('input', function () {
      var q = norm(input.value);
      var treffers = 0;
      document.querySelectorAll('.gp-provincie').forEach(function (prov) {
        var zichtbaar = 0;
        prov.querySelectorAll('.gp-stad-lijst li').forEach(function (li) {
          var match = q === '' || norm(li.textContent).indexOf(q) !== -1;
          li.hidden = !match;
          if (match) zichtbaar++;
        });
        prov.hidden = zichtbaar === 0;
        treffers += zichtbaar;
      });
      document.querySelectorAll('.gp-land').forEach(function (land) {
        land.hidden = land.querySelectorAll('.gp-provincie:not([hidden])').length === 0;
      });
      leeg.hidden = q === '' || treffers > 0;
      kaart.setAttribute('href', q === '' ? kaartBasis : kaartBasis + '?q=' + encodeURIComponent(input.value.trim()));
    });
  })();
</script>
@endpush
