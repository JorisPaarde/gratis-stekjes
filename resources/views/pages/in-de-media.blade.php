@extends('layouts.site')

@section('title', $title)
@section('meta_description', $metaDescription)
@section('canonical', $canonical)

@section('content')
<section class="section wrap in-de-media-page">
  <h1 class="page-title">Gratis planten in de media</h1>

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
              @if($item['samenvatting'] !== '')
                <p class="media-kaart-tekst">{{ $item['samenvatting'] }}</p>
              @endif
              <span class="media-kaart-cta">Lees het artikel</span>
            </div>
          </a>
        </li>
      @endforeach
    </ul>
  @endif
</section>
@endsection
