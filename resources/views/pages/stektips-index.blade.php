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
  <p class="section-intro">{{ $soortenCount }} soorten: eetbaar, kruiden, sier- en kamerplanten. De moeilijkheid is een eigen indicatie; bronnen geven geen percentages. Ontbrekende details volgen later (lege rijen worden niet getoond). Soorten van de EU-Unielijst invasieve exoten horen hier niet tussen.</p>
  <div class="grid-cards">
    @foreach($soorten as $s)
      <a class="soft-card" href="{{ route('stektips.show', ['slug' => $s['slug']]) }}">
        <h3>{{ $s['naam'] }}</h3>
        <p>{{ $s['wanneer'] ?? '' }} · {{ $s['kans'] ?? '' }}@if(!empty($s['categorie'])) · {{ $s['categorie'] }}@endif</p>
      </a>
    @endforeach
  </div>
  <aside class="soft-card stektips-proef">
    <h3><a href="{{ route('stektips.show', ['slug' => 'herfstolijfwilg']) }}">Joris' eigen proef</a></h3>
    <p>50 stekken van de <a href="{{ route('stektips.show', ['slug' => 'herfstolijfwilg']) }}">herfstolijfwilg</a> in wilgenwater zijn een mooie eerste case. Zet de helft in vochtige stekgrond in een koude bak, noteer per bakje wat er gebeurt, en publiceer de uitkomst in het voorjaar.</p>
  </aside>
  <p class="stektips-meer"><a href="{{ route('hulpmiddelen') }}">Hulpmiddelen</a></p>
</section>
@endsection
