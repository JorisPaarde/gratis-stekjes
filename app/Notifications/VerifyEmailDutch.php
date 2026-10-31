<?php

declare(strict_types=1);

namespace App\Notifications;

use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\URL;

class VerifyEmailDutch extends VerifyEmail
{
    public function toMail($notifiable): MailMessage
    {
        $url = $this->verificationUrl($notifiable);
        $site = (string) config('site.name');

        $hours = max(1, (int) ceil((int) Config::get('auth.verification.expire', 1440) / 60));

        return (new MailMessage)
            ->subject('Bevestig je aanmelding bij '.$site)
            ->greeting('Hoi,')
            ->line('Bevestig je aanmelding bij '.$site.' via deze knop:')
            ->action('E-mail bevestigen', $url)
            ->line('Deze link is '.$hours.' uur geldig.')
            ->line('Heb je je niet aangemeld? Negeer deze mail.')
            ->salutation('Groet,'.PHP_EOL.$site);
    }

    protected function verificationUrl($notifiable): string
    {
        return URL::temporarySignedRoute(
            'account.bevestigen',
            Carbon::now()->addMinutes((int) Config::get('auth.verification.expire', 1440)),
            [
                'id' => $notifiable->getKey(),
                'hash' => sha1($notifiable->getEmailForVerification()),
            ]
        );
    }
}
