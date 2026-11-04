{{-- Gedeelde plantzoeker voor /stektips en /vermeerderen. Zoekt via ?q= op /stektips. --}}
@php
  $zoekId = $zoekId ?? 'stektips-q';
  $zoekValue = $zoekValue ?? '';
@endphp
<label for="{{ $zoekId }}">Welke plant wil je vermeerderen?</label>
<div class="stektips-zoek-veld">
  <input
    id="{{ $zoekId }}"
    type="search"
    name="q"
    value="{{ $zoekValue }}"
    maxlength="120"
    placeholder="Bijv. vrouwentong of monstera"
    autocomplete="off"
  >
  <button class="btn" type="submit"><x-icon name="zoeken" :size="18" /> Zoek</button>
</div>
