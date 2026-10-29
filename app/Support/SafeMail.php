<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Throwable;

/**
 * Transactionele account-mail: plan verzending na de HTTP-response
 * (geen database-queue / cron), zodat de gebruiker niet op SMTP wacht.
 * SMTP-fouten worden gelogd en geven geen 500.
 */
final class SafeMail
{
    /**
     * Verstuur een notificatie na de response zonder de request te laten crashen.
     * Retourneert true als verzending is ingepland (of direct lukte), false bij planningsfout.
     */
    public static function notify(object $notifiable, object $notification): bool
    {
        try {
            self::afterResponse(static function () use ($notifiable, $notification): void {
                $notifiable->notify($notification);
            });

            return true;
        } catch (Throwable $e) {
            Log::error(self::safeSummary($e));

            return false;
        }
    }

    /**
     * Verstuur on-demand naar een expliciet adres (niet via $user->email), na de response.
     */
    public static function toAddress(string $email, object $notification): bool
    {
        try {
            self::afterResponse(static function () use ($email, $notification): void {
                Notification::route('mail', $email)->notify($notification);
            });

            return true;
        } catch (Throwable $e) {
            Log::error(self::safeSummary($e));

            return false;
        }
    }

    /**
     * @param  callable(): void  $send
     */
    private static function afterResponse(callable $send): void
    {
        $runner = static function () use ($send): void {
            try {
                $send();
            } catch (Throwable $e) {
                Log::error(SafeMail::safeSummary($e));
            }
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
}
