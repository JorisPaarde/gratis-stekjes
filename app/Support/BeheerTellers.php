<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\Deelplek;
use App\Models\Ervaring;
use App\Models\Feedback;
use App\Models\MailLog;
use App\Models\Report;
use App\Models\User;
use App\Models\Vereniging;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;
use Throwable;

/** Tellingen voor «Aandacht nodig» en de tabbalk in beheer. Elke telling faalt stil naar 0. */
final class BeheerTellers
{
    public const ONBEVESTIGD_DAGEN = 2;

    /** @return array<string, int> */
    public static function alle(): array
    {
        return [
            'onbevestigd' => self::tel('users', fn () => self::onbevestigdQuery()->count()),
            'mailfout' => self::tel('mail_log', fn () => self::mailFoutUserIds()->count()),
            'verenigingen' => self::tel('verenigingen', fn () => Vereniging::query()->where('status', Vereniging::STATUS_PENDING)->count()),
            'aanmeldingen' => self::tel('deelplekken', fn () => Deelplek::query()->pending()->count()),
            'meldingen' => self::tel('reports', fn () => Report::query()->where('handled', false)->count()),
            'feedback' => self::tel('feedback', fn () => Feedback::query()->whereNull('read_at')->count()),
            'ervaringen' => self::tel('ervaringen', fn () => Ervaring::query()->where('status', Ervaring::STATUS_PENDING)->count()),
            'naam_email' => self::tel('users', fn () => User::query()->where(fn ($q) => $q->where('name', 'like', '%@%')
                ->orWhere('name', 'like', '%gmail%')->orWhere('name', 'like', '%hotmail%')->orWhere('name', 'like', '%.com%')
                ->orWhere('name', 'like', '%.nl%'))->count()),
        ];
    }

    /** Actieve leden die hun e-mail na X dagen nog niet bevestigden. */
    public static function onbevestigdQuery(): Builder
    {
        return User::query()
            ->where('active', true)
            ->whereNull('email_verified_at')
            ->where('created_at', '<=', now()->subDays(self::ONBEVESTIGD_DAGEN));
    }

    /**
     * Leden van wie de laatste mail in de afgelopen 7 dagen mislukte.
     *
     * @return Collection<int, int>
     */
    public static function mailFoutUserIds(): Collection
    {
        $laatste = MailLog::query()
            ->selectRaw('MAX(id) as id')
            ->whereNotNull('user_id')
            ->groupBy('user_id');

        return MailLog::query()
            ->whereIn('id', $laatste)
            ->where('status', 'fail')
            ->where('created_at', '>=', now()->subDays(7))
            ->pluck('user_id');
    }

    private static function tel(string $tabel, callable $fn): int
    {
        try {
            return Schema::hasTable($tabel) ? (int) $fn() : 0;
        } catch (Throwable) {
            return 0;
        }
    }
}
