@extends('layouts.site')

@section('title', $title)
@section('meta_description', $metaDescription)
@section('canonical', $canonical)

@section('content')
<section class="section wrap">
  <h1 class="page-title">Tipmail eerste stekje</h1>

  @if($klaar)
    <p class="ok" role="status">Klaar. Je krijgt geen tipmail meer over je eerste stekje.</p>
    <p>Weer aanzetten kan op je <a href="{{ route('account.profiel') }}">profiel</a>.</p>
  @elseif($uit)
    <p>Die tipmail staat al uit. Weer aanzetten kan op je <a href="{{ route('account.profiel') }}">profiel</a>.</p>
  @else
    <p>Wil je geen tipmail meer als je nog geen aanbod hebt geplaatst?</p>
    <form method="post" action="{{ $actionUrl }}">
      @csrf
      <button class="btn" type="submit">Zet de tipmail uit</button>
    </form>
  @endif
</section>
@endsection
