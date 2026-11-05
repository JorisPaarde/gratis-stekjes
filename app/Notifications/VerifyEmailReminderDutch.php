<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\User;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\URL;

/**
 * Exact goedgekeurde 24-uursherinnering (zie plan.md).
 * Knoptekst: «Bevestig mijn e-mailadres».
 */
class VerifyEmailReminderDutch extends VerifyEmail
{
    public function toMail($notifiable): MailMessage
    {
        $url = $this->verificationUrl($notifiable);
        $site = (string) config('site.name');

        return (new MailMessage)
            ->subject('Bevestig je aanmelding bij '.$site)
            ->greeting('Hoi,')
            ->line('Je bent een dag geleden begonnen met je aanmelding bij '.$site.'. Je e-mailadres is nog niet bevestigd.')
            ->line('Klik op de knop hieronder om je aanmelding af te maken. Daarna kun je planten, stekjes en zaden aanbieden en berichten sturen.')
            ->action('Bevestig mijn e-mailadres', $url)
            ->line('Deze link is 24 uur geldig en werkt één keer.')
            ->line('Heb je je niet aangemeld? Negeer deze mail.')
            ->salutation('Groet,'.PHP_EOL.$site);
    }

    protected function verificationUrl($notifiable): string
    {
        $token = '';
        if ($notifiable instanceof User) {
            $token = (string) ($notifiable->email_verification_token ?? '');
            if ($token === '') {
                $token = $notifiable->rotateEmailVerificationToken();
            }
        }

        return URL::temporarySignedRoute(
            'account.bevestigen',
            Carbon::now()->addMinutes((int) Config::get('auth.verification.expire', 1440)),
            [
                'id' => $notifiable->getKey(),
                'hash' => sha1($notifiable->getEmailForVerification()),
                'token' => $token,
            ]
        );
    }
}
