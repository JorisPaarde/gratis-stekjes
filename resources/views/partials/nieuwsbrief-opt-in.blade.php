{{--
  Korte privacytekst bij het nieuwsbriefvinkje (registreren + account/profiel).
  Los van het verplichte privacy-akkoord. Nooit vooraf aangevinkt.
--}}
@php
  /** @var bool $checked */
  $checked = (bool) ($checked ?? false);
  $name = (string) ($name ?? 'nieuwsbrief');
@endphp
<label class="checkbox">
  <input type="hidden" name="{{ $name }}" value="0">
  <input type="checkbox" name="{{ $name }}" value="1" @checked($checked)>
  <span>Stuur me af en toe een nieuwsbrief</span>
</label>
<p class="hint">Tips en updates over Gratis planten. Afmelden kan altijd via je account of de link in de mail. Zie de <a href="{{ route('privacy') }}">privacyverklaring</a>.</p>
