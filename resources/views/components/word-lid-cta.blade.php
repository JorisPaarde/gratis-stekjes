@props([
    'content' => 'onbekend',
    'from' => null,
    'class' => 'btn',
    'icon' => true,
])
@php
  use App\Support\WordLidCta;
  $label = WordLidCta::label();
  $href = WordLidCta::href($content, null, $from);
@endphp
<a {{ $attributes->merge(['class' => $class, 'href' => $href]) }}>
  @if($icon)
    <x-icon name="plus" :size="18" />
  @endif
  {{ $label }}
</a>
