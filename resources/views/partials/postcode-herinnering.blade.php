@if(!empty($toonPostcodeHerinnering))
  <aside class="postcode-herinnering" aria-label="Postcode aanvullen" data-postcode-herinnering>
    <div class="wrap postcode-herinnering-inner">
      <p>
        <strong>Vul je postcode in,</strong>
        <span>dan zie je wat er bij jou in de buurt is.</span>
      </p>
      <div class="postcode-herinnering-acties">
        <a class="btn" href="{{ route('account.profiel') }}">Naar je profiel</a>
        <form method="post" action="{{ route('account.postcode-herinnering.weg') }}" class="postcode-herinnering-weg">
          @csrf
          <input type="hidden" name="next" value="{{ url()->full() }}">
          <button type="submit" class="postcode-herinnering-sluit" aria-label="Melding sluiten">Sluiten</button>
        </form>
      </div>
    </div>
  </aside>
@endif
