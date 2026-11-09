<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\MailLog;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;

/**
 * Samenvatting van activatie-tipmails per lid uit mail_log (één groupBy-query).
 * Telt alleen ActivatieHerinneringMail.
 */
final class ActivatieHerinneringStats
{
    /** @var list<string> */
    public const TYPES = [
        'ActivatieHerinneringMail',
    ];

    public function __construct(
        public readonly int $ok = 0,
        public readonly int $mislukt = 0,
        public readonly ?CarbonInterface $laatsteOk = null,
        public readonly ?CarbonInterface $laatstePoging = null,
    ) {}

    public function isLeeg(): bool
    {
        return $this->ok === 0 && $this->mislukt === 0;
    }

    public function laatste(): ?CarbonInterface
    {
        return $this->laatsteOk ?? $this->laatstePoging;
    }

    /** «Activatieherinnering: 1×» / «nog geen» / «1 mislukt». */
    public function tellingLabel(): string
    {
        if ($this->ok === 0) {
            if ($this->mislukt > 0) {
                return $this->mislukt === 1
                    ? 'Activatieherinnering: 1 mislukt'
                    : 'Activatieherinnering: '.$this->mislukt.' mislukt';
            }

            return 'Activatieherinnering: nog geen';
        }

        $basis = $this->ok === 1
            ? 'Activatieherinnering: 1×'
            : 'Activatieherinnering: '.$this->ok.'×';

        if ($this->mislukt > 0) {
            $basis .= $this->mislukt === 1 ? ' · 1 mislukt' : ' · '.$this->mislukt.' mislukt';
        }

        return $basis;
    }

    public function herinnerdLabel(): string
    {
        $n = $this->ok;
        $basis = $n.'×';
        $laatste = $this->laatste();
        if ($laatste === null) {
            return $n === 0 ? 'nog niet' : $basis;
        }

        return $basis.' · laatste '.$this->formatLaatste($laatste);
    }

    public function formatLaatste(CarbonInterface $at): string
    {
        $lokaal = $at->copy()->timezone('Europe/Amsterdam');

        return $lokaal->format('d-m-Y H:i').' ('.$at->diffForHumans().')';
    }

    /**
     * @param  list<int|string>  $userIds
     * @return Collection<int, self> keyed by user_id
     */
    public static function voorUsers(array $userIds): Collection
    {
        $ids = array_values(array_unique(array_map(static fn ($id) => (int) $id, $userIds)));
        if ($ids === [] || ! Schema::hasTable('mail_log')) {
            return collect();
        }

        /** @var array<int, array{ok: int, mislukt: int, laatste_ok: ?string, laatste_poging: ?string}> $acc */
        $acc = [];
        foreach ($ids as $id) {
            $acc[$id] = [
                'ok' => 0,
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
                $acc[$uid]['ok'] += $n;
                $acc[$uid]['laatste_ok'] = self::maxTimestamp($acc[$uid]['laatste_ok'], $laatste);
            } else {
                $acc[$uid]['mislukt'] += $n;
            }
            $acc[$uid]['laatste_poging'] = self::maxTimestamp($acc[$uid]['laatste_poging'], $laatste);
        }

        return collect($acc)->map(static fn (array $s): self => new self(
            ok: $s['ok'],
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
