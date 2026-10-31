@extends('layouts.site')

@section('title', $title)
@section('meta_description', $metaDescription)
@section('canonical', $canonical)

@section('content')
<section class="section wrap">
  <h1 class="page-title">Ervaring delen</h1>
  <p class="section-intro">Kort en in je eigen woorden (max. {{ $maxBody }} tekens). Optioneel een foto. Plaatsnaam alleen op gemeenteniveau, nooit een straatadres. Ik keur nieuwe ervaringen eerst goed.</p>

  <form class="form soft-card" method="post" action="{{ route('account.ervaringen.store') }}" enctype="multipart/form-data">
    @csrf
    @include('partials.honeypot')
    <input type="hidden" name="form_started" value="{{ $formStarted }}">
    @if($conversationId)
      <input type="hidden" name="conversation_id" value="{{ $conversationId }}">
    @endif

    <label>Jouw ervaring
      <textarea name="body" required maxlength="{{ $maxBody }}" rows="5">{{ old('body') }}</textarea>
    </label>
    @error('body')<p class="error" role="alert">{{ $message }}</p>@enderror

    <label>Plaats of gemeente
      <input type="text" name="plaatsnaam" maxlength="80" value="{{ old('plaatsnaam', $defaultPlaats) }}" placeholder="Bijvoorbeeld Haarlem" autocomplete="address-level2">
    </label>
    <p class="muted" style="margin-top:.25rem">Geen straat of huisnummer. Leeg laten = je profiellocatie.</p>
    @error('plaatsnaam')<p class="error" role="alert">{{ $message }}</p>@enderror

    <label>Koppel aan aanbod (optioneel)
      <select name="aanbod_id">
        <option value="">Geen</option>
        @foreach($aanbodOpties as $a)
          <option value="{{ $a->id }}" @selected((string) old('aanbod_id') === (string) $a->id)>
            {{ $a->type }}: {{ \Illuminate\Support\Str::limit($a->tekst, 60) }}
          </option>
        @endforeach
      </select>
    </label>
    @error('aanbod_id')<p class="error" role="alert">{{ $message }}</p>@enderror

    <label>Plantensoort-id (optioneel, via zoeken)
      <input type="number" name="plantensoort_id" min="1" value="{{ old('plantensoort_id') }}" placeholder="Leeg laten voor geen koppeling">
    </label>
    @error('plantensoort_id')<p class="error" role="alert">{{ $message }}</p>@enderror

    <label>Foto (optioneel, JPEG/PNG/WebP, max. 8 MB)
      <input type="file" name="foto" accept="image/jpeg,image/png,image/webp">
    </label>
    @error('foto')<p class="error" role="alert">{{ $message }}</p>@enderror

    <p style="margin-top:1rem;display:flex;gap:.5rem;flex-wrap:wrap">
      <button class="btn" type="submit">Plaatsen ter goedkeuring</button>
      <a class="btn btn-secondary" href="{{ route('account.ervaringen') }}">Annuleren</a>
    </p>
  </form>
</section>
@endsection
