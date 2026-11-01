<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Aanbod;
use App\Models\User;
use Illuminate\Support\Facades\Schema;

/**
 * Eén concrete volgende stap voor een lid: eerst wat het account bruikbaar maakt,
 * daarna de eerstvolgende badge. Getoond bovenaan elke accountpagina.
 */
final class VolgendeStap
{
    public function __construct(
        private readonly MilestoneService $milestones,
        private readonly MessagingService $messaging,
    ) {}

    /**
     * @return array{titel: string, tekst: string, knop: string, url: string, img: ?string, badge: ?string}|null
     */
    public function voor(User $user): ?array
    {
        $ongelezen = $this->messaging->unreadCount((int) $user->id);
        if ($ongelezen > 0) {
            return $this->stap(
                $ongelezen === 1 ? 'Je hebt een nieuw bericht' : 'Je hebt '.$ongelezen.' nieuwe berichten',
                'Iemand wacht op je antwoord.',
                'Naar je inbox',
                route('account.inbox'),
            );
        }

        if (trim((string) $user->locatie_label) === '') {
            return $this->stap(
                'Vul je postcode in',
                'Dan zie je wat er bij jou in de buurt te halen is, en vinden buren jouw aanbod.',
                'Naar je profiel',
                route('account.profiel'),
                'geworteld',
            );
        }

        $heeftAanbod = Schema::hasTable('aanbod') && Aanbod::query()->where('user_id', $user->id)->exists();
        if (! $heeftAanbod) {
            return $this->stap(
                'Zet je eerste stekje online',
                'Een plantje, stekje of zakje zaad dat je over hebt. Het kost je een minuut.',
                'Iets aanbieden',
                route('account.aanbod'),
                'eerste_stekje',
            );
        }

        foreach ($this->milestones->badgesFor($user) as $badge) {
            if ($badge['earned'] || $badge['exclusief'] || $badge['key'] === 'vaste_plant') {
                continue;
            }
            [$knop, $url] = $this->actie($badge['key']);

            return $this->stap(
                'Volgende badge: '.$badge['label'],
                $badge['hint'],
                $knop,
                $url,
                $badge['key'],
                $badge['img'],
            );
        }

        return null;
    }

    /** @return array{0: string, 1: string} */
    private function actie(string $key): array
    {
        return match ($key) {
            'geworteld', 'profiel_compleet' => ['Naar je profiel', route('account.profiel')],
            'eerste_stekje', 'gulle_gever', 'stekjesheld' => ['Iets aanbieden', route('account.aanbod')],
            'eerste_praatje' => ['Kijk wat er in de buurt is', route('aanbod')],
            'eerste_overdracht', 'verbinder' => ['Naar je gesprekken', route('account.inbox')],
            'verteller' => ['Deel een ervaring', route('account.ervaringen.create')],
            default => ['Naar je account', route('account.index').'#badges'],
        };
    }

    /** @return array{titel: string, tekst: string, knop: string, url: string, img: ?string, badge: ?string} */
    private function stap(string $titel, string $tekst, string $knop, string $url, ?string $badge = null, ?string $img = null): array
    {
        return [
            'titel' => $titel,
            'tekst' => $tekst,
            'knop' => $knop,
            'url' => $url,
            'badge' => $badge,
            'img' => $img ?? ($badge !== null ? '/assets/badges/'.$badge.'.svg' : null),
        ];
    }
}
