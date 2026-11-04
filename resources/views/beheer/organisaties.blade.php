@extends('layouts.site')

@section('title', $title)
@section('meta_description', $metaDescription)
@section('canonical', $canonical)

@push('head')
  @vite(['resources/css/admin.css'])
  <meta name="robots" content="noindex, nofollow">
@endpush

@section('content')
<section class="section wrap beheer-page">
  @include('beheer.partials.nav')

  <h1 class="page-title">Organisaties — initiatiefpagina’s</h1>
  <p class="muted">Per organisatie kun je een publieke pagina op <code>/slug</code> zetten. Lege slug = geen initiatiefpagina (gewone /organisaties/… blijft).</p>

  <div class="beheer-table-wrap">
    <table class="beheer-table">
      <thead>
        <tr><th>Organisatie</th><th>Initiatief-slug</th><th>Pagina</th><th></th></tr>
      </thead>
      <tbody>
        @foreach($rows as $row)
          @php
            $org = $row['org'];
            $init = $row['init'];
            $slug = $init?->initiatief_slug;
          @endphp
          <tr>
            <td data-label="Organisatie">{{ $org['naam'] }}</td>
            <td data-label="Slug">{{ $slug ?: '—' }}</td>
            <td data-label="Pagina">
              @if($slug)
                <a href="{{ route('initiatief.show', ['slug' => $slug]) }}">/{{ $slug }}</a>
              @else
                <a href="{{ route('organisaties.show', ['slug' => $org['slug']]) }}">/organisaties/{{ $org['slug'] }}</a>
              @endif
            </td>
            <td class="beheer-actions">
              <a href="{{ route('beheer.organisaties.edit', ['organisatieSlug' => $org['slug']]) }}">Bewerken</a>
            </td>
          </tr>
        @endforeach
      </tbody>
    </table>
  </div>
</section>
@endsection
