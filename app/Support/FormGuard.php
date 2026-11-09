<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

final class FormGuard
{
    /** Autofill-neutrale honeypot-veldnaam (geen website/url/name/email/…). */
    public const HONEYPOT_FIELD = 'gp_hp_stem';

    /** Legacy honeypot-naam (gecachte HTML / oude bots). */
    public const HONEYPOT_LEGACY_FIELD = 'website';

    /** @var list<string> */
    public const HONEYPOT_FIELDS = [self::HONEYPOT_FIELD, self::HONEYPOT_LEGACY_FIELD];

    /** @var array<string, true>|null */
    private static ?array $disposableDomains = null;

    /**
     * True als een van de honeypot-waarden (nieuw of legacy) gevuld is.
     */
    public static function honeypotFilled(?string $value): bool
    {
        return trim((string) $value) !== '';
    }

    /**
     * True als het request (of POST-array) een gevuld honeypot-veld heeft
     * (nieuw veld of legacy `website`).
     *
     * @param  Request|array<string, mixed>  $source
     */
    public static function honeypotFilledIn(Request|array $source): bool
    {
        foreach (self::HONEYPOT_FIELDS as $key) {
            $raw = $source instanceof Request
                ? $source->input($key)
                : ($source[$key] ?? null);
            if (self::honeypotFilled(is_scalar($raw) || $raw === null ? (string) ($raw ?? '') : '')) {
                return true;
            }
        }

        return false;
    }

    /** @return array<string, mixed> Validatieregels voor beide honeypot-veldnamen. */
    public static function honeypotRules(): array
    {
        $rules = [];
        foreach (self::HONEYPOT_FIELDS as $key) {
            $rules[$key] = ['nullable', 'string', 'max:200'];
        }

        return $rules;
    }

    public static function submittedTooFast(mixed $formStarted): bool
    {
        $min = (int) config('stekjes.form_min_seconds', 3);
        $started = (int) $formStarted;
        if ($started <= 0) {
            return true;
        }

        return (time() - $started) < $min;
    }

    /**
     * Log bot-afwijzing zonder PII (geen e-mail, geen IP in de context)
     * en tel op in form_bot_daily (dag × formulier × reden).
     *
     * @param  'honeypot'|'too_fast'  $reason
     */
    public static function logBotRejected(string $form, string $reason): void
    {
        if (! in_array($reason, ['honeypot', 'too_fast'], true)) {
            $reason = 'honeypot';
        }

        $form = self::normalizeFormKey($form);

        Log::info('form.bot_rejected', [
            'form' => $form,
            'reason' => $reason,
        ]);

        self::incrementBotDaily($form, $reason);
    }

    /** Alleen veilige sleutel voor aggregaat (geen PII). */
    public static function normalizeFormKey(string $form): string
    {
        $form = strtolower(trim($form));
        $form = preg_replace('/[^a-z0-9_-]/', '', $form) ?? '';
        if ($form === '') {
            return 'unknown';
        }

        return substr($form, 0, 40);
    }

    private static function incrementBotDaily(string $form, string $reason): void
    {
        try {
            if (! Schema::hasTable('form_bot_daily')) {
                return;
            }

            $date = now()->timezone('Europe/Amsterdam')->toDateString();

            $updated = DB::table('form_bot_daily')
                ->where('date', $date)
                ->where('form', $form)
                ->where('reason', $reason)
                ->increment('count');

            if ($updated > 0) {
                return;
            }

            try {
                DB::table('form_bot_daily')->insert([
                    'date' => $date,
                    'form' => $form,
                    'reason' => $reason,
                    'count' => 1,
                ]);
            } catch (UniqueConstraintViolationException) {
                DB::table('form_bot_daily')
                    ->where('date', $date)
                    ->where('form', $form)
                    ->where('reason', $reason)
                    ->increment('count');
            }
        } catch (\Throwable) {
            // Teller mag bot-afwijzing nooit blokkeren.
        }
    }

    public static function isDisposableEmail(string $email): bool
    {
        $email = strtolower(trim($email));
        $at = strrpos($email, '@');
        if ($at === false) {
            return false;
        }
        $domain = substr($email, $at + 1);
        if ($domain === '') {
            return false;
        }

        $domains = self::disposableDomains();
        if (isset($domains[$domain])) {
            return true;
        }

        // Parent-domeinen (bijv. a.b.mailinator.com)
        $parts = explode('.', $domain);
        while (count($parts) > 2) {
            array_shift($parts);
            $parent = implode('.', $parts);
            if (isset($domains[$parent])) {
                return true;
            }
        }

        return false;
    }

    /** @return array<string, true> */
    private static function disposableDomains(): array
    {
        if (self::$disposableDomains !== null) {
            return self::$disposableDomains;
        }

        $path = (string) config('stekjes.disposable_domains_path', resource_path('data/disposable/domains.txt'));
        $set = [];
        if (is_readable($path)) {
            $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [];
            foreach ($lines as $line) {
                $line = strtolower(trim($line));
                if ($line === '' || str_starts_with($line, '#')) {
                    continue;
                }
                $set[$line] = true;
            }
        }

        return self::$disposableDomains = $set;
    }
}
