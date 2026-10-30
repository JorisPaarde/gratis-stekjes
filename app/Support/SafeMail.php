<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\MailLog;
use App\Models\User;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Schema;
use Throwable;

/**
 * Transactionele account-mail: plan verzending na de HTTP-response
 * (geen database-queue / cron), zodat de gebruiker niet op SMTP wacht.
 * SMTP-fouten worden gelogd (laravel.log + mail_log) en geven geen 500.
 */
final class SafeMail
{
    /**
     * Verstuur een notificatie na de response zonder de request te laten crashen.
     * Retourneert true als verzending is ingepland (of direct lukte), false bij planningsfout.
     */
    public static function notify(object $notifiable, object $notification): bool
    {
        $userId = self::resolveUserId($notifiable);
        $type = self::typeName($notification);

        try {
            self::afterResponse(static function () use ($notifiable, $notification, $userId, $type): void {
                try {
                    $notifiable->notify($notification);
                    self::record($userId, $type, 'ok', null);
                } catch (Throwable $e) {
                    Log::error(SafeMail::safeSummary($e));
                    self::record($userId, $type, 'fail', SafeMail::safeSummary($e));
                }
            });

            return true;
        } catch (Throwable $e) {
            Log::error(self::safeSummary($e));
            self::record($userId, $type, 'fail', self::safeSummary($e));

            return false;
        }
    }

    /**
     * Verstuur on-demand naar een expliciet adres (niet via $user->email), na de response.
     */
    public static function toAddress(string $email, object $notification, ?int $userId = null): bool
    {
        $type = self::typeName($notification);

        try {
            self::afterResponse(static function () use ($email, $notification, $userId, $type): void {
                try {
                    Notification::route('mail', $email)->notify($notification);
                    self::record($userId, $type, 'ok', null);
                } catch (Throwable $e) {
                    Log::error(SafeMail::safeSummary($e));
                    self::record($userId, $type, 'fail', SafeMail::safeSummary($e));
                }
            });

            return true;
        } catch (Throwable $e) {
            Log::error(self::safeSummary($e));
            self::record($userId, $type, 'fail', self::safeSummary($e));

            return false;
        }
    }

    /**
     * @param  callable(): void  $send
     */
    private static function afterResponse(callable $send): void
    {
        $runner = static function () use ($send): void {
            $send();
        };

        // Console/Pest: direct versturen (geen web-response; terminate() is onbetrouwbaar
        // bij meerdere HTTP-calls in één test). Productie-web: na de response.
        if (app()->runningInConsole()) {
            $runner();

            return;
        }

        // Geen ShouldQueue / database-jobs: terminating draait in-process na de response.
        $ran = false;
        app()->terminating(static function () use ($runner, &$ran): void {
            if ($ran) {
                return;
            }
            $ran = true;
            $runner();
        });
    }

    public static function safeSummary(Throwable $e): string
    {
        $class = $e::class;
        $file = basename(str_replace('\\', '/', $e->getFile()));
        $line = $e->getLine();
        $message = $e->getMessage();
        // Geen adressen/hosts/wachtwoorden in logs die later publiek getoond worden.
        $message = (string) preg_replace('/[A-Za-z0-9._%+-]+@[A-Za-z0-9.-]+\.[A-Za-z]{2,}/', '[email]', $message);
        $message = (string) preg_replace('#https?://\S+#', '[url]', $message);
        $message = (string) preg_replace('/\b(?:\d{1,3}\.){3}\d{1,3}\b/', '[ip]', $message);
        $message = (string) preg_replace('/(?i)(password|passwd|secret|token)\s*[:=]\s*\S+/', '$1=[redacted]', $message);
        $message = mb_substr($message, 0, 220);

        return "{$class}: {$message} @ {$file}:{$line}";
    }

    public static function typeName(object $notification): string
    {
        return class_basename($notification);
    }

    private static function resolveUserId(object $notifiable): ?int
    {
        if ($notifiable instanceof User) {
            return (int) $notifiable->getKey();
        }

        if (isset($notifiable->id) && is_numeric($notifiable->id)) {
            return (int) $notifiable->id;
        }

        return null;
    }

    private static function record(?int $userId, string $type, string $status, ?string $error): void
    {
        try {
            if (! Schema::hasTable('mail_log')) {
                return;
            }

            $errorShort = null;
            if ($error !== null && $error !== '') {
                $errorShort = mb_substr($error, 0, 255);
            }

            MailLog::query()->create([
                'user_id' => $userId,
                'type' => mb_substr($type, 0, 80),
                'status' => $status === 'ok' ? 'ok' : 'fail',
                'error' => $errorShort,
                'created_at' => now(),
            ]);
        } catch (Throwable $e) {
            try {
                Log::warning('mail_log schrijven mislukt: '.self::safeSummary($e));
            } catch (Throwable) {
                // Logging mag de request/mail nooit breken.
            }
        }
    }
}
