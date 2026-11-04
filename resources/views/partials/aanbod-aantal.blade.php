{{-- Toon aantal alleen als > 1, in de vorm «3 stuks». --}}
@if((int) ($aantal ?? 0) > 1)
  <span class="aanbod-aantal">{{ (int) $aantal }} stuks</span>
@endif
