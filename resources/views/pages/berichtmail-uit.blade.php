@extends('layouts.site')

@section('title', $title)
@section('meta_description', $metaDescription)
@section('canonical', $canonical)

@section('content')
<section class="section wrap">
  <h1 class="page-title">Mail bij nieuwe berichten</h1>

  @if($klaar)
    <p class="ok" role="status">Klaar. Je krijgt geen mail meer als iemand je een bericht stuurt.</p>
    <p>Je berichten blijven gewoon in je <a href="{{ route('account.inbox') }}">inbox</a> staan. Weer aanzetten kan op je <a href="{{ route('account.profiel') }}">profiel</a>.</p>
  @elseif($uit)
    <p>De mail bij nieuwe berichten staat al uit. Weer aanzetten kan op je <a href="{{ route('account.profiel') }}">profiel</a>.</p>
  @else
    <p>Wil je geen mail meer krijgen als iemand je een bericht stuurt? Je berichten blijven gewoon in je inbox staan.</p>
    <form method="post" action="{{ $actionUrl }}">
      @csrf
      <button class="btn" type="submit">Zet de mail uit</button>
    </form>
  @endif
</section>
@endsection
