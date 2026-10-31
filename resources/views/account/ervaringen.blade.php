@extends('layouts.site')

@section('title', $title)
@section('meta_description', $metaDescription)
@section('canonical', $canonical)

@section('content')
<section class="section wrap">
  <h1 class="page-title">Mijn ervaringen</h1>
  <p class="section-intro">Nieuwe ervaringen wachten op mijn goedkeuring voordat ze publiek zichtbaar zijn. Je mag ze zelf altijd verwijderen.</p>

  <p><a class="btn" href="{{ route('account.ervaringen.create') }}"><x-icon name="plus" :size="18" /> Nieuwe ervaring</a>
    <a class="btn btn-secondary" href="{{ route('account.index') }}">Terug naar account</a></p>

  @if($mine->isEmpty())
    <p class="muted" style="margin-top:1.25rem">Je hebt nog geen ervaring gedeeld.</p>
  @else
    <ul style="list-style:none;padding:0;margin:1.25rem 0 0;display:grid;gap:1rem">
      @foreach($mine as $e)
        <li class="soft-card">
          @if($e->hasPhoto())
            <p style="margin:0 0 .75rem">
              <img src="{{ route('account.ervaringen.foto', $e) }}" alt="" width="480" height="320" loading="lazy" style="width:100%;max-height:12rem;object-fit:cover;border-radius:var(--radius, .75rem)">
            </p>
          @endif
          <p style="margin:0 0 .35rem">{{ $e->body }}</p>
          <p class="muted" style="margin:0;font-size:.9rem">
            {{ $e->plaatsnaam }}
            ·
            @if($e->isPending()) in afwachting
            @elseif($e->isApproved()) goedgekeurd
            @else afgewezen
            @endif
            · {{ $e->created_at?->timezone('Europe/Amsterdam')->format('d-m-Y H:i') }}
          </p>
          <form method="post" action="{{ route('account.ervaringen.destroy', $e) }}" style="margin-top:.75rem" onsubmit="return confirm('Ervaring definitief verwijderen?');">
            @csrf
            @method('DELETE')
            <button class="btn btn-secondary" type="submit">Verwijderen</button>
          </form>
        </li>
      @endforeach
    </ul>
  @endif
</section>
@endsection
