{{-- Seizoensblok «Maak er zelf meer van»: eerst voor jezelf, dan voor een ander. Data: App\Services\Seizoen. --}}
@php
  $deelUrl = auth()->check() ? route('aanbieden') : route('account.registreren');
@endphp
<section class="seizoen seizoen--{{ $seizoen['seizoen'] }}" id="zelf-meer-maken" aria-labelledby="seizoen-titel">
  <div class="wrap">
    <p class="seizoen-eyebrow">{{ ucfirst($seizoen['maand_naam']) }} · eerst je eigen tuin, dan die van de buren</p>
    <h2 id="seizoen-titel" class="seizoen-kop">{{ $seizoen['kop'] }}</h2>
    <p class="seizoen-intro">{{ $seizoen['tekst'] }}</p>

    <div class="seizoen-stappen">
      <div class="seizoen-stap seizoen-stap--zelf">
        <p class="seizoen-stap-label"><span class="seizoen-num" aria-hidden="true">1</span> Eerst voor jezelf: maak er meer van</p>
        <ul class="seizoen-acties">
          @foreach($seizoen['acties'] as $actie)
            <li>
              <a class="seizoen-actie" href="{{ $actie['url'] }}">
                @include('partials.illustraties.stek', ['naam' => $actie['illustratie']])
                <span class="seizoen-actie-tekst">
                  <strong>{{ $actie['titel'] }}</strong>
                  <span>{{ $actie['tekst'] }}</span>
                  <span class="seizoen-actie-link">Zo doe je het <span aria-hidden="true">→</span></span>
                </span>
              </a>
            </li>
          @endforeach
        </ul>
        @if($seizoen['wist_je_dat'] !== '')
          <p class="seizoen-wist"><strong>Wist je dat?</strong> {{ $seizoen['wist_je_dat'] }}</p>
        @endif
      </div>

      <div class="seizoen-stap seizoen-stap--delen">
        <p class="seizoen-stap-label"><span class="seizoen-num" aria-hidden="true">2</span> Dan voor een ander: deel wat over is</p>
        <ol class="seizoen-flow" aria-label="Van één plant naar gratis planten voor de buren">
          <li><span class="seizoen-flow-getal">1</span> plant</li>
          <li><span class="seizoen-flow-getal">5</span> stekjes</li>
          <li><span class="seizoen-flow-getal">4</span> weggegeven</li>
        </ol>
        <p>Je maakt al snel meer dan je zelf nodig hebt. Zet de rest online, dan is iemand in de buurt er blij mee.</p>
        @if(!empty($seizoen['gedeeld']))
          <p class="seizoen-teller">Deze maand al <strong>{{ $seizoen['gedeeld'] }}</strong> keer iets gedeeld.</p>
        @endif
        <p class="seizoen-knoppen">
          <a class="btn" href="{{ $deelUrl }}"><x-icon name="plus" :size="18" /> Bied het gratis aan</a>
          <a class="btn btn-secondary" href="{{ route('vermeerderen') }}">Alles over vermeerderen</a>
        </p>
      </div>
    </div>

    @if(!empty($seizoen['planten']))
      <h3 class="seizoen-planten-kop">Makkelijk in {{ $seizoen['maand_naam'] }}</h3>
      <ul class="seizoen-planten">
        @foreach($seizoen['planten'] as $plant)
          @php($img = $plant['afbeelding'])
          <li>
            <a href="{{ $plant['url'] }}">
              <img src="{{ $img['url'] }}" alt="{{ $img['alt'] }}" width="200" height="150" loading="lazy" decoding="async" @class(['is-illustratie' => !empty($img['is_fallback'])])>
              <span>{{ $plant['naam'] }}</span>
            </a>
          </li>
        @endforeach
      </ul>
      @php($metCredit = collect($seizoen['planten'])->filter(fn ($p) => empty($p['afbeelding']['is_fallback']) && !empty($p['afbeelding']['auteur'])))
      @if($metCredit->isNotEmpty())
        <p class="seizoen-credits">Foto’s via Wikimedia Commons:
          @foreach($metCredit as $p)
            {{ $p['naam'] }} © @if(!empty($p['afbeelding']['bron_url']))<a href="{{ $p['afbeelding']['bron_url'] }}" rel="noopener noreferrer">{{ $p['afbeelding']['auteur'] }}</a>@else{{ $p['afbeelding']['auteur'] }}@endif ({{ $p['afbeelding']['licentie'] }})@if(! $loop->last), @endif
          @endforeach
        </p>
      @endif
    @endif
  </div>
</section>
