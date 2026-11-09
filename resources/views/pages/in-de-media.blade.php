@extends('layouts.site')

@section('title', $title)
@section('meta_description', $metaDescription)
@section('canonical', $canonical)

@section('content')
<section class="section wrap in-de-media-page">
  <h1 class="page-title">Gratis planten in de media</h1>
  <p class="section-intro media-intro">
    Soms verschijnt {{ config('site.name') }} in het nieuws — over buren die planten delen,
    een praatje bij de deur, en een beetje meer groen in de buurt. Hier vind je die vermeldingen,
    met een link naar het origineel.
  </p>

  @if($vermeldingen === [])
    <p class="muted" role="status">Er staan nog geen vermeldingen.</p>
  @else
    <ul class="media-kaarten">
      @foreach($vermeldingen as $item)
        <li>
          <a class="media-kaart{{ $item['image'] ? '' : ' media-kaart--zonder-beeld' }}"
             href="{{ $item['url'] }}"
             target="_blank"
             rel="noopener noreferrer">
            @if($item['image'])
              <figure class="media-kaart-beeld">
                <picture>
                  @if($item['image_webp'])
                    <source srcset="{{ $item['image_webp'] }}" type="image/webp">
                  @endif
                  <img src="{{ $item['image'] }}"
                       alt="{{ $item['image_alt'] }}"
                       width="960"
                       height="644"
                       loading="lazy"
                       decoding="async">
                </picture>
                @if($item['image_credit'] !== '')
                  <figcaption class="media-kaart-credit">Beeld: {{ $item['image_credit'] }}</figcaption>
                @endif
              </figure>
            @else
              <div class="media-kaart-accent" aria-hidden="true">
                <span>{{ $item['medium'] }}</span>
              </div>
            @endif
            <div class="media-kaart-body">
              <p class="media-kaart-meta">
                <span class="media-kaart-medium">{{ $item['medium'] }}</span>
                <span class="media-kaart-sep" aria-hidden="true">·</span>
                <time datetime="{{ $item['datum'] }}">{{ $item['datum_label'] }}</time>
              </p>
              <h2 class="media-kaart-kop">{{ $item['kop'] }}</h2>
              <p class="media-kaart-tekst">{{ $item['kaart_tekst'] }}</p>
              <span class="media-kaart-cta">Lees het artikel</span>
            </div>
          </a>
        </li>
      @endforeach
    </ul>
  @endif

  <aside class="media-redactie" aria-labelledby="redactie-titel">
    <h2 id="redactie-titel">Voor redacties</h2>
    <p class="media-redactie-uitnodiging">{{ $redactieUitnodiging }}</p>
    <p>
      Vragen over feiten, interviews of beeld? Mail naar
      <a href="mailto:{{ $contactEmail }}">{{ $contactEmail }}</a>.
    </p>
    <h3 class="media-redactie-sub">Kernfeiten</h3>
    <ul class="media-feiten">
      <li><strong>Gratis</strong> — er wordt niets verkocht; meedoen kost niets.</li>
      <li><strong>Zonder reclame</strong> — geen advertenties en geen meetcode van derden die je volgt.</li>
      <li><strong>Nederland en België</strong> — planten, stekjes en zaden delen bij jou in de buurt; je haalt alles zelf op, de enige tegenprestatie is een praatje.</li>
      <li><strong>Bereik</strong> — {{ $redactieBereik }}</li>
      <li>
        <strong>Deelplekken en verenigingen</strong> —
        er staan steeds meer vaste deelplekken op de kaart
        (zadenbibliotheken, plantenasiels, plantenbiebs en meer),
        en tuinverenigingen kunnen meedoen via
        <a href="{{ route('tuinverenigingen') }}">tuinverenigingen</a>.
        Zie ook <a href="{{ route('deelplekken') }}">deelplekken</a>.
      </li>
    </ul>
    @if($logoUrl !== null || $ogImageUrl !== null)
      <p class="media-downloads">
        <strong>Beeld voor publicatie</strong>
        @if($logoUrl !== null)
          · <a href="{{ $logoUrl }}" download>Logo (PNG)</a>
        @endif
        @if($ogImageUrl !== null)
          · <a href="{{ $ogImageUrl }}" download>Social image (JPG)</a>
        @endif
      </p>
    @endif
  </aside>

  <div class="media-meedoen" aria-labelledby="media-meedoen-titel">
    <h2 id="media-meedoen-titel">Doe je mee?</h2>
    <p>Heb je zelf iets over, of zoek je een stekje in de buurt? Maak gratis een account — weggeven en ophalen kan met hetzelfde account.</p>
    <p class="media-meedoen-acties">
      <x-word-lid-cta content="in-de-media" :from="request()->getPathInfo()" />
      <a class="btn btn-secondary" href="{{ route('hoe-werkt-het') }}">Hoe werkt het</a>
    </p>
  </div>
</section>
@endsection
