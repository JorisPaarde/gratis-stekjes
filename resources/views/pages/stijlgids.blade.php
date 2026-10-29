@extends('layouts.site')

@section('title', 'Stijlgids')
@section('meta_description', 'Interne stijlgids: kleuren en iconen van '.config('site.name'))
@section('canonical', url('/stijlgids'))
@section('body_class', 'page-stijlgids')

@push('head')
  <meta name="robots" content="noindex, nofollow">
@endpush

@section('content')
<section class="section wrap">
  <h1 class="page-title">Stijlgids</h1>
  <p class="section-intro">Interne referentie voor kleuren en iconen. Niet in de navigatie of sitemap; altijd noindex.</p>

  <h2>Kleuren</h2>
  <div class="swatch-grid" style="display:grid;gap:1rem;grid-template-columns:repeat(auto-fit,minmax(10rem,1fr));margin:1rem 0 2rem">
    <div class="soft-card" style="background:#1f4d2b;color:#fff"><strong>Bosgroen</strong><br>#1F4D2B</div>
    <div class="soft-card" style="background:#e6f2e1;color:#1a1210"><strong>Mint</strong><br>#E6F2E1</div>
    <div class="soft-card" style="background:#e8833a;color:#1a1210"><strong>Oranje CTA</strong><br>#E8833A + donkere tekst</div>
    <div class="soft-card" style="background:#1a1210;color:#fff"><strong>Ink</strong><br>#1A1210</div>
  </div>

  <h2>Iconen</h2>
  <p class="muted">Lucide (ISC) + eigen planticonen in dezelfde lijnstijl (24×24, stroke 2).</p>
  <ul class="icon-gallery" style="list-style:none;padding:0;margin:1.5rem 0;display:grid;gap:1rem;grid-template-columns:repeat(auto-fill,minmax(8.5rem,1fr))">
    @foreach([
      'stekje','plant','zaad','boom','locatie','kaart','zoeken','bericht','gebruiker',
      'hart','delen','kalender','ophalen','info','waarschuwing','check','sluiten','menu',
      'filter','plus','uitnodigen','partner',
    ] as $icon)
      <li class="soft-card" style="text-align:center;padding:1rem .75rem">
        <span style="display:inline-flex;color:var(--forest);margin-bottom:.5rem">
          <x-icon :name="$icon" :size="28" />
        </span>
        <div style="font-size:.85rem;font-weight:600">{{ $icon }}</div>
      </li>
    @endforeach
  </ul>

  <p><a class="btn" href="{{ route('home') }}">Naar home</a></p>
</section>
@endsection
