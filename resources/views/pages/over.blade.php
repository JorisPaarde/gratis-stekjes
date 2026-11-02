@extends('layouts.site')

@section('title', $title)
@section('meta_description', $metaDescription)
@section('canonical', $canonical)

@push('head')
  @vite(['resources/css/meedoen.css'])
@endpush

@php($socials = config('site.socials', []))

@section('content')
<section class="section wrap prose over-page">
  <h1 class="page-title">Wie zit er achter {{ config('site.name') }}?</h1>
  <p class="section-intro">{{ config('site.name') }} is een plek waar mensen in Nederland en België gratis planten, stekjes en zaden aan elkaar geven. Het is <strong>gratis</strong>, je haalt alles <strong>zelf op</strong>, en de enige tegenprestatie is een <strong>praatje</strong>. Met één account kun je zowel weggeven als ophalen. <a href="{{ route('hoe-werkt-het') }}">Zo werkt het</a>.</p>
  <p>Ik maak het in mijn eentje, voor niks. Hieronder lees je wie ik ben en waarom ik dit doe.</p>

  {{-- Persoonlijk verhaal. Namen komen uit config/site.php (maker, maker_vader). --}}
  <section class="maker" aria-labelledby="maker-titel">
    <picture>
      <source srcset="/assets/images/maker/maker-240.webp 1x, /assets/images/maker/maker-480.webp 2x" type="image/webp">
      <img class="maker-foto" src="/assets/images/maker/maker-240.jpg" srcset="/assets/images/maker/maker-480.jpg 2x" alt="{{ config('site.maker') }}, de maker van {{ config('site.name') }}" width="220" height="220">
    </picture>
    <div class="maker-tekst">
      <h2 id="maker-titel">Hoi, ik ben {{ config('site.maker') }}</h2>
      <p>Ik heb een hele lieve vrouw en vier kinderen, waaronder een tweeling. Thuis heb ik een fijne tuin, en daarnaast ook nog een volkstuin. Eigenlijk heb ik alles wat mijn hartje begeert.</p>
      <p>Vroeger was ik kok, en later gaf ik les op een koksopleiding. Dat vond ik het mooiste wat er is: mensen verder helpen. Nu werk ik voor mezelf als webbouwer en doe ik veel met AI. Daardoor kan ik veel meer van mijn ideeën echt maken, en {{ config('site.name') }} is daar een van.</p>
      <p>De natuur fascineert me al lang. Ik ben een tijd helemaal weg geweest van biomimicry: leren van hoe de natuur dingen oplost. Planten delen is eigenlijk ook zoiets: wat bij de een te veel groeit, heeft de ander nodig.</p>
    </div>
  </section>

  <h2>Waarom ik dit doe</h2>
  <p>Via je telefoon, het nieuws en social media zie je vooral de nare dingen. Je brein wordt de hele dag gehackt, en je gaat bijna geloven dat de wereld zo is. Terwijl ik iets anders zie: de meeste mensen willen best iets delen, weggeven en even een praatje maken. Als je dat weer met eigen ogen ziet, krijg je ook weer een beetje vertrouwen. Eigenlijk zijn de meeste mensen gewoon best leuk en goed.</p>
  <p>Planten zijn daar perfect voor. Bijna iedereen heeft wel een stekje, zaailing of uitloper over, en weggeven kost niets. Wie iets ophaalt, belt even aan en maakt een praatje. Zo leer je je buren kennen, en dat is precies wat ik wil. Ik verdien er niets aan, en dat hoeft ook niet.</p>
  <p>Mijn vader, {{ config('site.maker_vader') }}, heeft zijn hele leven keihard geprobeerd de wereld een beetje mooier te maken. Hij hielp heel veel daklozen en werd in 2018 de allereerste <a href="{{ config('site.maker_vader_url') }}" target="_blank" rel="noopener noreferrer">MensenrechtenMens</a> van het College voor de Rechten van de Mens. Hij is helaas overleden. Ik hoop dat hij trots zou zijn op wat hier samen ontstaat.</p>

  <h2>Gratis en zonder reclame</h2>
  <p>Meedoen kost niets en er wordt niets verkocht. Er zit geen reclame op de site en geen meetcode van derden die je volgt. De site is er om te helpen, niet om aan jouw aandacht te verdienen.</p>

  <h2>Doe je mee?</h2>
  <p>In mijn eentje kom ik maar tot een bepaald punt, en eerlijk gezegd vind ik samen ook veel leuker. Ik zoek mensen en organisaties die mee willen bouwen: een tuincentrum, bibliotheek, volkstuinvereniging, gemeente of school, of gewoon iemand die een poster ophangt.</p>
  <p class="over-acties">
    <a class="btn" href="{{ route('help-mee') }}">Kijk hoe je mee kunt helpen</a>
    <a class="btn btn-secondary" href="{{ route('help-mee') }}#partner">Word partner</a>
  </p>
  @if($socials !== [])
    <p class="maker-volg"><strong>Volg mij</strong> en deel mijn berichten over {{ config('site.name') }}, daar help je echt mee:
      @foreach($socials as $label => $url)
        <a href="{{ $url }}" rel="noopener noreferrer me" target="_blank">{{ $label }}</a>@if(! $loop->last) · @endif
      @endforeach
    </p>
  @endif

  <p class="muted"><a href="{{ route('privacy') }}">Privacyverklaring</a> · <a href="{{ route('contact') }}">Contact</a></p>
</section>
@endsection
