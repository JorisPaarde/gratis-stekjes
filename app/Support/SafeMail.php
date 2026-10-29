<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Throwable;

final class SafeMail
{
    /**
     * Verstuur een notificatie zonder de request te laten crashen.
     * Retourneert true bij succes, false bij mislukte verzending.
     */
    public static function notify(object $notifiable, object $notification): bool
    {
        try {
            $notifiable->notify($notification);

            return true;
        } catch (Throwable $e) {
            Log::error(self::safeSummary($e));

            return false;
        }
    }

    /**
     * Verstuur on-demand naar een expliciet adres (niet via $user->email).
     */
    public static function toAddress(string $email, object $notification): bool
    {
        try {
            Notification::route('mail', $email)->notify($notification);

            return true;
        } catch (Throwable $e) {
            Log::error(self::safeSummary($e));

            return false;
        }
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
