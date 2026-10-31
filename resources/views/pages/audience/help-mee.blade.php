@extends('layouts.site')

@section('title', $title)
@section('meta_description', $metaDescription)
@section('canonical', $canonical)

@section('content')
<x-audience-page
  eyebrow="Voor verbinders"
  heading="Help mee"
  intro="{{ config('site.name') }} groeit vooral als mensen het doorgeven. Ik zoek verbinders: iemand die het leuk vindt om te posten, te tippen en contact te leggen — niet om een account vol te zetten met aanbod, maar om de site onder de juiste mensen te brengen."
  :current="$currentSeries"
  :series="$series"
>
  <h2>Wat je kunt doen</h2>
  <ul>
    <li><strong>Social media en berichten:</strong> korte updates, tips of een foto van een stekje dat de deur uit ging. Ik help graag mee met teksten (ook met AI).</li>
    <li><strong>Volkstuinen tippen:</strong> verenigingen wijzen op de pagina voor <a href="{{ route('volkstuinen') }}">volkstuinverenigingen</a> en het aanmeldformulier.</li>
    <li><strong>Voedselbossen en initiatieven:</strong> plekken en netwerken zoals voedselbossen of Meer Bomen Nu laten weten dat delen via de site kan — via hun eigen kanalen of het bestaande <a href="{{ route('voor-organisaties') }}">organisatieformulier</a>.</li>
  </ul>
  <p>Geen verplichtingen, geen verkoop, geen partnerschap-claims. Als het klikt, werk ik graag met je mee.</p>

  <h2>Interesse? Laat het me weten</h2>
  <p>Vul het formulier in. Ik sla je gegevens op in de database en lees alles zelf in het beheer. Er gaat <strong>geen</strong> automatische mail naar jou of naar derden.</p>

  @if($errors->any())
    <ul class="error-list" role="alert">
      @foreach($errors->all() as $error)
        <li class="error">{{ $error }}</li>
      @endforeach
    </ul>
  @endif

  <form class="form soft-card audience-form" method="post" action="{{ route('help-mee.store') }}" novalidate>
    @csrf
    @include('partials.honeypot')

    <label for="help-mee-name">Naam of voornaam *
      <input id="help-mee-name" name="name" required maxlength="{{ $maxName }}" value="{{ old('name') }}" autocomplete="name">
    </label>
    <label for="help-mee-email">E-mail *
      <input id="help-mee-email" type="email" name="email" required maxlength="254" value="{{ old('email') }}" autocomplete="email">
    </label>
    <label for="help-mee-note">Waar wil je bij helpen? (optioneel)
      <textarea id="help-mee-note" name="note" rows="5" maxlength="{{ $maxNote }}">{{ old('note') }}</textarea>
    </label>
    <p class="muted">Bijvoorbeeld: social media, volkstuinen benaderen, voedselbossen of initiatieven tippen.</p>

    <label class="checkbox">
      <input type="checkbox" name="privacy" value="1" required @checked(old('privacy'))>
      <span>Ik ga akkoord met de <a href="{{ route('privacy') }}">privacyverklaring</a>.</span>
    </label>

    <div class="form-actions">
      <button class="btn" type="submit">Stuur interesse</button>
    </div>
  </form>

  <x-slot:cta>
    <p class="muted">Liever meteen meedoen met delen? <a href="{{ route('account.registreren') }}">Maak een account</a> · <a href="{{ route('contact') }}">Contact</a></p>
  </x-slot:cta>
</x-audience-page>
@endsection
