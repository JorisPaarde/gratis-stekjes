<?php

declare(strict_types=1);

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * Postcode + huisnummer → straat en plaats via de PDOK Locatieserver (alleen NL, geen sleutel).
 * Resultaten worden niet opgeslagen of gelogd.
 */
final class AdresOpzoeken
{
    /**
     * @return array{ok: bool, error?: string, straat?: string, plaats?: string, postcode?: string, toevoegingen?: list<string>}
     */
    public function zoek(string $postcode, string $huisnummer, string $toevoeging = ''): array
    {
        $postcode = strtoupper(preg_replace('/\s+/', '', $postcode) ?? '');
        $huisnummer = trim($huisnummer);
        $toevoeging = strtoupper(trim($toevoeging));

        if (! preg_match('/^\d{4}[A-Z]{2}$/', $postcode)) {
            return ['ok' => false, 'error' => 'Vul een Nederlandse postcode in, zoals 2011 AB.'];
        }
        if (! preg_match('/^\d{1,5}$/', $huisnummer)) {
            return ['ok' => false, 'error' => 'Vul een huisnummer in (alleen cijfers).'];
        }

        $base = rtrim((string) config('stekjes.pdok_url'), '/');
        if ($base === '') {
            return ['ok' => false, 'error' => 'Opzoeken lukt nu niet. Vul straat en plaats zelf in.'];
        }

        try {
            $response = Http::timeout(5)
                ->acceptJson()
                ->withHeaders(['User-Agent' => 'GratisPlanten/'.config('site.version', '0')])
                ->get($base, [
                    'q' => 'postcode:'.$postcode.' AND huisnummer:'.$huisnummer,
                    'fq' => 'type:adres',
                    'rows' => 20,
                    'fl' => 'straatnaam,huisnummer,huisletter,huisnummertoevoeging,postcode,woonplaatsnaam',
                ]);
        } catch (Throwable) {
            return ['ok' => false, 'error' => 'Opzoeken lukt nu niet. Vul straat en plaats zelf in.'];
        }

        $docs = $response->successful() ? $response->json('response.docs') : null;
        if (! is_array($docs) || $docs === []) {
            return ['ok' => false, 'error' => 'Dit adres vond ik niet. Klopt de postcode en het huisnummer? Je kunt straat en plaats ook zelf invullen.'];
        }

        $treffers = array_values(array_filter($docs, static fn ($d): bool => is_array($d)
            && strtoupper((string) ($d['postcode'] ?? '')) === $postcode
            && (string) ($d['huisnummer'] ?? '') === $huisnummer));
        if ($treffers === []) {
            return ['ok' => false, 'error' => 'Dit adres vond ik niet. Je kunt straat en plaats ook zelf invullen.'];
        }

        $toevoegingen = [];
        foreach ($treffers as $d) {
            $t = strtoupper(trim(((string) ($d['huisletter'] ?? '')).((string) ($d['huisnummertoevoeging'] ?? ''))));
            if ($t !== '') {
                $toevoegingen[] = $t;
            }
        }
        $toevoegingen = array_values(array_unique($toevoegingen));
        sort($toevoegingen);

        $eerste = $treffers[0];

        return [
            'ok' => true,
            'straat' => (string) ($eerste['straatnaam'] ?? ''),
            'plaats' => (string) ($eerste['woonplaatsnaam'] ?? ''),
            'postcode' => substr($postcode, 0, 4).' '.substr($postcode, 4),
            'toevoegingen' => $toevoegingen,
        ];
    }
}
