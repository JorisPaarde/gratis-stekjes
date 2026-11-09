@if($voornaam !== '')
Hoi {{ $voornaam }},
@else
Hoi,
@endif

Je account bij {{ $site }} is bevestigd — leuk dat je erbij bent.
Nog geen stekje of zaadje online gezet? Met één aanbod help je al iemand in de buurt.

Zet je eerste stekje online:
{{ $aanbiedenUrl }}

Geen haast. Liever geen tipmails meer? Zet ze uit: {{ $uitzettenUrl }}

Groet,
{{ $site }}
