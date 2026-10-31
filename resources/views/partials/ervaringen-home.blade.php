@if(!empty($ervaringenBlok))
<section class="section wrap" aria-labelledby="ervaringen-home-titel">
  <h2 id="ervaringen-home-titel">Ervaringen</h2>
  <p class="section-intro">Wat leden delen na een overdracht of zomaar — kort en dichtbij.</p>
  <ul class="ervaringen-home-list" style="list-style:none;padding:0;margin:0;display:grid;gap:1rem">
    @foreach($ervaringenBlok as $item)
      <li class="soft-card">
        @if(!empty($item['has_photo']) && !empty($item['photo_url']))
          <p style="margin:0 0 .75rem">
            <img src="{{ $item['photo_url'] }}" alt="" width="480" height="320" loading="lazy" decoding="async" style="width:100%;max-height:14rem;object-fit:cover;border-radius:var(--radius, .75rem)">
          </p>
        @endif
        <p style="margin:0 0 .5rem">{{ $item['body'] }}</p>
        <p class="muted" style="margin:0;font-size:.9rem">
          {{ $item['naam'] }} · {{ $item['plaatsnaam'] }}
          @if(!empty($item['soort'])) · {{ $item['soort'] }} @endif
        </p>
      </li>
    @endforeach
  </ul>
  <p style="margin-top:1rem"><a href="{{ route('ervaringen.index') }}">Meer ervaringen</a></p>
</section>
@endif
