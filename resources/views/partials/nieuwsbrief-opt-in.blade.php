{{--
  Nieuwsbrief-schakelaar (opt-out): registreren + account/profiel.
  Standaard AAN; uitvinken = afmelden. Los van het verplichte privacy-akkoord.
  $checked: huidige stand; $profile: true op de profielpagina (andere hinttekst).
--}}
@php
  /** @var bool $checked */
  $checked = (bool) ($checked ?? true);
  $profile = (bool) ($profile ?? false);
  $name = (string) ($name ?? 'nieuwsbrief');
@endphp
<label class="checkbox">
  <input type="hidden" name="{{ $name }}" value="0">
  <input type="checkbox" name="{{ $name }}" value="1" @checked($checked)>
  <span>{{ $profile ? 'Ik ontvang af en toe de nieuwsbrief' : 'Houd me af en toe op de hoogte met de nieuwsbrief' }}</span>
</label>
<p class="hint">
  @if($profile)
    Haal het vinkje weg en klik op Opslaan om je af te melden. Je kunt je ook afmelden via de link onderaan elke nieuwsbrief.
  @else
    Staat standaard aan: haal het vinkje weg als je geen nieuwsbrief wilt. Afmelden kan altijd, ook later in je profiel of via de link onderaan elke nieuwsbrief.
  @endif
  We versturen hem via Brevo. Zie de <a href="{{ route('privacy') }}">privacyverklaring</a>.
</p>
