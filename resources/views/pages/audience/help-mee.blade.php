@extends('layouts.site')

@section('title', $title)
@section('meta_description', $metaDescription)
@section('canonical', $canonical)

@section('content')
<x-audience-page
  eyebrow="Voor verbinders"
  heading="Breng jij mensen graag bij elkaar?"
  intro="Jij legt het contact, ik regel de site en de teksten. Ik zoek verbinders die {{ config('site.name') }} onder de juiste mensen brengen — social media, volkstuinen tippen, voedselbossen en initiatieven zoals Meer Bomen Nu wijzen op delen."
  :current="$currentSeries"
  :series="$series"
>
  <h2>Wat je kunt doen</h2>
  <ul>
    <li><strong>Social media en berichten:</strong> korte updates, tips of een foto van een stekje dat de deur uit ging. Ik help graag mee met teksten (ook met AI).</li>
    <li><strong>Volkstuinen tippen:</strong> verenigingen wijzen op de pagina voor <a href="{{ route('volkstuinen') }}">volkstuinverenigingen</a> en het aanmeldformulier.</li>
    <li><strong>Voedselbossen en initiatieven:</strong> plekken laten weten dat delen via de site kan — via hun eigen kanalen of het bestaande <a href="{{ route('voor-organisaties') }}">organisatieformulier</a>.</li>
  </ul>
  <p>Zo’n 2 uur per maand is genoeg. Stoppen kan altijd. Geen verkoop, geen partnerschap-claims — alleen contact leggen.</p>

  <h2>Meld je</h2>
  <p>Stuur me een kort bericht via <a href="{{ route('contact') }}">contact</a>. Ben je al lid? Gebruik dan de <a href="{{ route('feedback.create') }}">feedbackknop</a> (opslag in de database; ik lees alles zelf, zonder automatische bevestigingsmail).</p>

  <x-slot:cta>
    <div class="audience-cta-actions">
      <a class="btn" href="{{ route('contact') }}">Meld je als verbinder: 2 uur per maand, stoppen kan altijd</a>
      @auth
        <a class="btn btn-secondary" href="{{ route('feedback.create', ['page_path' => '/help-mee']) }}">Via feedback</a>
      @endauth
    </div>
    <p class="muted">Liever meteen meedoen met delen? <a href="{{ route('account.registreren') }}">Maak een account</a></p>
  </x-slot:cta>
</x-audience-page>
@endsection
