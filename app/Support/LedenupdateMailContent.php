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

    /** Aanhef-typen voor dry-run/log (nooit de naam zelf). */
    public const AANHEF_NAAM = 'naam';

    public const AANHEF_AFGELEID = 'afgeleid';

    public const AANHEF_ZONDER = 'zonder naam';

    /** Lokale delen die geen voornaam zijn. */
    private const GENERIEK = [
        'info', 'contact', 'mail', 'email', 'admin', 'hallo', 'hello', 'hoi', 'post', 'noreply',
        'no', 'webmaster', 'support', 'service', 'office', 'kantoor', 'sales', 'team', 'test',
        'user', 'gebruiker', 'postmaster', 'secretariaat', 'redactie', 'familie', 'fam',
    ];

    /**
     * @return array{naam: string, nr: int, tier: int, vraag_postcode: bool, aanhef: string}|null
     *         null als lidnummer of tier ontbreekt, of de naam leeg is
     */
    public static function placeholders(User $user, MilestoneService $milestones): ?array
    {
        $nr = $milestones->lidnummer($user);
        $tier = self::tierFor($nr);
        [$naam, $aanhef] = self::aanhef($user);

        if ($aanhef === null || $nr === null || $tier === null) {
            return null;
        }

        return [
            'naam' => $naam,
            'nr' => $nr,
            'tier' => $tier,
            'vraag_postcode' => ! $user->hasValidPostcode(),
            'aanhef' => $aanhef,
        ];
    }

    /**
     * Naam voor «Hoi …,». Normale naam: ongewijzigd. Naam die op een e-mailadres lijkt:
     * voornaam afgeleid uit het lokale deel van het e-mailadres, of geen naam («Hoi,»).
     * Wijzigt het user-record niet.
     *
     * @return array{0: string, 1: string|null} [naam, aanhef-type] — type null = lege naam (blokkerend)
     */
    public static function aanhef(User $user): array
    {
        $naam = trim((string) $user->name);
        if ($naam === '') {
            return ['', null];
        }
        if (! Naam::lijktOpEmail($naam, (string) $user->email)) {
            return [$naam, self::AANHEF_NAAM];
        }

        $afgeleid = self::voornaamUitEmail((string) $user->email);
        if ($afgeleid === null) {
            return ['', self::AANHEF_ZONDER];
        }

        return [$afgeleid, self::AANHEF_AFGELEID];
    }

    /** Voornaam uit het deel vóór @ (bijv. jan.devries → Jan), of null als het geen naam lijkt. */
    public static function voornaamUitEmail(string $email): ?string
    {
        $lokaal = mb_strtolower(trim(explode('@', $email, 2)[0]));
        $delen = preg_split('/[._+\-0-9]+/u', $lokaal, -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $token = (string) ($delen[0] ?? '');
        $token = (string) preg_replace('/[0-9]+/u', '', $token);

        if ($token === '' || ! preg_match('/^\p{L}+$/u', $token) || mb_strlen($token) < 2) {
            return null;
        }
        if (in_array($token, self::GENERIEK, true)) {
            return null;
        }
        // Initialen: 2–3 letters zonder klinker (bijv. «jk», «pvd»).
        if (mb_strlen($token) <= 3 && ! preg_match('/[aeiouy\x{00E0}-\x{00FF}]/u', $token)) {
            return null;
        }

        return mb_strtoupper(mb_substr($token, 0, 1)).mb_substr($token, 1);
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
     * @param  array{naam: string, nr: int, tier: int, vraag_postcode: bool, aanhef?: string}  $data
     */
    public static function plainBody(array $data): string
    {
        $lines = [
            ($data['naam'] !== '' ? 'Hoi '.$data['naam'].',' : 'Hoi,'),
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
     * @param  array{naam: string, nr: int, tier: int, vraag_postcode: bool, aanhef?: string}  $data
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
        if (self::aanhef($user)[1] === null) {
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
