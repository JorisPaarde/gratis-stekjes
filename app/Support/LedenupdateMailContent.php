<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\User;
use App\Services\MilestoneService;

/**
 * Exacte tekst voor de eenmalige ledenupdate-mail. Geen e-mailadressen of straatadressen.
 */
final class LedenupdateMailContent
{
    public const SUBJECT = 'Nieuw bij Gratis planten, en je hebt al een badge';

    public const PROFIEL_URL = 'https://gratisplanten.nl/account/profiel';

    /**
     * @return array{naam: string, nr: int, tier: int, vraag_postcode: bool}|null
     *         null als naam, lidnummer of tier ontbreekt
     */
    public static function placeholders(User $user, MilestoneService $milestones): ?array
    {
        $naam = trim((string) $user->name);
        $nr = $milestones->lidnummer($user);
        $tier = self::tierFor($nr);

        if ($naam === '' || $nr === null || $tier === null) {
            return null;
        }

        return [
            'naam' => $naam,
            'nr' => $nr,
            'tier' => $tier,
            'vraag_postcode' => ! $user->hasValidPostcode(),
        ];
    }

    /** Hoogste (strakste) oprichterstier: 10, 50 of 100. */
    public static function tierFor(?int $lidnummer): ?int
    {
        if ($lidnummer === null) {
            return null;
        }

        foreach (array_keys(MilestoneService::OPRICHTERS) as $grens) {
            if ($lidnummer <= $grens) {
                return (int) $grens;
            }
        }

        return null;
    }

    /**
     * @param  array{naam: string, nr: int, tier: int, vraag_postcode: bool}  $data
     */
    public static function plainBody(array $data): string
    {
        $lines = [
            'Hoi '.$data['naam'].',',
            '',
            'Leuk dat je meedoet met Gratis planten. Er is de laatste tijd veel bijgekomen, dus even kort wat je nu kunt:',
            '',
            '- Bij je aanbod zet je hoeveel stuks je hebt. Op 0 gaat het vanzelf offline.',
            '- Zoek op een plant, dan zie je eerst wat het dichtst bij je is.',
            '- Op gratisplanten.nl/vermeerderen staat een plantzoeker. Typ bijvoorbeeld \'vrouwentong\' en je ziet hoe je hem stekt.',
            '- Op gratisplanten.nl/initiatieven vind je groepen die met planten bezig zijn, zoals de Struikrovers.',
            '- Je kunt je account koppelen aan je volkstuin of vereniging.',
            '',
            'Je hebt al een badge: je bent lid nummer '.$data['nr'].', een van de eerste '.$data['tier'].'. Er zijn er meer te verdienen, zoals je eerste stekje, je eerste praatje of vijf keer iets weggeven. Op je account zie je welke je nog mist.',
            '',
        ];

        if ($data['vraag_postcode']) {
            $lines[] = 'Vul je postcode in, dan zie je wat er bij jou in de buurt is.';
            $lines[] = '';
        }

        $lines[] = 'Naar je profiel: '.self::PROFIEL_URL;
        $lines[] = '';
        $lines[] = 'Groene groet,';
        $lines[] = 'Gratis planten';

        return implode("\n", $lines);
    }

    /**
     * Eenvoudige HTML-variant met dezelfde tekst en klikbare profiellink.
     *
     * @param  array{naam: string, nr: int, tier: int, vraag_postcode: bool}  $data
     */
    public static function htmlBody(array $data): string
    {
        $esc = static fn (string $s): string => htmlspecialchars($s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $plain = self::plainBody($data);
        $url = self::PROFIEL_URL;
        $linked = preg_replace(
            '/'.preg_quote($url, '/').'/',
            '<a href="'.$esc($url).'">'.$esc($url).'</a>',
            $esc($plain),
            1
        );

        return '<p style="white-space:pre-wrap;font-family:sans-serif;font-size:15px;line-height:1.5">'.$linked.'</p>';
    }

    /**
     * Welk veld ontbreekt (voor dry-run exit 1).
     *
     * @return list<string>
     */
    public static function missingFields(User $user, MilestoneService $milestones): array
    {
        $missing = [];
        if (trim((string) $user->name) === '') {
            $missing[] = 'naam';
        }
        $nr = $milestones->lidnummer($user);
        if ($nr === null) {
            $missing[] = 'nr';
        }
        if (self::tierFor($nr) === null) {
            $missing[] = 'tier';
        }

        return $missing;
    }
}
