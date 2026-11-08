{{-- Bestaande no-foto placeholder (planticon). Class is-leeg; optioneel is-foto-fout. --}}
@php($extra = trim((string) ($extraClass ?? '')))
<span class="aanbod-kaart-foto is-leeg{{ $extra !== '' ? ' '.$extra : '' }}" aria-hidden="true"><x-icon name="plant" :size="(int) ($size ?? 32)" /></span>
