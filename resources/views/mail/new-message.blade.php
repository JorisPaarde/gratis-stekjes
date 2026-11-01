Hoi,

{{ $afzender !== '' ? $afzender : 'Iemand' }} stuurde je een bericht op {{ config('site.name') }}@if($onderwerp) over: {{ $onderwerp }}@endif.

Lees en beantwoord het hier:
{{ $gesprekUrl }}

Komen er kort na elkaar meer berichten, dan krijg je één mail tot je het gesprek hebt geopend.
In deze mail staat het bericht zelf niet, en ook geen contactgegevens van de ander.

@if($uitzettenUrl !== '')
Liever geen mail bij nieuwe berichten? Zet het uit: {{ $uitzettenUrl }}
@endif

Groet,
{{ config('site.name') }}
