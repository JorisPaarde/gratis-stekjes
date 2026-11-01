<?php

declare(strict_types=1);

namespace App\Services;

use App\Mail\EersteStekjeMail;
use App\Models\Aanbod;
use App\Models\Conversation;
use App\Models\Ervaring;
use App\Models\Message;
use App\Models\MilestoneSent;
use App\Models\User;
use App\Support\AdminDashboard;
use Illuminate\Support\Facades\DB;
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

    public const MILESTONE_PIONIER = 'pionier';

    /**
     * Ken de exclusieve Pionier-badge toe aan precies één lid (verplaatst hem als iemand
     * anders hem had). Gebruikt milestones_sent, zodat hij blijft staan als aanbod verloopt.
     */
    public function setPionier(User $user): void
    {
        if (! Schema::hasTable('milestones_sent')) {
            return;
        }

        DB::transaction(function () use ($user): void {
            MilestoneSent::query()->where('milestone', self::MILESTONE_PIONIER)->delete();
            MilestoneSent::query()->create([
                'user_id' => $user->id,
                'milestone' => self::MILESTONE_PIONIER,
                'created_at' => now(),
            ]);
        });
    }

    public function pionierUserId(): ?int
    {
        if (! Schema::hasTable('milestones_sent')) {
            return null;
        }
        $id = MilestoneSent::query()->where('milestone', self::MILESTONE_PIONIER)->value('user_id');

        return $id !== null ? (int) $id : null;
    }

    /**
     * Eerste lid dat ooit iets weggaf (vroegste uitdeel-aanbod), beheerders uitgezonderd.
     */
    public function eersteDeler(): ?User
    {
        if (! Schema::hasTable('aanbod')) {
            return null;
        }
        $admins = AdminDashboard::allowedEmails();

        $aanbod = Aanbod::query()
            ->where('type', 'uitdelen')
            ->whereHas('user', function ($q) use ($admins): void {
                if ($admins !== []) {
                    $q->whereNotIn(DB::raw('LOWER(email)'), $admins);
                }
            })
            ->orderBy('created_at')
            ->orderBy('id')
            ->first();

        return $aanbod?->user;
    }

    /** Voornaam, plek en volledig adres ingevuld (telefoon blijft optioneel). */
    public function profielCompleet(User $user): bool
    {
        return trim((string) $user->name) !== ''
            && trim((string) $user->locatie_label) !== ''
            && $user->volledigAdres() !== null;
    }

    private function profielHint(User $user): string
    {
        if (trim((string) $user->locatie_label) === '') {
            return 'Vul je postcode in op je profiel.';
        }

        return 'Vul je adres in op je profiel. Alleen wie jij in een gesprek “ja” geeft, ziet het.';
    }

    /**
     * Alle badges met plaatje, status en wat je nog moet doen.
     *
     * @return list<array{key: string, label: string, earned: bool, hint: string, img: string, exclusief: bool}>
     */
    public function badgesFor(User $user): array
    {
        $uid = (int) $user->id;
        $deelCount = Schema::hasTable('aanbod')
            ? Aanbod::query()->where('user_id', $uid)->where('type', 'uitdelen')->count()
            : 0;
        $aanbodCount = Schema::hasTable('aanbod')
            ? Aanbod::query()->where('user_id', $uid)->count()
            : 0;
        $overdrachtCount = Schema::hasTable('conversations')
            ? Conversation::query()
                ->where(function ($q) use ($uid) {
                    $q->where('user_a_id', $uid)->orWhere('user_b_id', $uid);
                })
                ->whereNotNull('overdracht_at')
                ->count()
            : 0;
        $ervaringCount = Schema::hasTable('ervaringen')
            ? Ervaring::query()->where('user_id', $uid)->count()
            : 0;
        $berichtCount = Schema::hasTable('messages')
            ? Message::query()->where('sender_id', $uid)->count()
            : 0;
        $isPionier = $this->pionierUserId() === $uid;
        $lidSinds = $user->created_at;
        $jaarLid = $lidSinds !== null && $lidSinds->lte(now()->subYear());
        $nogDagen = $lidSinds !== null ? max(0, (int) ceil(now()->diffInDays($lidSinds->copy()->addYear(), false))) : 365;

        $nog = static fn (int $doel, int $nu, string $enkel, string $meer): string => ($doel - $nu) === 1
            ? 'Nog 1 '.$enkel.' te gaan.'
            : 'Nog '.($doel - min($doel, $nu)).' '.$meer.' te gaan.';

        $badges = [
            ['geworteld', 'Geworteld', trim((string) $user->locatie_label) !== '',
                'Je plek staat ingevuld. Buren kunnen je vinden.',
                'Vul op je profiel in waar je ongeveer woont.'],
            ['profiel_compleet', 'Profiel compleet', $this->profielCompleet($user),
                'Naam, plek en adres staan erin. Afspreken gaat nu in één keer.',
                $this->profielHint($user)],
            ['eerste_stekje', 'Eerste stekje', $aanbodCount >= 1 || $overdrachtCount >= 1,
                'Je hebt je eerste aanbod geplaatst of een overdracht afgerond.',
                'Plaats je eerste aanbod of rond een overdracht af.'],
            ['eerste_praatje', 'Eerste praatje', $berichtCount >= 1,
                'Je stuurde je eerste bericht.',
                'Stuur iemand een bericht over een plant.'],
            ['eerste_overdracht', 'Eerste overdracht', $overdrachtCount >= 1,
                'Er ging een plant van hand tot hand.',
                'Spreek af, geef of haal een plant op en zet de overdracht op afgerond.'],
            ['gulle_gever', 'Gulle gever', $deelCount >= 5,
                'Je hebt al vijf keer iets weggegeven.',
                $nog(5, $deelCount, 'keer weggeven', 'keer weggeven')],
            ['stekjesheld', 'Stekjesheld', $deelCount >= 10,
                'Tien keer iets weggegeven. Wat een groene buurt.',
                $nog(10, $deelCount, 'keer weggeven', 'keer weggeven')],
            ['verbinder', 'Verbinder', $overdrachtCount >= 3,
                'Drie overdrachten: jij brengt mensen samen.',
                $nog(3, $overdrachtCount, 'overdracht', 'overdrachten')],
            ['verteller', 'Verteller', $ervaringCount >= 1,
                'Je hebt een ervaring gedeeld.',
                'Deel een korte ervaring, met of zonder foto.'],
            ['vaste_plant', 'Vaste plant', $jaarLid,
                'Al een jaar lid. Dank je wel!',
                'Over '.$nogDagen.' '.($nogDagen === 1 ? 'dag' : 'dagen').' ben je een jaar lid.'],
        ];

        $out = [];
        if ($isPionier) {
            $out[] = [
                'key' => self::MILESTONE_PIONIER,
                'label' => 'Pionier',
                'earned' => true,
                'hint' => 'De allereerste die iets weggaf op '.config('site.name').'. Deze badge bestaat maar één keer.',
                'img' => '/assets/badges/pionier.svg',
                'exclusief' => true,
            ];
        }
        foreach ($badges as [$key, $label, $earned, $hintJa, $hintNee]) {
            $out[] = [
                'key' => $key,
                'label' => $label,
                'earned' => (bool) $earned,
                'hint' => $earned ? $hintJa : $hintNee,
                'img' => '/assets/badges/'.$key.'.svg',
                'exclusief' => false,
            ];
        }

        return $out;
    }
}
