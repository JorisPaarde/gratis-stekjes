<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Deelplek;
use App\Support\Country;
use App\Support\PostalCode;
use Illuminate\Support\Str;

final class DeelplekAanmelding
{
    public function __construct(
        private readonly Deelplekken $deelplekken,
        private readonly LocationGeocoder $geocoder,
    ) {}

    /**
     * @param  array<string, mixed>  $fields
     * @return array{ok: bool, error?: string, deelplek?: Deelplek}
     */
    public function submit(array $fields, bool $allowNetworkGeo = true): array
    {
        $naam = trim(mb_substr((string) ($fields['naam'] ?? ''), 0, 160));
        $type = trim((string) ($fields['type'] ?? ''));
        $adres = trim(mb_substr((string) ($fields['adres'] ?? ''), 0, 255));
        $postcode = trim(mb_substr((string) ($fields['postcode'] ?? ''), 0, 16));
        $plaats = trim(mb_substr((string) ($fields['plaats'] ?? ''), 0, 120));
        $country = Country::normalize((string) ($fields['country'] ?? Country::NL));
        $url = trim(mb_substr((string) ($fields['url'] ?? ''), 0, 500));
        $beschrijving = trim(mb_substr((string) ($fields['beschrijving'] ?? ''), 0, 1000));
        $email = strtolower(trim(mb_substr((string) ($fields['contact_email'] ?? ''), 0, 254)));

        if ($naam === '') {
            return ['ok' => false, 'error' => 'Vul een naam in.'];
        }
        if (! $this->deelplekken->typeValid($type)) {
            return ['ok' => false, 'error' => 'Kies een geldig type.'];
        }
        if ($plaats === '') {
            return ['ok' => false, 'error' => 'Vul een plaats in.'];
        }
        if ($email === '' || ! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return ['ok' => false, 'error' => 'Vul een geldig contact-e-mailadres in.'];
        }
        if ($url !== '' && ! preg_match('#^https?://#i', $url)) {
            return ['ok' => false, 'error' => 'Website moet met http:// of https:// beginnen (of leeg laten).'];
        }

        if ($postcode !== '') {
            $pcValid = PostalCode::validate($postcode, $country);
            if ($pcValid !== true) {
                return ['ok' => false, 'error' => $pcValid];
            }
            $postcode = PostalCode::format(
                PostalCode::normalize($postcode, $country) ?? $postcode,
                $country
            );
        }

        $geoQuery = $this->geoQuery($adres, $postcode, $plaats);
        $resolved = $this->geocoder->resolve($geoQuery, $country, $allowNetworkGeo);
        if (! ($resolved['ok'] ?? false)) {
            return ['ok' => false, 'error' => $resolved['error'] ?? 'Adres of plaats niet gevonden.'];
        }

        // AVG: geen exact straatadres opslaan — alleen genormaliseerde postcode + plaats.
        $pcStored = $postcode !== ''
            ? $postcode
            : ($resolved['postcode'] !== null
                ? PostalCode::format((string) $resolved['postcode'], $country)
                : '');
        // BE: plaatsnaam zoals geocoder die geeft (gemeente/deelgemeente).
        if ($country === Country::BE && trim((string) $resolved['label']) !== '' && ! preg_match('/^\d{4}$/', (string) $resolved['label'])) {
            $plaats = trim(mb_substr((string) $resolved['label'], 0, 120));
        }
        $adresRegel = trim(($pcStored !== '' ? $pcStored.' ' : '').$plaats);

        $slug = $this->uniqueSlug($naam, $plaats);

        $row = Deelplek::query()->create([
            'slug' => $slug,
            'naam' => $naam,
            'type' => $type,
            'plaats' => $plaats,
            'provincie' => '',
            'country' => $country,
            'adres' => mb_substr($adresRegel, 0, 255),
            'lat' => $resolved['lat'],
            'lng' => $resolved['lon'],
            'bron_url' => $url !== '' ? $url : '',
            'geraadpleegd' => now()->toDateString(),
            'visible' => false,
            'source' => Deelplek::SOURCE_AANMELDING,
            'status' => Deelplek::STATUS_PENDING,
            'beschrijving' => $beschrijving,
            'contact_email' => $email,
            'privacy_at' => now(),
            'reviewed_at' => null,
        ]);

        return ['ok' => true, 'deelplek' => $row];
    }

    public function approve(Deelplek $plek): Deelplek
    {
        $plek->update([
            'status' => Deelplek::STATUS_APPROVED,
            'visible' => true,
            'reviewed_at' => now(),
        ]);

        return $plek->refresh();
    }

    public function reject(Deelplek $plek): Deelplek
    {
        $plek->update([
            'status' => Deelplek::STATUS_REJECTED,
            'visible' => false,
            'reviewed_at' => now(),
        ]);

        return $plek->refresh();
    }

    private function geoQuery(string $adres, string $postcode, string $plaats): string
    {
        $parts = array_filter([$adres, $postcode, $plaats], static fn ($p) => $p !== '');

        return implode(' ', $parts);
    }

    private function uniqueSlug(string $naam, string $plaats): string
    {
        $base = Str::slug($naam.'-'.$plaats);
        if ($base === '') {
            $base = 'plek-'.Str::lower(Str::random(8));
        }
        $base = mb_substr($base, 0, 70);
        $slug = $base;
        $i = 2;
        while (Deelplek::query()->where('slug', $slug)->exists()) {
            $slug = mb_substr($base, 0, 70).'-'.$i;
            $i++;
        }

        return $slug;
    }
}
