<div
  class="app-balk"
  role="region"
  aria-label="Gratis planten als app"
  data-app-balk
  hidden
>
  <img src="{{ asset('assets/icons/icon-192.png') }}" width="40" height="40" alt="">
  <p>Ook als app</p>
  <a class="btn btn-secondary" href="{{ route('app') }}" data-app-balk-actie>Installeren</a>
  <button class="app-balk-sluit" type="button" aria-label="Sluiten" data-app-balk-sluit>
    <x-icon name="sluiten" :size="20" />
  </button>
</div>
