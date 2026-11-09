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
 */
final class BevestigingsmailStats
{
    /** @var list<string> */
    public const TYPES = [
        'VerifyEmailDutch',
        'VerifyEmailReminderDutch',
    ];

    public function __construct(
        public readonly int $bevestiging = 0,
        public readonly int $herinnering = 0,
        public readonly int $mislukt = 0,
        public readonly ?CarbonInterface $laatsteOk = null,
        public readonly ?CarbonInterface $laatstePoging = null,
    ) {}

    public function totaalOk(): int
    {
        return $this->bevestiging + $this->herinnering;
    }

    public function isLeeg(): bool
    {
        return $this->totaalOk() === 0 && $this->mislukt === 0;
    }

    /** Compacte NL-tekst voor lijst en detail. */
    public function samenvatting(): string
    {
        $delen = ['Bevestigingsmails: '.$this->tellingLabel()];

        $laatste = $this->laatsteOk ?? $this->laatstePoging;
        if ($laatste !== null) {
            $delen[] = 'laatste '.$this->formatLaatste($laatste);
        }

        if ($this->mislukt > 0) {
            $delen[] = $this->mislukt === 1 ? '1 mislukt' : $this->mislukt.' mislukt';
        }

        return implode(', ', $delen);
    }

    /** Korte hint bij de herstuur-knop. */
    public function laatsteVerzendHint(): ?string
    {
        $laatste = $this->laatsteOk ?? $this->laatstePoging;
        if ($laatste === null) {
            return null;
        }

        return 'Laatst verstuurd: '.$this->formatLaatste($laatste);
    }

    /** @return '1' | '1 + 2 herinneringen' | '0' | … */
    public function tellingLabel(): string
    {
        if ($this->herinnering === 0) {
            return (string) $this->bevestiging;
        }

        $her = $this->herinnering === 1 ? '1 herinnering' : $this->herinnering.' herinneringen';

        return $this->bevestiging.' + '.$her;
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

        /** @var array<int, array{bevestiging: int, herinnering: int, mislukt: int, laatste_ok: ?string, laatste_poging: ?string}> $acc */
        $acc = [];
        foreach ($ids as $id) {
            $acc[$id] = [
                'bevestiging' => 0,
                'herinnering' => 0,
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
                    $acc[$uid]['bevestiging'] += $n;
                } elseif ($row->type === 'VerifyEmailReminderDutch') {
                    $acc[$uid]['herinnering'] += $n;
                }
                $acc[$uid]['laatste_ok'] = self::maxTimestamp($acc[$uid]['laatste_ok'], $laatste);
            } else {
                $acc[$uid]['mislukt'] += $n;
            }
            $acc[$uid]['laatste_poging'] = self::maxTimestamp($acc[$uid]['laatste_poging'], $laatste);
        }

        return collect($acc)->map(static fn (array $s): self => new self(
            bevestiging: $s['bevestiging'],
            herinnering: $s['herinnering'],
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
