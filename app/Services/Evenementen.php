<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Evenement;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

final class Evenementen
{
    /** @return array<string, string> */
    public function typeLabels(): array
    {
        /** @var array<string, string> $labels */
        $labels = config('stekjes.evenement_types', []);

        return $labels;
    }

    /** @return list<string> */
    public function types(): array
    {
        return array_keys($this->typeLabels());
    }

    public function typeValid(string $type): bool
    {
        return array_key_exists($type, $this->typeLabels());
    }

    /** @return list<string> */
    public function provincies(): array
    {
        /** @var list<string> $list */
        $list = config('stekjes.provincies', []);

        return $list;
    }

    public function provincieValid(string $provincie): bool
    {
        return in_array($provincie, $this->provincies(), true);
    }

    /**
     * @param  array{type?: string, provincie?: string}  $filters
     * @return Collection<int, Evenement>
     */
    public function queryVisible(array $filters = []): Collection
    {
        $query = Evenement::query()->visible()->orderBy('start_datum')->orderBy('id');

        $type = trim((string) ($filters['type'] ?? ''));
        if ($type !== '' && $this->typeValid($type)) {
            $query->ofType($type);
        }

        $provincie = trim((string) ($filters['provincie'] ?? ''));
        if ($provincie !== '' && $this->provincieValid($provincie)) {
            $query->inProvincie($provincie);
        }

        return $query->get();
    }

    /**
     * @param  Collection<int, Evenement>  $items
     * @return array{upcoming: Collection<int, Collection<int, Evenement>>, past: Collection<int, Evenement>}
     */
    public function splitAndGroup(Collection $items, ?CarbonInterface $today = null): array
    {
        $today ??= Carbon::now('Europe/Amsterdam')->startOfDay();

        $upcoming = $items->filter(fn (Evenement $e) => $e->isUpcoming($today))->values();
        $past = $items->filter(fn (Evenement $e) => $e->isPast($today))
            ->sortByDesc(fn (Evenement $e) => $e->effectiveEndDate()->format('Y-m-d').'-'.$e->id)
            ->values();

        $grouped = $upcoming->groupBy(function (Evenement $e) {
            return $e->start_datum->format('Y-m');
        });

        return [
            'upcoming' => $grouped,
            'past' => $past,
        ];
    }

    public function monthLabel(string $yearMonth): string
    {
        $months = [
            1 => 'januari', 2 => 'februari', 3 => 'maart', 4 => 'april',
            5 => 'mei', 6 => 'juni', 7 => 'juli', 8 => 'augustus',
            9 => 'september', 10 => 'oktober', 11 => 'november', 12 => 'december',
        ];
        [$year, $month] = array_pad(explode('-', $yearMonth, 2), 2, '0');
        $m = (int) $month;
        $label = $months[$m] ?? $yearMonth;

        return $label.' '.$year;
    }

    public function formatDateRange(Evenement $event): string
    {
        $start = $event->start_datum;
        $end = $event->eind_datum;

        if ($end === null || $end->equalTo($start)) {
            return $start->format('j-n-Y');
        }

        if ($start->year === $end->year && $start->month === $end->month) {
            return $start->format('j').'–'.$end->format('j-n-Y');
        }

        return $start->format('j-n-Y').' – '.$end->format('j-n-Y');
    }

    /**
     * Schema.org Event-object voor JSON-LD.
     *
     * @return array<string, mixed>
     */
    public function toSchemaEvent(Evenement $event): array
    {
        $schema = [
            '@type' => 'Event',
            'name' => (string) $event->titel,
            'startDate' => $event->start_datum->format('Y-m-d'),
            'eventAttendanceMode' => 'https://schema.org/OfflineEventAttendanceMode',
            'eventStatus' => 'https://schema.org/EventScheduled',
            'inLanguage' => 'nl-NL',
            'location' => [
                '@type' => 'Place',
                'name' => (string) $event->plaats,
                'address' => [
                    '@type' => 'PostalAddress',
                    'addressLocality' => (string) $event->plaats,
                    'addressRegion' => (string) $event->provincie,
                    'addressCountry' => 'NL',
                ],
            ],
        ];

        if ($event->eind_datum !== null) {
            $schema['endDate'] = $event->eind_datum->format('Y-m-d');
        }

        $organisator = trim((string) $event->organisator);
        if ($organisator !== '') {
            $schema['organizer'] = [
                '@type' => 'Organization',
                'name' => $organisator,
            ];
        }

        $url = trim((string) $event->url);
        if ($url !== '') {
            $schema['url'] = $url;
        }

        $typeLabels = $this->typeLabels();
        $type = (string) $event->type;
        if (isset($typeLabels[$type])) {
            $schema['description'] = $typeLabels[$type].' in '.$event->plaats;
        }

        return $schema;
    }

    /**
     * @param  Collection<int, Evenement>  $events
     * @return list<array<string, mixed>>
     */
    public function schemaEvents(Collection $events): array
    {
        return $events->map(fn (Evenement $e) => $this->toSchemaEvent($e))->values()->all();
    }

    /**
     * Admin: maak of werk evenement bij.
     *
     * @param  array<string, mixed>  $fields
     * @return array{ok: bool, error?: string, id?: int}
     */
    public function save(?int $id, array $fields): array
    {
        $titel = trim(mb_substr((string) ($fields['titel'] ?? ''), 0, 200));
        $type = trim((string) ($fields['type'] ?? ''));
        $plaats = trim(mb_substr((string) ($fields['plaats'] ?? ''), 0, 120));
        $provincie = trim((string) ($fields['provincie'] ?? ''));
        $organisator = trim(mb_substr((string) ($fields['organisator'] ?? ''), 0, 160));
        $url = trim(mb_substr((string) ($fields['url'] ?? ''), 0, 500));
        $bron = trim(mb_substr((string) ($fields['bron'] ?? ''), 0, 500));
        $visible = ! empty($fields['visible']);

        if ($titel === '') {
            return ['ok' => false, 'error' => 'Vul een titel in.'];
        }
        if (! $this->typeValid($type)) {
            return ['ok' => false, 'error' => 'Kies een geldig type.'];
        }
        if ($plaats === '') {
            return ['ok' => false, 'error' => 'Vul een plaats (gemeente) in.'];
        }
        if (! $this->provincieValid($provincie)) {
            return ['ok' => false, 'error' => 'Kies een geldige provincie.'];
        }
        if ($url !== '' && ! preg_match('#^https?://#i', $url)) {
            return ['ok' => false, 'error' => 'Externe link moet met http:// of https:// beginnen (of leeg laten).'];
        }

        $startRaw = trim((string) ($fields['start_datum'] ?? ''));
        $start = $this->parseDate($startRaw);
        if ($start === null) {
            return ['ok' => false, 'error' => 'Vul een geldige startdatum in (JJJJ-MM-DD).'];
        }

        $eindRaw = trim((string) ($fields['eind_datum'] ?? ''));
        $eind = null;
        if ($eindRaw !== '') {
            $eind = $this->parseDate($eindRaw);
            if ($eind === null) {
                return ['ok' => false, 'error' => 'Einddatum is ongeldig (JJJJ-MM-DD of leeg).'];
            }
            if ($eind->lt($start)) {
                return ['ok' => false, 'error' => 'Einddatum mag niet vóór de startdatum liggen.'];
            }
        }

        $gecontroleerdRaw = trim((string) ($fields['gecontroleerd_op'] ?? ''));
        $gecontroleerd = null;
        if ($gecontroleerdRaw !== '') {
            $gecontroleerd = $this->parseDate($gecontroleerdRaw);
            if ($gecontroleerd === null) {
                return ['ok' => false, 'error' => 'Datum laatst gecontroleerd is ongeldig (JJJJ-MM-DD of leeg).'];
            }
        }

        $payload = [
            'titel' => $titel,
            'type' => $type,
            'start_datum' => $start->format('Y-m-d'),
            'eind_datum' => $eind?->format('Y-m-d'),
            'plaats' => $plaats,
            'provincie' => $provincie,
            'organisator' => $organisator,
            'url' => $url,
            'bron' => $bron,
            'gecontroleerd_op' => $gecontroleerd?->format('Y-m-d'),
            'visible' => $visible,
        ];

        $existing = $id ? Evenement::query()->find($id) : null;
        if ($existing) {
            $existing->update($payload);

            return ['ok' => true, 'id' => (int) $existing->id];
        }

        $row = Evenement::query()->create($payload);

        return ['ok' => true, 'id' => (int) $row->id];
    }

    /** @return array{ok: bool, error?: string} */
    public function hide(int $id): array
    {
        if ($id <= 0) {
            return ['ok' => false, 'error' => 'Ongeldig evenement.'];
        }
        $row = Evenement::query()->find($id);
        if ($row === null) {
            return ['ok' => false, 'error' => 'Evenement niet gevonden.'];
        }
        $row->update(['visible' => false]);

        return ['ok' => true];
    }

    private function parseDate(string $raw): ?Carbon
    {
        if ($raw === '' || ! preg_match('/^\d{4}-\d{2}-\d{2}$/', $raw)) {
            return null;
        }
        try {
            $date = Carbon::createFromFormat('Y-m-d', $raw);

            return $date !== false ? $date->startOfDay() : null;
        } catch (\Throwable) {
            return null;
        }
    }
}
