@extends('layouts.site')

@section('title', $title)
@section('meta_description', $metaDescription)
@section('canonical', $canonical)
@section('body_class', 'page-berichten')

@push('head')
  @vite(['resources/css/berichten.css'])
@endpush

@section('content')
@php($andere = $view['andere'])
<section class="section wrap">
  <h1 class="page-title">Gesprek met {{ $andere['voornaam'] }}</h1>

  @if(session('error'))
    <p class="flash-error" role="alert">{{ session('error') }}</p>
  @endif

  <aside class="safety" role="note">
    <strong>Veiligheidstips</strong>
    <p>{{ $view['veiligheidstips'] }}</p>
  </aside>

  <div class="contact-box" style="margin:1rem 0">
    <h2>Verder contact?</h2>
    <p class="muted">Alleen als jullie allebei “ja” kiezen, zien jullie elkaars e-mail{{ !empty($andere['contact_gedeeld']) ? ' (en telefoon als die is ingevuld)' : '' }}. In de chat zelf staan nooit e-mailadressen.</p>
    @if(!empty($view['wederzijds_contact']))
      <p><strong>Contact gedeeld</strong></p>
      <p>E-mail: {{ $andere['email'] ?? '' }}</p>
      @if(!empty($andere['telefoon']))
        <p>Telefoon: {{ $andere['telefoon'] }}</p>
      @endif
    @else
      <p>Jouw keuze nu: {{ $view['mijn_contactkeuze'] ?? 'nog niet gekozen' }}</p>
      <form method="post" action="{{ route('account.gesprek.store', $view['id']) }}" style="display:flex;gap:.5rem;flex-wrap:wrap">
        @csrf
        <input type="hidden" name="action" value="contact">
        <button class="btn" name="choice" value="ja" type="submit">Ik wil wel verder contact</button>
        <button class="btn btn-secondary" name="choice" value="nee" type="submit">Ik wil niet</button>
      </form>
    @endif
  </div>

  <div class="chat">
    @forelse($view['berichten'] as $m)
      <div class="bubble {{ ((int) $m['sender_id'] === $uid) ? 'me' : '' }}">
        <p>{{ $m['body'] }}</p>
        <p class="muted" style="margin:.35rem 0 0;font-size:.85rem">{{ $m['created_at'] }}</p>
      </div>
    @empty
      <p class="muted">Nog geen berichten. Zeg hallo.</p>
    @endforelse
  </div>

  <form class="form" method="post" action="{{ route('account.gesprek.store', $view['id']) }}" style="margin-top:1rem">
    @csrf
    @include('partials.honeypot')
    <input type="hidden" name="action" value="message">
    <label>Nieuw bericht
      <textarea name="body" required maxlength="4000"></textarea>
    </label>
    <button class="btn" type="submit">Verstuur</button>
  </form>

  <details style="margin-top:1.5rem">
    <summary>Melden of blokkeren</summary>
    <form class="form" method="post" action="{{ route('account.gesprek.store', $view['id']) }}">
      @csrf
      <input type="hidden" name="action" value="report">
      <label>Reden melding
        <textarea name="reason" required maxlength="1000"></textarea>
      </label>
      <button class="btn btn-secondary" type="submit">Melden</button>
    </form>
    <form method="post" action="{{ route('account.gesprek.store', $view['id']) }}" style="margin-top:.75rem">
      @csrf
      <input type="hidden" name="action" value="block">
      <button class="btn btn-secondary" type="submit">Blokkeer deze gebruiker</button>
    </form>
  </details>

  <p><a href="{{ route('account.inbox') }}">Terug naar inbox</a></p>
</section>
@endsection
