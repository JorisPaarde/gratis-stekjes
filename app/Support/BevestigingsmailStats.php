<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\MailLog;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;

/**
 * Samenvatting van bevestigingsmails per lid uit mail_log (één groupBy-query).
 *
 * Telt alleen VerifyEmailDutch + VerifyEmailReminderDutch. Andere mailtypes tellen niet mee.
 *
 * De eerste geslaagde VerifyEmailDutch = aanmelding. Elke volgende geslaagde
 * VerifyEmailDutch (handmatig Herstuur / bulk Herinner) én elke geslaagde
 * VerifyEmailReminderDutch telt als herinnering.
 */
final class BevestigingsmailStats
{
    /** @var list<string> */
    public const TYPES = [
        'VerifyEmailDutch',
        'VerifyEmailReminderDutch',
    ];

    public function __construct(
        /** Geslaagde VerifyEmailDutch (incl. de eerste bij aanmelding). */
        public readonly int $verifyDutch = 0,
        /** Geslaagde VerifyEmailReminderDutch (automatische 24u-herinnering). */
        public readonly int $autoHerinnering = 0,
        public readonly int $mislukt = 0,
        public readonly ?CarbonInterface $laatsteOk = null,
        public readonly ?CarbonInterface $laatstePoging = null,
    ) {}

    /** 1 als er minstens één geslaagde VerifyEmailDutch is, anders 0. */
    public function aanmelding(): int
    {
        return $this->verifyDutch > 0 ? 1 : 0;
    }

    /**
     * Aantal herinneringen: VerifyEmailDutch na de eerste + alle VerifyEmailReminderDutch.
     */
    public function herinneringen(): int
    {
        return max(0, $this->verifyDutch - 1) + $this->autoHerinnering;
    }

    public function totaalOk(): int
    {
        return $this->verifyDutch + $this->autoHerinnering;
    }

    public function isLeeg(): bool
    {
        return $this->totaalOk() === 0 && $this->mislukt === 0;
    }

    public function laatste(): ?CarbonInterface
    {
        return $this->laatsteOk ?? $this->laatstePoging;
    }

    /** Compacte NL-tekst voor de gebruikerslijst. */
    public function samenvatting(): string
    {
        $delen = [$this->tellingLabel()];

        $laatste = $this->laatste();
        if ($laatste !== null) {
            $delen[] = 'laatste: '.$this->formatLaatste($laatste);
        }

        if ($this->mislukt > 0) {
            $delen[] = $this->mislukt === 1 ? '1 mislukt' : $this->mislukt.' mislukt';
        }

        return implode(', ', $delen);
    }

    /**
     * Hoofdregel: «Bevestigingsmail: 1× bij aanmelding + N herinneringen»
     * of «Bevestigingsmail: alleen bij aanmelding».
     */
    public function tellingLabel(): string
    {
        $n = $this->herinneringen();

        if ($this->aanmelding() === 0) {
            if ($n === 0) {
                return 'Bevestigingsmail: nog geen';
            }

            $her = $n === 1 ? '1 herinnering' : $n.' herinneringen';

            return 'Bevestigingsmail: '.$her;
        }

        if ($n === 0) {
            return 'Bevestigingsmail: alleen bij aanmelding';
        }

        $her = $n === 1 ? '1 herinnering' : $n.' herinneringen';

        return 'Bevestigingsmail: 1× bij aanmelding + '.$her;
    }

    /** Detailrij «Herinnerd»: «2× · laatste …» of «0×». */
    public function herinnerdLabel(): string
    {
        $n = $this->herinneringen();
        $basis = $n.'×';
        $laatste = $this->laatste();
        if ($laatste === null) {
            return $n === 0 ? 'nog niet' : $basis;
        }

        return $basis.' · laatste '.$this->formatLaatste($laatste);
    }

    /** Korte hint bij de herstuur-knop. */
    public function laatsteVerzendHint(): ?string
    {
        $laatste = $this->laatste();
        if ($laatste === null) {
            return null;
        }

        return 'Laatst verstuurd: '.$this->formatLaatste($laatste);
    }

    public function formatLaatste(CarbonInterface $at): string
    {
        $lokaal = $at->copy()->timezone('Europe/Amsterdam');

        return $lokaal->format('d-m-Y H:i').' ('.$at->diffForHumans().')';
    }

    /**
     * Eén query met groupBy voor alle gevraagde user_ids. Geen N+1.
     *
     * @param  list<int|string>  $userIds
     * @return Collection<int, self> keyed by user_id
     */
    public static function voorUsers(array $userIds): Collection
    {
        $ids = array_values(array_unique(array_map(static fn ($id) => (int) $id, $userIds)));
        if ($ids === [] || ! Schema::hasTable('mail_log')) {
            return collect();
        }

        /** @var array<int, array{verify_dutch: int, auto_herinnering: int, mislukt: int, laatste_ok: ?string, laatste_poging: ?string}> $acc */
        $acc = [];
        foreach ($ids as $id) {
            $acc[$id] = [
                'verify_dutch' => 0,
                'auto_herinnering' => 0,
                'mislukt' => 0,
                'laatste_ok' => null,
                'laatste_poging' => null,
            ];
        }

        $rows = MailLog::query()
            ->selectRaw('user_id, type, status, COUNT(*) as n, MAX(created_at) as laatste')
            ->whereIn('user_id', $ids)
            ->whereIn('type', self::TYPES)
            ->groupBy('user_id', 'type', 'status')
            ->get();

        foreach ($rows as $row) {
            $uid = (int) $row->user_id;
            if (! isset($acc[$uid])) {
                continue;
            }
            $n = (int) $row->n;
            $laatste = $row->laatste !== null ? (string) $row->laatste : null;

            if ($row->status === 'ok') {
                if ($row->type === 'VerifyEmailDutch') {
                    $acc[$uid]['verify_dutch'] += $n;
                } elseif ($row->type === 'VerifyEmailReminderDutch') {
                    $acc[$uid]['auto_herinnering'] += $n;
                }
                $acc[$uid]['laatste_ok'] = self::maxTimestamp($acc[$uid]['laatste_ok'], $laatste);
            } else {
                $acc[$uid]['mislukt'] += $n;
            }
            $acc[$uid]['laatste_poging'] = self::maxTimestamp($acc[$uid]['laatste_poging'], $laatste);
        }

        return collect($acc)->map(static fn (array $s): self => new self(
            verifyDutch: $s['verify_dutch'],
            autoHerinnering: $s['auto_herinnering'],
            mislukt: $s['mislukt'],
            laatsteOk: $s['laatste_ok'] !== null ? Carbon::parse($s['laatste_ok']) : null,
            laatstePoging: $s['laatste_poging'] !== null ? Carbon::parse($s['laatste_poging']) : null,
        ));
    }

    public static function voorUser(int $userId): self
    {
        return self::voorUsers([$userId])->get($userId, new self);
    }

    private static function maxTimestamp(?string $a, ?string $b): ?string
    {
        if ($a === null) {
            return $b;
        }
        if ($b === null) {
            return $a;
        }

        return strcmp($a, $b) >= 0 ? $a : $b;
    }
}
