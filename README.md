# Gratis stekjes

Een plek waar mensen gratis stekjes aan elkaar uitdelen. Je zet in je profiel wat je over hebt, iemand uit de buurt vindt het, komt het ophalen, en het enige wat het kost is een praatje bij de voordeur.

## Neem het mee

Dit project is van iedereen. Deel het, gebruik het, kopieer het, pas het aan, begin er je eigen versie mee in je dorp, wijk of land. Je hoeft niets te vragen en niemand te noemen. Doe ermee wat je wilt.

Het doel is simpel: de wereld een stukje groener maken. Meer planten in meer tuinen, op meer balkons en in meer vensterbanken, en een beetje meer contact tussen mensen die bij elkaar in de buurt wonen. Hoe vaker dit idee ergens opduikt, hoe beter.

En heb je zelf iets over, een stekje, zaden, een pot of wat kennis: deel het uit.

## Het idee

- **Gratis.** Niets wordt verkocht. Geven en ophalen, meer niet.
- **Van mens tot mens.** Je zoekt op postcode of plaats wat er in de buurt is en stuurt via de site een berichtje. Pas als jullie allebei verder willen, zien jullie elkaars contactgegevens.
- **Privacy eerst.** Op de kaart staat alleen een benaderde plek (postcode-4 of wijk), nooit een adres of e-mailadres.
- **Groen en eerlijk.** Planten die invasief zijn in Nederland horen er niet tussen; de site waarschuwt daarvoor, met bronnen.

## Zelf draaien

De site is bewust licht gehouden: statische pagina's met een klein stukje PHP en een MySQL- of SQLite-database. Dat draait op vrijwel elke gewone webhosting.

Wat je nodig hebt:

- PHP 8.2 of nieuwer
- MySQL/MariaDB (of SQLite om lokaal te testen)
- Een mailbox met SMTP voor de bevestigingsmails

Globaal gaat het zo (de precieze stappen staan in de docs zodra de eerste versie er is):

1. Clone de repo.
2. Kopieer `config.example.php` naar een `config.php` buiten je webroot en vul je eigen sitenaam, adres, database en mailgegevens in. Zet die gegevens nooit in git.
3. Draai de databasemigraties.
4. Zet de bestanden op je hosting, of start lokaal met `php -S localhost:8000`.

De naam en het webadres staan alleen in de config, dus je eigen versie een andere naam of een eigen domein geven kost één regel.

## Licentie

- **Code:** [The Unlicense](LICENSE). Publiek domein, zonder voorwaarden.
- **Teksten, tips en andere inhoud:** [CC0 1.0](LICENSE-CONTENT.md). Ook publiek domein.

Bronnen die we gebruiken (zoals de EU-lijst van invasieve soorten) houden hun eigen voorwaarden; die staan erbij vermeld.
