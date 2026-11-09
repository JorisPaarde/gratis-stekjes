<x-mail::message>
{{ $voornaam !== '' ? 'Hoi '.$voornaam.',' : 'Hoi!' }}

Je account bij {{ $site }} is bevestigd — leuk dat je erbij bent.
Nog geen stekje of zaadje online gezet? Met één aanbod help je al iemand in de buurt.

<x-mail::button :url="$aanbiedenUrl" color="primary">
Zet je eerste stekje online
</x-mail::button>

Geen haast. Liever geen tipmails meer? [Zet ze uit]({{ $uitzettenUrl }})

Groet,<br>
{{ $site }}
</x-mail::message>
