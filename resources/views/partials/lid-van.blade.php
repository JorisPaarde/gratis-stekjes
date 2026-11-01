@if(!empty($verenigingen))
  <p class="zoek-lid">Lid van
    @foreach($verenigingen as $v)
      <a href="{{ $v['url'] }}">{{ $v['naam'] }}</a>@if(! $loop->last), @endif
    @endforeach
  </p>
@endif
