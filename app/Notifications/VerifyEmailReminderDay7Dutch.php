<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\User;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Support\Facades\URL;

/**
 * Herinnering op dag 7 na registratie, als de e-mail nog niet is bevestigd.
 * Knoptekst: «Bevestig mijn e-mailadres». Link 7 dagen geldig.
 */
class VerifyEmailReminderDay7Dutch extends VerifyEmail
{
    public function toMail($notifiable): MailMessage
    {
        $url = $this->verificationUrl($notifiable);
        $site = (string) config('site.name');

        return (new MailMessage)
            ->subject('Bevestig je aanmelding bij '.$site)
            ->greeting('Hoi,')
            ->line('Een week geleden ben je begonnen met je aanmelding bij '.$site.'. Je e-mailadres is nog niet bevestigd.')
            ->line('Wil je meedoen? Klik op de knop hieronder. Daarna kun je planten, stekjes en zaden aanbieden en berichten sturen.')
            ->action('Bevestig mijn e-mailadres', $url)
            ->line('Deze link is 7 dagen geldig en werkt één keer.')
            ->line('Heb je je niet aangemeld? Dan hoef je niets te doen.')
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
            now()->addDays(7),
            [
                'id' => $notifiable->getKey(),
                'hash' => sha1($notifiable->getEmailForVerification()),
                'token' => $token,
            ]
        );
    }
}
