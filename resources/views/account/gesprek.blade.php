@extends('layouts.site')

@section('title', $title)
@section('meta_description', $metaDescription)
@section('canonical', $canonical)
@section('body_class', 'page-berichten page-gesprek')

@push('head')
  @vite(['resources/css/berichten.css'])
@endpush

@section('content')
<script>
  if ('scrollRestoration' in history) { history.scrollRestoration = 'manual'; }
  window.scrollTo(0, 0);
</script>
@php($andere = $view['andere'])
<section class="gesprek wrap" data-gesprek>
  <header class="gesprek-top">
    <p class="gesprek-terug"><a href="{{ route('account.inbox') }}">Terug naar inbox</a></p>
    <h1 class="page-title gesprek-titel">Gesprek met {{ $andere['voornaam'] }}</h1>
    @if(!empty($andere['locatie_label']))
      <p class="muted gesprek-meta">{{ $andere['voornaam'] }} woont in de buurt van {{ $andere['locatie_label'] }}.</p>
    @endif

    @if(session('error'))
      <p class="flash-error" role="alert">{{ session('error') }}</p>
    @endif
  </header>

  <form class="gesprek-composer" method="post" action="{{ route('account.gesprek.store', $view['id']) }}" data-gesprek-composer>
    @csrf
    @include('partials.honeypot')
    <input type="hidden" name="action" value="message">
    <label class="visually-hidden" for="gesprek-bericht">Nieuw bericht</label>
    <textarea
      id="gesprek-bericht"
      name="body"
      required
      maxlength="4000"
      rows="2"
      placeholder="Schrijf een bericht"
      enterkeyhint="send"
      autofocus
      data-gesprek-input
    ></textarea>
    <button class="btn" type="submit">Verstuur</button>
  </form>

  <div class="chat" data-gesprek-chat>
    @forelse($view['berichten'] as $m)
      <div class="bubble {{ ((int) $m['sender_id'] === $uid) ? 'me' : '' }}">
        <p>{{ $m['body'] }}</p>
        <p class="muted" style="margin:.35rem 0 0;font-size:.85rem">{{ $m['created_at'] }}</p>
      </div>
    @empty
      <p class="muted gesprek-leeg">Nog geen berichten. Zeg hallo.</p>
    @endforelse
  </div>

  <details class="gesprek-meer" data-gesprek-meer>
    <summary class="gesprek-meer-knop">Tips, contact en instellingen</summary>
    <div class="gesprek-meer-inhoud">
      <details class="gesprek-panel">
        <summary>Veiligheidstips</summary>
        <p>{{ $view['veiligheidstips'] }}</p>
      </details>

      <details class="gesprek-panel" @if(!empty($view['wederzijds_contact'])) open @endif>
        <summary>Verder contact?</summary>
        <p class="muted">Alleen als jullie allebei “ja” kiezen, zien jullie elkaars e-mail, en het adres en telefoonnummer als die zijn ingevuld en gedeeld worden. In de chat zelf staan nooit e-mailadressen.</p>
        @if(!empty($view['wederzijds_contact']))
          <p><strong>Contact gedeeld</strong></p>
          <p>E-mail: {{ $andere['email'] ?? '' }}</p>
          @if(!empty($andere['telefoon']))
            <p>Telefoon: {{ $andere['telefoon'] }}</p>
          @endif
          @if(!empty($andere['adres']))
            <p>Adres: {{ $andere['adres'] }}
              · <a href="https://www.openstreetmap.org/search?query={{ urlencode($andere['adres']) }}" rel="noopener noreferrer" target="_blank">Op de kaart</a></p>
          @endif
          @if(!empty($view['overdracht_afgerond']))
            <p class="muted" style="margin-top:.75rem">Overdracht afgerond.
              <a href="{{ route('account.ervaringen.create', ['gesprek' => $view['id']]) }}">Deel een ervaring</a>
            </p>
          @else
            <form method="post" action="{{ route('account.gesprek.store', $view['id']) }}" style="margin-top:.75rem">
              @csrf
              <input type="hidden" name="action" value="overdracht">
              <button class="btn btn-secondary" type="submit">Ik heb de overdracht afgerond</button>
            </form>
          @endif
        @else
          <p>Jouw keuze nu: {{ $view['mijn_contactkeuze'] ?? 'nog niet gekozen' }}</p>
          <form method="post" action="{{ route('account.gesprek.store', $view['id']) }}" class="gesprek-keuzes">
            @csrf
            <input type="hidden" name="action" value="contact">
            <button class="btn" name="choice" value="ja" type="submit">Ik wil wel verder contact</button>
            <button class="btn btn-secondary" name="choice" value="nee" type="submit">Ik wil niet</button>
          </form>
        @endif
      </details>

      <details class="gesprek-panel">
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
    </div>
  </details>
  <script>
    (function (el) {
      if (el && window.matchMedia && window.matchMedia('(min-width: 40rem)').matches) {
        el.open = true;
      }
    })(document.currentScript.previousElementSibling);
  </script>
</section>
@endsection

@push('scripts')
  @vite(['resources/js/gesprek.js'])
@endpush
