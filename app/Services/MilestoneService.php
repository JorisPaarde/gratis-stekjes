<?php

declare(strict_types=1);

namespace App\Services;

use App\Mail\EersteStekjeMail;
use App\Models\Aanbod;
use App\Models\Conversation;
use App\Models\Ervaring;
use App\Models\MilestoneSent;
use App\Models\User;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;
use Throwable;

/**
 * Mijlpaalmail 'eerste stekje' + lichte badge-status voor de accountpagina.
 * Mail staat standaard UIT via MILESTONE_MAIL_ENABLED; bij uit: alleen loggen, niets queue'en/versturen.
 */
final class MilestoneService
{
    public const MILESTONE_EERSTE_STEKJE = 'eerste_stekje';

    /**
     * Trigger na eerste aanbod of eerste afgeronde overdracht.
     * Idempotent: max één keer per lid (rij in milestones_sent).
     */
    public function maybeSendEersteStekje(User $user, string $trigger): void
    {
        if (! Schema::hasTable('milestones_sent')) {
            return;
        }

        $already = MilestoneSent::query()
            ->where('user_id', $user->id)
            ->where('milestone', self::MILESTONE_EERSTE_STEKJE)
            ->exists();
        if ($already) {
            return;
        }

        // Markeer eerst (unieke constraint) zodat parallelle triggers geen dubbele mail geven.
        try {
            MilestoneSent::query()->create([
                'user_id' => $user->id,
                'milestone' => self::MILESTONE_EERSTE_STEKJE,
                'created_at' => now(),
            ]);
        } catch (Throwable) {
            // Race: andere request won — klaar.
            return;
        }

        $enabled = (bool) config('stekjes.milestone_mail_enabled', false);
        if (! $enabled) {
            // Geen PII in de log (geen e-mail/naam).
            Log::info('milestone.eerste_stekje.zou_versturen', [
                'user_id' => (int) $user->id,
                'trigger' => $trigger,
                'mail_enabled' => false,
            ]);

            return;
        }

        try {
            Mail::to($user->email)->send(new EersteStekjeMail(
                voornaam: (string) $user->name,
                ervaringUrl: route('account.ervaringen.create'),
            ));
        } catch (Throwable $e) {
            Log::warning('milestone.eerste_stekje.send_failed', [
                'user_id' => (int) $user->id,
                'error' => class_basename($e),
            ]);
        }
    }

    /**
     * @return list<array{key: string, label: string, earned: bool, hint: string}>
     */
    public function badgesFor(User $user): array
    {
        $aanbodCount = Schema::hasTable('aanbod')
            ? Aanbod::query()->where('user_id', $user->id)->count()
            : 0;
        $overdrachtCount = Schema::hasTable('conversations')
            ? Conversation::query()
                ->where(function ($q) use ($user) {
                    $q->where('user_a_id', $user->id)->orWhere('user_b_id', $user->id);
                })
                ->whereNotNull('overdracht_at')
                ->count()
            : 0;
        $ervaringCount = Schema::hasTable('ervaringen')
            ? Ervaring::query()->where('user_id', $user->id)->count()
            : 0;

        $eersteStekje = $aanbodCount >= 1 || $overdrachtCount >= 1;
        $vijfGedeeld = $aanbodCount >= 5;
        $eersteErvaring = $ervaringCount >= 1;

        return [
            [
                'key' => 'eerste_stekje',
                'label' => 'Eerste stekje',
                'earned' => $eersteStekje,
                'hint' => $eersteStekje
                    ? 'Je hebt je eerste aanbod geplaatst of een overdracht afgerond.'
                    : 'Plaats je eerste aanbod of rond een overdracht af.',
            ],
            [
                'key' => 'vijf_gedeeld',
                'label' => '5 gedeeld',
                'earned' => $vijfGedeeld,
                'hint' => $vijfGedeeld
                    ? 'Je hebt al vijf keer iets aangeboden.'
                    : 'Nog '.(5 - min(5, $aanbodCount)).' aanbod te gaan.',
            ],
            [
                'key' => 'eerste_ervaring',
                'label' => 'Eerste ervaring',
                'earned' => $eersteErvaring,
                'hint' => $eersteErvaring
                    ? 'Je hebt een ervaring gedeeld.'
                    : 'Deel een korte ervaring (met of zonder foto).',
            ],
        ];
    }
}
