@extends('layouts.site')

@section('title', $title)
@section('meta_description', $metaDescription)
@section('canonical', $canonical)

@push('head')
  @vite(['resources/css/meedoen.css'])
@endpush

@php
  $site = rtrim(route('home'), '/');
  $berichten = [
    ['Kort en simpel', "Plantje over? Op Gratis planten geef je planten, stekjes en zaden gratis weg aan iemand in de buurt. Zelf ophalen, en vaak een praatje erbij. Kijk wat er bij jou in de buurt is: {$site}"],
    ['Persoonlijk', "Ik deel mijn stekjes via Gratis planten. Geen geld, geen gedoe: je haalt het op en maakt een praatje. Heb jij ook iets over, of zoek je iets groens? {$site}"],
    ['Voor de app van je volkstuin', "Hoi tuinders! Te veel zaailingen, stekjes of vaste planten? Op Gratis planten zet je ze gratis online. Koppel je account aan de vereniging, dan zie je elkaars aanbod. ".route('help-mee')],
    ['Onder een snoeipost', "Leuk! Tip: gooi je snoeisel niet weg, veel takjes kun je gewoon stekken. Heb je straks stekjes over, dan kun je ze gratis aanbieden aan iemand in de buurt op gratisplanten.nl"],
  ];
@endphp

@section('content')
<x-audience-page
  eyebrow="Meedoen"
  heading="Help {{ config('site.name') }} groeien"
  intro="Hoe meer mensen meedoen, hoe meer groen er bij jou in de buurt te halen is. Je hoeft er niet veel voor te doen: een poster, een bericht of gewoon doorvertellen helpt al."
  :current="$currentSeries"
  :series="$series"
>
  <nav class="meedoen-snel" aria-label="Snel naar">
    <a class="meedoen-tegel" href="#poster"><span aria-hidden="true">🖨️</span> Hang een poster op</a>
    <a class="meedoen-tegel" href="#delen"><span aria-hidden="true">💬</span> Deel een bericht</a>
    <a class="meedoen-tegel" href="#partner"><span aria-hidden="true">🤝</span> Word partner</a>
  </nav>

  @include('partials.maker-kort')

  <h2 id="poster">Hang een poster op</h2>
  <p>Op het prikbord van je volkstuin, in de supermarkt, de bieb of het buurthuis. De QR-code brengt mensen meteen naar de site. Print hem op A4 of bewaar hem als pdf.</p>
  <ul class="meedoen-posters">
    @foreach(\App\Http\Controllers\MeedoenController::POSTERS as $key => $p)
      <li>
        <a class="poster-kaart" href="{{ route('poster', ['voor' => $key]) }}">
          <span class="poster-mini" aria-hidden="true">
            <picture>
              <source type="image/webp" srcset="/assets/images/posters/{{ $key }}-480.webp">
              <img src="/assets/images/posters/{{ $key }}-480.jpg" alt="" width="480" height="300" loading="lazy" decoding="async" style="object-position: {{ $p['foto']['positie'] }}">
            </picture>
            <span class="poster-mini-kop">{{ $p['kop'] }}</span>
            <span class="poster-mini-qr"></span>
            <span class="poster-mini-voet"></span>
          </span>
          <span class="poster-kaart-tekst">
            <strong>{{ $p['kop'] }}</strong>
            <span class="poster-kaart-voor">{{ $p['titel'] }}</span>
          </span>
          <span class="poster-kaart-actie">Bekijk en print poster <span aria-hidden="true">→</span></span>
        </a>
      </li>
    @endforeach
  </ul>
  <p class="muted poster-kaart-uitleg">Klik op een poster. Je ziet hem dan op ware grootte en kunt hem printen of als pdf bewaren.</p>
  <p class="meedoen-tip"><strong>Tip: stekjeslabels.</strong> Geef je een stekje weg? Steek er een labeltje bij met de QR-code. Zo wordt elk stekje een uitnodiging. <a href="{{ route('stekjeslabels') }}">Print 8 labels op A4</a>.</p>
  <p class="muted">Lid van een volkstuin? <a href="{{ route('account.verenigingen') }}">Koppel je vereniging</a>, dan krijgt jullie tuin een eigen poster met het aanbod van leden.</p>

  <h2 id="delen">Deel een bericht</h2>
  <p>Kopieer een tekst en plak hem in Facebook, Instagram, WhatsApp of de app van je vereniging. Of deel de link direct.</p>
  <div class="meedoen-berichten">
    @foreach($berichten as $i => [$label, $tekst])
      <figure class="meedoen-bericht">
        <figcaption>{{ $label }}</figcaption>
        <p id="bericht-{{ $i }}">{{ $tekst }}</p>
        <button type="button" class="btn btn-secondary btn-klein" data-kopieer="bericht-{{ $i }}">Kopieer tekst</button>
      </figure>
    @endforeach
  </div>
  <p class="meedoen-deel">
    <a class="btn" href="https://www.facebook.com/sharer/sharer.php?u={{ urlencode($site) }}" target="_blank" rel="noopener noreferrer">Deel op Facebook</a>
    <a class="btn" href="https://wa.me/?text={{ urlencode($berichten[0][1]) }}" target="_blank" rel="noopener noreferrer">Deel via WhatsApp</a>
    <a class="btn" href="https://www.linkedin.com/sharing/share-offsite/?url={{ urlencode($site) }}" target="_blank" rel="noopener noreferrer">Deel op LinkedIn</a>
  </p>
  <p class="muted">Facebook laat geen tekst vooraf invullen. Kopieer daarom eerst een tekst en plak hem in je bericht.</p>
  @if(config('site.socials', []) !== [])
    <p><strong>Volg mij</strong> en deel mijn berichten, daar help je echt mee:
      @foreach(config('site.socials') as $label => $url)
        <a href="{{ $url }}" rel="noopener noreferrer me" target="_blank">{{ $label }}</a>@if(! $loop->last) · @endif
      @endforeach
    </p>
  @endif

  <h2 id="partner">Word partner</h2>
  <p>Ik zoek echte partners: organisaties die Gratis planten samen met mij verder willen brengen. Geen reclame en geen geld, wel samen meer groen en meer contact in de buurt.</p>
  <ul class="meedoen-partners">
    <li><strong>Tuincentra en kwekerijen</strong>: overgebleven zaailingen of stekjes weggeven, een poster bij de kassa.</li>
    <li><strong>Bibliotheken en buurthuizen</strong>: een poster ophangen, of een plantenbieb of zadenbieb als deelplek op de kaart.</li>
    <li><strong>Volkstuin- en tuinverenigingen</strong>: je vereniging koppelen, de poster in het clubhuis, het aanbod van leden zichtbaar.</li>
    <li><strong>Gemeenten en welzijn</strong>: buren met elkaar in contact brengen en meer groen in de wijk.</li>
    <li><strong>Scholen en natuureducatie</strong>: stekken en zaaien met kinderen, en dan delen.</li>
    <li><strong>Bedrijven en makers</strong>: helpen met drukwerk, vertalen, fotografie of techniek.</li>
  </ul>
  <p>Partners komen op de pagina <a href="{{ route('partners') }}">Initiatieven</a>, als jullie dat willen.</p>

  @if(session('partner_status'))
    <p class="ok" role="status">{{ session('partner_status') }}</p>
  @else
    @if($errors->any())
      <p class="error" role="alert">{{ $errors->first() }}</p>
    @endif
    <form class="form meedoen-form" method="post" action="{{ route('help-mee.partner') }}">
      @csrf
      <input type="hidden" name="form_started" value="{{ time() }}">
      <div class="visually-hidden" aria-hidden="true"><label>Website <input name="website" tabindex="-1" autocomplete="off"></label></div>
      <label>Je naam *<input name="naam" required maxlength="120" value="{{ old('naam') }}" autocomplete="name"></label>
      <label>Organisatie *<input name="organisatie" required maxlength="160" value="{{ old('organisatie') }}" autocomplete="organization"></label>
      <label>Wat voor organisatie? *
        <select name="soort" required>
          <option value="">Kies…</option>
          @foreach(\App\Http\Controllers\MeedoenController::PARTNERSOORTEN as $k => $l)
            <option value="{{ $k }}" @selected(old('soort') === $k)>{{ $l }}</option>
          @endforeach
        </select>
      </label>
      <label>E-mailadres *<input type="email" name="email" required maxlength="254" value="{{ old('email') }}" autocomplete="email"></label>
      <label>Hoe wil je meehelpen? *<textarea name="bericht" required minlength="10" maxlength="2000">{{ old('bericht') }}</textarea></label>
      <p class="hint">Ik lees je bericht zelf en mail je terug. Je gegevens bewaar ik niet op de site; ze komen alleen in mijn mailbox.</p>
      <button class="btn" type="submit">Stuur mijn aanvraag</button>
    </form>
  @endif


  <h2 id="verbinder">Word verbinder</h2>
  <p>Breng jij mensen graag bij elkaar? Tip volkstuinen, voedselbossen en buurtinitiatieven, deel berichten en vertel me wat er leeft. Jij legt het contact, ik regel de site en de teksten. Zo’n 2 uur per maand is genoeg.</p>

  <h2>Of help op een andere manier</h2>
  <ul>
    <li><a href="{{ route('account.registreren') }}">Maak een account</a> en zet je eerste stekje online. Elk aanbod maakt de kaart leuker.</li>
    <li><a href="{{ route('voor-organisaties.aanmelden') }}">Meld een deelplek aan</a>, zoals een plantenasiel, plantenbieb of ruilkastje.</li>
    <li>Vertel het door aan een buurvrouw, collega of je oma met de vensterbank vol stekjes.</li>
    <li>Iets gezien dat beter kan? Laat het me weten via <a href="{{ route('contact') }}">contact</a> of de feedbackknop.</li>
  </ul>

  <x-slot:cta>
    <div class="audience-cta-actions">
      <a class="btn" href="{{ route('contact') }}">Meld je als verbinder: 2 uur per maand, stoppen kan altijd</a>
      @auth
        <a class="btn btn-secondary" href="{{ route('feedback.create', ['page_path' => '/help-mee']) }}">Via feedback</a>
      @endauth
    </div>
  </x-slot:cta>
</x-audience-page>
@endsection

@push('scripts')
<script>
  document.querySelectorAll('[data-kopieer]').forEach(function (btn) {
    btn.addEventListener('click', function () {
      var el = document.getElementById(btn.getAttribute('data-kopieer'));
      var tekst = el ? el.textContent.trim() : '';
      var klaar = function () { var o = btn.textContent; btn.textContent = 'Gekopieerd ✓'; setTimeout(function () { btn.textContent = o; }, 2000); };
      if (navigator.clipboard) { navigator.clipboard.writeText(tekst).then(klaar).catch(function () {}); }
      else { var r = document.createRange(); r.selectNodeContents(el); var s = window.getSelection(); s.removeAllRanges(); s.addRange(r); document.execCommand('copy'); klaar(); }
    });
  });
</script>
@endpush
