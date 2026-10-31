Hoi,

Over een week haal ik je aanbod{{ $tekstPreview !== '' ? ' ("'.$tekstPreview.'")' : '' }} van de kaart en uit zoeken op {{ config('site.name') }}.
Wil je het nog {{ (int) config('stekjes.aanbod_ttl_days', 60) }} dagen laten staan? Verleng met één klik:
{{ $renewUrl }}

De link is eenmalig en tijdelijk geldig.

Groet,
{{ config('site.name') }}
