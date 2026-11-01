@if(!empty($volgendeStap) && rtrim(request()->url(), '/') !== rtrim(strtok($volgendeStap['url'], '#'), '/'))
  <aside class="volgende-stap" aria-label="Je volgende stap">
    <div class="wrap volgende-stap-inner">
      @if($volgendeStap['img'])
        <img src="{{ $volgendeStap['img'] }}" alt="" width="44" height="44">
      @endif
      <p><strong>{{ $volgendeStap['titel'] }}.</strong> <span>{{ $volgendeStap['tekst'] }}</span></p>
      <a class="btn" href="{{ $volgendeStap['url'] }}">{{ $volgendeStap['knop'] }}</a>
    </div>
  </aside>
@endif
