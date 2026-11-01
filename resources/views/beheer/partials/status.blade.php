@if(! $u->active)
  <span class="status status--uit">Geblokkeerd</span>
@elseif(! $u->hasVerifiedEmail())
  <span class="status status--wacht">Wacht op bevestiging{{ $u->created_at ? ' · '.max(0, (int) $u->created_at->diffInDays(now())).' d' : '' }}</span>
@elseif(($mailFout ?? false))
  <span class="status status--fout">Mail mislukt</span>
@else
  <span class="status status--ok">Bevestigd</span>
@endif
