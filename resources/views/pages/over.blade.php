@extends('layouts.site')

@section('title', $title)
@section('meta_description', $metaDescription)
@section('canonical', $canonical)

@section('content')
<section class="section wrap prose">
  <h1>Over {{ config('site.name') }}</h1>
  <p>Ik ben Joris. Ik wil toetsen of mensen stekjes gaan uitdelen en ophalen — gratis, met een praatje als enige tegenprestatie. Eerst gericht op mensen die met voedselbossen en eetbare tuinen bezig zijn.</p>
  <p>De regio is open: heel Nederland. De sitenaam en het adres zijn configureerbaar; dit is de testfase (noindex op staging).</p>
  <p>Volgt binnenkort: meer over mij en dit project.</p>
  <p><a href="{{ route('privacy') }}">Privacyverklaring</a> · <a href="{{ route('account.registreren') }}">Doe mee</a></p>
</section>
@endsection
