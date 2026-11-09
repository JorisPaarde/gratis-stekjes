{{-- Autofill-neutrale honeypot: geen website/url/name/email. Legacy `website` blijft server-side geaccepteerd. --}}
<div class="hp" aria-hidden="true">
  <label>Laat leeg
    <input
      type="text"
      name="{{ \App\Support\FormGuard::HONEYPOT_FIELD }}"
      value=""
      tabindex="-1"
      autocomplete="off"
      data-1p-ignore
      data-lpignore="true"
      data-bwignore
      data-form-type="other"
    >
  </label>
  <input type="hidden" name="form_started" value="{{ time() }}">
</div>
