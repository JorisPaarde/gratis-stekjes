{{-- Honeypot + timing (bots); CSRF via @csrf op het formulier --}}
<div class="hp" aria-hidden="true">
  <label>Website <input type="text" name="website" tabindex="-1" autocomplete="off" value=""></label>
</div>
<input type="hidden" name="form_started" value="{{ $formStarted ?? time() }}">
