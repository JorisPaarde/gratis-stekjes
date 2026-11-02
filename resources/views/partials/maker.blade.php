{{-- Persoonlijk verhaal van de maker (op /over). Naam en bedrijf komen uit config/site.php. --}}
@php($socials = config('site.socials', []))
<section class="maker" aria-labelledby="maker-titel">
  <picture>
    <source srcset="/assets/images/maker/maker-240.webp 1x, /assets/images/maker/maker-480.webp 2x" type="image/webp">
    <img class="maker-foto" src="/assets/images/maker/maker-240.jpg" srcset="/assets/images/maker/maker-480.jpg 2x" alt="{{ config('site.maker') }}, de maker van {{ config('site.name') }}" width="220" height="220">
  </picture>
  <div class="maker-tekst">
    <h2 id="maker-titel">Hoi, ik ben {{ config('site.maker') }}</h2>
    <p>Ik heb een hele lieve vrouw en vier kinderen, waaronder een tweeling. Thuis heb ik een fijne tuin, en daarnaast ook nog een volkstuin. Eigenlijk heb ik alles wat mijn hartje begeert.</p>
    <p>Vroeger was ik kok, en later gaf ik les op een koksopleiding. Dat vond ik het mooiste wat er is: mensen verder helpen. Nu werk ik voor mezelf als webbouwer en doe ik veel met AI. Ook ben ik een tijd helemaal weg geweest van biomimicry: leren van hoe de natuur dingen oplost. Ik doe dus van alles en nog wat.</p>
    <p>Het mooie van AI is dat ik nu veel meer van mijn ideeën echt kan maken. {{ config('site.name') }} is daar een van. Ik doe het voor niks en verdien er niets aan. Maar in mijn eentje kom ik maar tot een bepaald punt, en eerlijk gezegd vind ik samen ook veel leuker.</p>
  </div>
</section>

<h2>Waarom ik dit doe</h2>
<p>Via je telefoon, het nieuws en social media zie je vooral de nare dingen. Je brein wordt de hele dag gehackt, en je gaat bijna geloven dat de wereld zo is. Terwijl ik iets anders zie: de meeste mensen willen best iets delen, weggeven en even een praatje maken. Als je dat weer met eigen ogen ziet, krijg je ook weer een beetje vertrouwen. Eigenlijk zijn de meeste mensen gewoon best leuk en goed.</p>
<p>Mijn vader, {{ config('site.maker_vader') }}, heeft zijn hele leven keihard geprobeerd de wereld een beetje mooier te maken. Hij hielp heel veel daklozen en werd in 2018 de allereerste <a href="{{ config('site.maker_vader_url') }}" target="_blank" rel="noopener noreferrer">MensenrechtenMens</a> van het College voor de Rechten van de Mens. Hij is helaas overleden. Ik hoop dat hij trots zou zijn op wat hier samen ontstaat.</p>

@if($socials !== [])
  <p class="maker-volg"><strong>Volg mij</strong> en deel mijn berichten over {{ config('site.name') }}, daar help je echt mee:
    @foreach($socials as $label => $url)
      <a href="{{ $url }}" rel="noopener noreferrer me" target="_blank">{{ $label }}</a>@if(! $loop->last) · @endif
    @endforeach
  </p>
@endif
