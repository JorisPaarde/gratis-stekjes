@extends('layouts.site')

@section('title', $title)
@section('meta_description', $metaDescription)
@section('canonical', $canonical)

@push('head')
  @vite(['resources/css/stektips.css'])
@endpush

@section('content')
<section class="section wrap stektips-page">
  <h1 class="page-title">Stektips</h1>
  <p class="section-intro">Zestien soorten om mee te beginnen. De slagingskans is een eigen indicatie op basis van de bronnen in het plan; die geven geen percentages. Ontbrekende details: <em>Volgt binnenkort</em>.</p>
  <div class="grid-cards">
    @foreach($soorten as $s)
      <a class="soft-card" href="{{ route('stektips.show', ['slug' => $s['slug']]) }}">
        <h3>{{ $s['naam'] }}</h3>
        <p>{{ $s['wanneer'] ?? '' }} · {{ $s['kans'] ?? '' }}</p>
      </a>
    @endforeach
  </div>
  <aside class="soft-card stektips-proef">
    <h3>Joris' eigen proef</h3>
    <p>50 olijfwilgstekken in wilgenwater zijn een mooie eerste case. Zet de helft in vochtige stekgrond in een koude bak, noteer per bakje wat er gebeurt, en publiceer de uitkomst in het voorjaar.</p>
  </aside>
  <p class="stektips-meer"><a href="{{ route('hulpmiddelen') }}">Hulpmiddelen</a></p>
</section>
@endsection
