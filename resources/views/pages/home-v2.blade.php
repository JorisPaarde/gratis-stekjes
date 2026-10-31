@extends('layouts.site')

@section('title', $title)
@section('meta_description', $metaDescription)
@section('canonical', $canonical)
@section('body_class', 'page-home page-home-v2')

@push('head')
  @vite(['resources/css/home-v2.css'])
@endpush

@section('content')
@php
  $hero = $hero ?? [];
  $heading = (string) ($hero['heading'] ?? 'Gratis planten, stekjes en zaden bij jou in de buurt');
  $tagline = $hero['tagline'] ?? null;
  $lead = $hero['lead'] ?? null;
  $primary = $hero['primary'] ?? ['label' => 'Maak gratis een account', 'route' => 'account.registreren'];
  $secondary = $hero['secondary'] ?? ['label' => 'Open de kaart', 'route' => 'aanbod'];
@endphp

<section class="home-hero" aria-labelledby="hero-titel" data-home-hero>
  <div class="home-hero-media" aria-hidden="true">
    <x-home-picture
      id="hero"
      class="home-hero-pic"
      :eager="true"
      sizes="100vw"
      alt="Volkstuinperkjes met groenten in een buurttuin"
    />
  </div>
  <div class="home-hero-scrim" aria-hidden="true"></div>
  <div class="home-hero-inner">
    <h1 id="hero-titel" class="home-hero-heading">{{ $heading }}</h1>
    @if(!empty($tagline))
      <p class="home-hero-tagline">{{ $tagline }}</p>
    @endif
    @if(!empty($lead))
      <p class="home-hero-lead">{{ $lead }}</p>
    @endif
    <div class="home-hero-actions">
      <a class="btn" href="{{ route($primary['route']) }}" data-hero-primary>
        <x-icon name="plus" :size="18" /> {{ $primary['label'] }}
      </a>
      <a class="btn btn-on-dark" href="{{ route($secondary['route']) }}" data-hero-secondary>
        <x-icon name="kaart" :size="18" /> {{ $secondary['label'] }}
      </a>
    </div>
  </div>
</section>

<div class="hero-wave" aria-hidden="true">
  <img src="{{ asset('assets/illustrations/wave-divider.svg') }}" alt="" width="1440" height="80" decoding="async" loading="lazy">
</div>

<section class="section wrap home-section" id="kaart" aria-labelledby="kaart-titel">
  <div class="home-split">
    <div class="home-split-copy">
      <h2 id="kaart-titel">Wat staat er bij jou in de buurt?</h2>
      <p class="section-intro">Op de kaart zie je gratis planten, stekjes en zaden die mensen weggeven — én deelplekken zoals een plantenasiel, een plantenruil of een zadenbibliotheek. Zoek op postcode of plaats en spreek een ophaalmoment af.</p>
      <p class="home-actions">
        <a class="btn" href="{{ route('aanbod') }}"><x-icon name="kaart" :size="18" /> Open de kaart</a>
        <a class="btn btn-secondary" href="{{ route('deelplekken') }}"><x-icon name="locatie" :size="18" /> Bekijk deelplekken</a>
      </p>
    </div>
    <a class="home-split-media home-kaart-preview" href="{{ route('aanbod') }}">
      <x-home-picture
        id="kaart"
        sizes="(max-width: 768px) 100vw, 50vw"
        alt="Kaart van Nederland en België met oranje markers van deelplekken"
      />
      <span class="home-kaart-label">Naar de kaart met aanbod en deelplekken</span>
    </a>
  </div>
</section>

<section class="section section--mint home-section" id="hoe-werkt-het" aria-labelledby="hoe-titel">
  <div class="wrap">
    <h2 id="hoe-titel">Hoe het werkt</h2>
    <p class="section-intro">In drie korte stappen deel je planten, stekjes en zaden met mensen bij jou in de buurt. Alles blijft gratis; je haalt het fysiek op.</p>
    <ol class="home-steps">
      <li>
        <span class="home-step-num" aria-hidden="true">1</span>
        <div>
          <h3>Maak een account</h3>
          <p>Met één gratis account kun je kijken, berichten sturen en zelf iets aanbieden.</p>
        </div>
      </li>
      <li>
        <span class="home-step-num" aria-hidden="true">2</span>
        <div>
          <h3>Bied planten of zaden aan</h3>
          <p>Zet online wat je overhebt — een stekje, een plant of gratis zaden — met alleen je postcode of plaats, nooit je straatadres.</p>
        </div>
      </li>
      <li>
        <span class="home-step-num" aria-hidden="true">3</span>
        <div>
          <h3>Spreek af en haal op</h3>
          <p>Stuur een bericht via de site, kies samen een moment en wissel een praatje bij de deur.</p>
        </div>
      </li>
    </ol>
    <div class="home-steps-visual">
      <x-home-picture
        id="stappen"
        sizes="(max-width: 768px) 100vw, 33vw"
        alt="Kratten met verse groenten op een markt"
      />
      <x-home-picture
        id="stekjes"
        sizes="(max-width: 768px) 100vw, 33vw"
        alt="Iemand verpot een vrouwentong in een terracotta pot"
      />
      <x-home-picture
        id="zaden"
        sizes="(max-width: 768px) 100vw, 33vw"
        alt="Zakjes groentezaden op een rek"
      />
    </div>
    <p class="home-actions">
      <a class="btn" href="{{ route('account.registreren') }}"><x-icon name="plus" :size="18" /> Maak gratis een account</a>
      <a class="btn btn-secondary" href="{{ auth()->check() ? route('account.aanbod') : route('account.registreren') }}"><x-icon name="plant" :size="18" /> Bied planten of zaden aan</a>
    </p>
  </div>
</section>

<section class="section wrap home-section" id="vermeerdertips" aria-labelledby="tips-titel">
  <h2 id="tips-titel">Vermeerdertips</h2>
  <p class="section-intro">Wil je zelf stekjes maken of zaden winnen? Op de stektips-pagina’s lees je per soort hoe je dat doet — handig vóór je iets deelt of ophaalt.</p>
  <ul class="home-tips-grid">
    @foreach($tips as $tip)
      <li>
        <a class="home-tip-card" href="{{ route('stektips.show', ['slug' => $tip['slug']]) }}">
          @if(!empty($tip['afbeelding']['url']))
            <img
              src="{{ $tip['afbeelding']['url'] }}"
              alt="{{ $tip['afbeelding']['alt'] ?? ('Foto van '.$tip['naam']) }}"
              width="320"
              height="240"
              loading="lazy"
              decoding="async"
            >
          @endif
          <span class="home-tip-name">{{ $tip['naam'] }}</span>
        </a>
      </li>
    @endforeach
  </ul>
  <p class="home-actions">
    <a class="btn btn-secondary" href="{{ route('stektips.index') }}"><x-icon name="stekje" :size="18" /> Alle stektips</a>
  </p>
</section>

<section class="section section--mint home-section" id="organisaties" aria-labelledby="org-titel">
  <div class="wrap">
    <div class="home-split home-split--rev">
      <div class="home-split-copy">
        <h2 id="org-titel">Organisaties en plekken die delen</h2>
        <p class="section-intro">Naast buren die iets weggeven, zijn er plantenasiels, zadenbibliotheken en andere initiatieven waar je planten, stekjes of gratis zaden kunt brengen of meenemen. Ken je een plantenasiel, zadenbibliotheek of ruilgroep? Die zetten we graag op de kaart.</p>
        <ul class="home-org-list">
          @foreach($organisaties as $org)
            <li>
              <a href="{{ route('organisaties.show', ['slug' => $org['slug']]) }}">{{ $org['naam'] }}</a>
              @if(!empty($org['plaats']))
                <span class="muted"> · {{ $org['plaats'] }}</span>
              @endif
            </li>
          @endforeach
        </ul>
        <p class="home-actions">
          <a class="btn btn-secondary" href="{{ route('organisaties.index') }}">Alle organisaties</a>
          <a class="btn btn-secondary" href="{{ route('deelplekken') }}">Deelplekken op de kaart</a>
        </p>
      </div>
      <div class="home-split-media home-org-media">
        <x-home-picture
          id="zadenbibliotheek"
          sizes="(max-width: 768px) 100vw, 45vw"
          alt="Zadenbibliotheek met bakjes zaden in een bibliotheek"
        />
        <x-home-picture
          id="plantenasiel"
          sizes="(max-width: 768px) 100vw, 45vw"
          alt="Kamerplanten in potten bij een raam"
        />
      </div>
    </div>
  </div>
</section>

<section class="section wrap home-section" id="nederland-belgie" aria-labelledby="landen-titel">
  <h2 id="landen-titel">Nederland en België</h2>
  <p class="section-intro">Je mag overal in Nederland meedoen. In Vlaanderen en Brussel kun je planten, stekjes en zaden gratis afhalen bij mensen in de buurt — dezelfde spelregels, dezelfde kaart.</p>
  <div class="home-landen">
    <article class="home-land">
      <x-home-picture
        id="nederland"
        sizes="(max-width: 768px) 100vw, 50vw"
        alt="Buurttuinbed met jonge planten en mulch"
      />
      <h3>Nederland</h3>
      <p>Van Groningen tot Maastricht: bekijk stadspagina’s of zoek direct op de kaart naar planten, stekjes, plantenruil en gratis zaden bij jou in de buurt.</p>
      <p><a href="{{ route('gratis-planten.index') }}">Kies je stad in Nederland</a></p>
    </article>
    <article class="home-land">
      <x-home-picture
        id="belgie"
        sizes="(max-width: 768px) 100vw, 50vw"
        alt="Moestuinbedden met groenten in een ommuurde tuin"
      />
      <h3>België</h3>
      <p>Planten gratis afhalen in Vlaanderen en Brussel: open de kaart, stuur een bericht en spreek een moment af. Ook een zadenbibliotheek of plantenasiel in de buurt? Die staan tussen de deelplekken.</p>
      <p>
        <a href="{{ route('gratis-planten.show', ['stad' => 'antwerpen']) }}">Antwerpen</a>
        · <a href="{{ route('gratis-planten.show', ['stad' => 'brussel']) }}">Brussel</a>
        · <a href="{{ route('aanbod') }}">Hele kaart</a>
      </p>
    </article>
  </div>
  <div class="home-landen-extra">
    <x-home-picture
      id="tuin"
      sizes="(max-width: 768px) 100vw, 50vw"
      alt="Handen vol rode en groene koffiebessen"
    />
    <x-home-picture
      id="delen"
      sizes="(max-width: 768px) 100vw, 50vw"
      alt="Hand met een jong plantje in een kluitje aarde"
    />
  </div>
</section>

<section class="section home-signup" id="meedoen" aria-labelledby="meedoen-titel">
  <div class="wrap home-signup-inner">
    <div class="home-signup-copy">
      <h2 id="meedoen-titel">Doe mee — het is gratis</h2>
      <p>Maak een account en deel planten, stekjes en zaden met mensen bij jou in de buurt. Meer groen, meer contact.</p>
      <p class="home-actions">
        <a class="btn" href="{{ route('account.registreren') }}"><x-icon name="plus" :size="18" /> Maak gratis een account</a>
        <a class="btn btn-on-dark" href="{{ route('aanbod') }}"><x-icon name="kaart" :size="18" /> Open de kaart</a>
      </p>
    </div>
    <div class="home-signup-media" aria-hidden="true">
      <x-home-picture
        id="aanmelden"
        sizes="(max-width: 768px) 100vw, 40vw"
        alt=""
      />
    </div>
  </div>
</section>

<section class="section wrap home-credits" aria-labelledby="credits-titel">
  <h2 id="credits-titel" class="visually-hidden">Fotocredits</h2>
  <p class="muted home-credits-text">
    Foto’s met naamsvermelding:
    buurttuin <a href="https://commons.wikimedia.org/wiki/File:City_gardening_community_garden_plots_Langen%C3%A6s_park_Aarhus_Denmark_01.jpg" rel="noopener noreferrer">Ciara Ní Riain</a>,
    zakjes zaden <a href="https://commons.wikimedia.org/wiki/File:Packets_of_Vegetable_Seeds.jpg" rel="noopener noreferrer">ParentingPatch</a>,
    zadenbibliotheek <a href="https://commons.wikimedia.org/wiki/File:Ausstellungsfl%C3%A4che_und_Saatgutbibliothek.jpg" rel="noopener noreferrer">Stadtbibliothek Neuss</a>,
    tuinbed <a href="https://commons.wikimedia.org/wiki/File:04_18_2022_Garden_bed_Q_Gardens_community_garden_Flatbush_BK_NYC.jpg" rel="noopener noreferrer">Wil540 art</a>,
    moestuin <a href="https://commons.wikimedia.org/wiki/File:Chiswick_House_Kitchen_Garden_-_vegetable_beds.jpg" rel="noopener noreferrer">Chiswick Chap</a>
    (Wikimedia Commons, CC BY-SA);
    kaart © <a href="https://www.openstreetmap.org/copyright" rel="noopener noreferrer">OpenStreetMap</a>-bijdragers.
  </p>
  @php $tipCredits = array_values(array_filter($tips, fn ($t) => empty($t['afbeelding']['is_fallback']) && ! empty($t['afbeelding']['auteur']))); @endphp
  @if(count($tipCredits) > 0)
    <p class="muted home-credits-text">
      Foto’s vermeerdertips via Wikimedia Commons:
      @foreach($tipCredits as $tc)
        {{ $tc['naam'] }}:
        @if(!empty($tc['afbeelding']['bron_url']))
          <a href="{{ $tc['afbeelding']['bron_url'] }}" rel="noopener noreferrer">{{ $tc['afbeelding']['auteur'] }}</a>
        @else
          {{ $tc['afbeelding']['auteur'] }}
        @endif
        @if(!empty($tc['afbeelding']['licentie_url']))
          (<a href="{{ $tc['afbeelding']['licentie_url'] }}" rel="noopener noreferrer license">{{ $tc['afbeelding']['licentie'] }}</a>)
        @elseif(!empty($tc['afbeelding']['licentie']))
          ({{ $tc['afbeelding']['licentie'] }})
        @endif
        @if(! $loop->last) · @endif
      @endforeach
    </p>
  @endif
</section>
@endsection
