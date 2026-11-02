{{-- Eén inhoudsblok van een kennisbankartikel (data/kennisbank/*.json → «inhoud»). --}}
@php
  $type = $block['type'] ?? '';
  $maandNamen = ['jan', 'feb', 'mrt', 'apr', 'mei', 'jun', 'jul', 'aug', 'sep', 'okt', 'nov', 'dec'];
  $huidigeMaand = (int) now()->timezone('Europe/Amsterdam')->format('n');
@endphp
@switch($type)
  @case('h2')
    <h2 id="{{ \Illuminate\Support\Str::slug($block['tekst'] ?? '') }}">{{ $block['tekst'] ?? '' }}</h2>
    @break

  @case('p')
    {{-- [[slug|tekst]] wordt een link naar dat artikel; [[@stektips|tekst]] naar het overzicht per plant. --}}
    @if(!empty($block['foto']))
      <figure class="kb-sectie-foto">
        <x-artikel-picture :id="$block['foto']" sizes="(max-width: 760px) 100vw, 42rem" />
      </figure>
    @endif
    @php
      $linkRoutes = ['@stektips' => 'stektips.index', '@aanbod' => 'aanbod', '@vermeerderen' => 'vermeerderen'];
      $html = preg_replace_callback('/\[\[(@?[a-z0-9-]+)\|([^\]]+)\]\]/', function ($m) use ($linkRoutes) {
          $url = str_starts_with($m[1], '@')
              ? (isset($linkRoutes[$m[1]]) ? route($linkRoutes[$m[1]]) : null)
              : route('stektips.artikel', ['slug' => $m[1]]);

          return $url === null ? $m[2] : '<a href="'.e($url).'">'.$m[2].'</a>';
      }, e($block['tekst'] ?? ''));
    @endphp
    <p>{!! $html !!}</p>
    @break

  @case('ol')
  @case('ul')
    @if(!empty($block['items']))
      <{{ $type }}>
        @foreach($block['items'] as $item)
          <li>{{ $item }}</li>
        @endforeach
      </{{ $type }}>
    @endif
    @break

  @case('tip')
    <aside class="kb-tip" role="note">
      <span class="kb-tip-icoon" aria-hidden="true">💡</span>
      <div>
        @if(!empty($block['titel']))<p class="kb-tip-titel">{{ $block['titel'] }}</p>@endif
        <p>{{ $block['tekst'] ?? '' }}</p>
      </div>
    </aside>
    @break

  @case('kaarten')
    <ul class="kb-kaarten">
      @foreach($block['items'] ?? [] as $kaart)
        <li @class(['kb-kaart', 'kb-kaart--met-foto' => !empty($kaart['foto'])])>
          @if(!empty($kaart['foto']))
            <x-artikel-picture :id="$kaart['foto']" class="kb-kaart-foto" sizes="(max-width: 600px) 100vw, 18rem" />
          @elseif(!empty($kaart['illustratie']))
            @include('partials.illustraties.stek', ['naam' => $kaart['illustratie']])
          @endif
          <h3>{{ $kaart['titel'] ?? '' }}</h3>
          @if(!empty($kaart['wanneer']))<p class="kb-kaart-wanneer">{{ $kaart['wanneer'] }}</p>@endif
          <p>{{ $kaart['tekst'] ?? '' }}</p>
          @if(!empty($kaart['slug']))
            <a class="kb-kaart-link" href="{{ route('stektips.artikel', ['slug' => $kaart['slug']]) }}">Zo doe je het <span aria-hidden="true">→</span></a>
          @endif
        </li>
      @endforeach
    </ul>
    @break

  @case('stappen')
    @if(!empty($block['foto']))
      <figure class="kb-sectie-foto">
        <x-artikel-picture :id="$block['foto']" sizes="(max-width: 760px) 100vw, 42rem" />
      </figure>
    @endif
    @if(!empty($block['illustratie']))
      <div class="kb-stappen-illu">@include('partials.illustraties.stek', ['naam' => $block['illustratie']])</div>
    @endif
    <ol class="kb-stappen">
      @foreach($block['items'] ?? [] as $stap)
        <li>
          <p class="kb-stap-titel">{{ $stap['titel'] ?? '' }}</p>
          <p>{{ $stap['tekst'] ?? '' }}</p>
        </li>
      @endforeach
    </ol>
    @break

  @case('vergelijk')
    <div class="kb-vergelijk">
      @foreach($block['kolommen'] ?? [] as $kolom)
        <div @class(['kb-vergelijk-kolom', 'kb-vergelijk-kolom--met-foto' => !empty($kolom['foto'])])>
          @if(!empty($kolom['foto']))
            <x-artikel-picture :id="$kolom['foto']" class="kb-vergelijk-foto" sizes="(max-width: 640px) 100vw, 28rem" />
          @elseif(!empty($kolom['illustratie']))
            @include('partials.illustraties.stek', ['naam' => $kolom['illustratie']])
          @endif
          <h3>{{ $kolom['titel'] ?? '' }}</h3>
          <ul>
            @foreach($kolom['items'] ?? [] as $item)
              <li>{{ $item }}</li>
            @endforeach
          </ul>
        </div>
      @endforeach
    </div>
    @break

  @case('kalender')
    <figure class="kb-kalender">
      <div class="kb-kalender-grid" role="table" aria-label="{{ $block['titel'] ?? 'Kalender' }}">
        <div class="kb-kalender-rij kb-kalender-kop" role="row">
          <span role="columnheader" class="kb-kalender-label"></span>
          @foreach($maandNamen as $i => $m)
            <span role="columnheader" @class(['kb-kalender-maand', 'is-nu' => $i + 1 === $huidigeMaand])>{{ $m }}</span>
          @endforeach
        </div>
        @foreach($block['rijen'] ?? [] as $rij)
          <div class="kb-kalender-rij" role="row">
            <span role="rowheader" class="kb-kalender-label">{{ $rij['label'] ?? '' }}</span>
            @for($m = 1; $m <= 12; $m++)
              @php $aan = in_array($m, $rij['maanden'] ?? [], true); @endphp
              <span role="cell" @class(['kb-kalender-cel', 'is-aan' => $aan, 'kb-kleur-'.($rij['kleur'] ?? 'groen') => $aan, 'is-nu' => $m === $huidigeMaand]) aria-label="{{ $maandNamen[$m - 1] }}: {{ $aan ? 'ja' : 'nee' }}"></span>
            @endfor
          </div>
        @endforeach
      </div>
      @if(!empty($block['noot']))<figcaption>{{ $block['noot'] }}</figcaption>@endif
    </figure>
    @break

  @case('maanden')
    @if(!empty($block['foto']))
      <figure class="kb-sectie-foto">
        <x-artikel-picture :id="$block['foto']" sizes="(max-width: 760px) 100vw, 56rem" />
      </figure>
    @endif
    <ol class="kb-maanden">
      @foreach($block['items'] ?? [] as $i => $item)
        <li @class(['kb-maand', 'is-nu' => $i + 1 === $huidigeMaand])>
          <p class="kb-maand-naam">{{ $item['maand'] ?? '' }}@if($i + 1 === $huidigeMaand) <span class="kb-nu-label">nu</span>@endif</p>
          <p>{{ $item['tekst'] ?? '' }}</p>
        </li>
      @endforeach
    </ol>
    @break

  @case('soorten')
    @if(!empty($block['titel']))<h3 class="kb-soorten-titel">{{ $block['titel'] }}</h3>@endif
    <ul class="kb-soorten">
      @foreach($block['items'] ?? [] as $soort)
        <li>
          <a href="{{ $soort['url'] }}">
            <img src="{{ $soort['foto']['url'] }}" alt="{{ $soort['foto']['alt'] }}" width="320" height="240" loading="lazy" decoding="async">
            <span class="kb-soort-naam">{{ $soort['naam'] }}</span>
            @if($soort['kort'] !== '')<span class="kb-soort-kort">{{ $soort['kort'] }}</span>@endif
          </a>
        </li>
      @endforeach
    </ul>
    @php $metFoto = collect($block['items'] ?? [])->reject(fn ($s) => $s['foto']['is_fallback']); @endphp
    @if($metFoto->isNotEmpty())
      <p class="kb-credits">Foto’s via Wikimedia Commons:
        @foreach($metFoto as $s)
          {{ $s['naam'] }} © <a href="{{ $s['foto']['bron_url'] }}" rel="noopener noreferrer">{{ $s['foto']['auteur'] }}</a> (<a href="{{ $s['foto']['licentie_url'] }}" rel="noopener noreferrer license">{{ $s['foto']['licentie'] }}</a>)@if(! $loop->last), @endif
        @endforeach
      </p>
    @endif
    @break

  @case('videos')
    @if(!empty($block['titel']))<h2 id="{{ \Illuminate\Support\Str::slug($block['titel']) }}">{{ $block['titel'] }}</h2>@endif
    @if(!empty($block['intro']))<p>{{ $block['intro'] }}</p>@endif
    <div class="kb-videos">
      @foreach($block['items'] ?? [] as $video)
        @php $vid = preg_replace('/[^A-Za-z0-9_-]/', '', (string) ($video['id'] ?? '')); @endphp
        <figure class="kb-video">
          <div class="kb-video-frame" data-video-id="{{ $vid }}" data-video-titel="{{ $video['titel'] ?? '' }}">
            <a class="kb-video-start" href="https://www.youtube.com/watch?v={{ $vid }}" rel="noopener noreferrer" target="_blank">
              @if(!empty($video['poster']))
                <x-artikel-picture :id="$video['poster']" class="kb-video-poster" sizes="(max-width: 759px) 100vw, 28rem" :decorative="true" />
              @endif
              <span class="kb-video-play" aria-hidden="true"><svg viewBox="0 0 68 48" width="68" height="48"><path d="M66.5 7.7c-.8-2.9-3-5.2-5.9-6C55.3.2 34 .2 34 .2S12.7.2 7.4 1.7c-2.9.8-5.1 3.1-5.9 6C0 13 0 24 0 24s0 11 1.5 16.3c.8 2.9 3 5.2 5.9 6C12.7 47.8 34 47.8 34 47.8s21.3 0 26.6-1.5c2.9-.8 5.1-3.1 5.9-6C68 35 68 24 68 24s0-11-1.5-16.3z" fill="#e8833a"/><path d="M45 24 27 14v20z" fill="#fff"/></svg></span>
              <span class="kb-video-scrim" aria-hidden="true"></span>
              <span class="kb-video-titel">{{ $video['titel'] ?? '' }}</span>
              <span class="kb-video-kanaal">{{ $video['kanaal'] ?? 'YouTube' }}</span>
            </a>
          </div>
        </figure>
      @endforeach
    </div>
    <p class="kb-credits">Video’s laden pas als je op afspelen klikt. Dan maak je verbinding met YouTube (Google), zonder cookies voor advertenties (youtube-nocookie.com).</p>
    @break
@endswitch
