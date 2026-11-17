{!! strip_tags($header ?? '') !!}

{!! strip_tags($slot) !!}
@isset($subcopy)

{{-- Markdown-link [tekst](url) wordt in platte tekst gewoon de url. --}}
{!! preg_replace('/\[([^\]]*)\]\(([^)]*)\)/', '$2', strip_tags($subcopy)) !!}
@endisset

{!! strip_tags($footer ?? '') !!}
