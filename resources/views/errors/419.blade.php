@extends('layouts.site')

@section('title', 'Sessie verlopen')
@section('meta_description', 'Je sessie is verlopen. Probeer het opnieuw.')
@section('robots', 'noindex, follow')

@section('content')
<section class="section wrap prose">
  <h1 class="page-title">Sessie verlopen</h1>
  <p>Je formulier stond te lang open, of de beveiligingstoken klopt niet meer. Dat komt vaker voor in de browser in Facebook of Instagram. Vul het formulier opnieuw in — je gegevens zijn niet opgeslagen.</p>
  <p>
    <a class="btn" href="{{ route('account.registreren') }}">Opnieuw aanmelden</a>
    <a class="btn btn-secondary" href="{{ route('account.inloggen') }}">Inloggen</a>
    <a class="btn btn-secondary" href="{{ route('home') }}">Naar home</a>
  </p>
</section>
@endsection
