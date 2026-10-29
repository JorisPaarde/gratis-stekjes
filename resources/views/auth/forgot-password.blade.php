@extends('layouts.site')

@section('title', $title)
@section('meta_description', $metaDescription)
@section('canonical', $canonical)

@section('content')
<section class="section wrap">
  <h1 class="page-title">Wachtwoord vergeten</h1>
  <p class="section-intro">Vul je e-mailadres in. Als het bij mij bekend is, stuur ik een link om een nieuw wachtwoord in te stellen.</p>

  @if($errors->any())
    <p class="error" role="alert">{{ $errors->first() }}</p>
  @endif

  <form class="form" method="post" action="{{ route('account.wachtwoord.vergeten') }}">
    @csrf
    @include('partials.honeypot')

    <label>E-mail
      <input type="email" name="email" required maxlength="254" autocomplete="email" value="{{ old('email') }}">
    </label>
    <button class="btn" type="submit">Stuur resetlink</button>
  </form>

  <p class="muted"><a href="{{ route('account.inloggen') }}">Terug naar inloggen</a></p>
</section>
@endsection
