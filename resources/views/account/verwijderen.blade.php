@extends('layouts.site')

@section('title', $title)
@section('meta_description', $metaDescription)
@section('canonical', $canonical)

@section('content')
<section class="section wrap prose">
  <h1 class="page-title">Account verwijderen</h1>
  <p>Dit wist je account, aanbod en (indien aanwezig) gesprekken, berichten, blokkades en meldingen die bij jou horen. Dit kun je niet terugdraaien.</p>
  <p>Download eerst eventueel een <a href="{{ route('account.export') }}">gegevensexport (JSON)</a>.</p>
  @if($errors->any())
    <p class="error" role="alert">{{ $errors->first() }}</p>
  @endif
  <form class="form" method="post" action="{{ route('account.verwijderen') }}">
    @csrf
    <label>Typ <strong>verwijder</strong> om te bevestigen
      <input name="bevestig" required autocomplete="off" value="{{ old('bevestig') }}">
    </label>
    <button class="btn" type="submit">Account definitief verwijderen</button>
  </form>
  <p><a href="{{ route('account.index') }}">Annuleren</a></p>
</section>
@endsection
