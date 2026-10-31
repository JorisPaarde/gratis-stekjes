<?php

declare(strict_types=1);

namespace App\Notifications;

use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Notifications\Messages\MailMessage;

class ResetPasswordDutch extends ResetPassword
{
    public function toMail($notifiable): MailMessage
    {
        $url = $this->resetUrl($notifiable);
        $site = (string) config('site.name');
        $minutes = (int) config('auth.passwords.'.config('auth.defaults.passwords').'.expire', 60);

        return (new MailMessage)
            ->subject('Wachtwoord resetten bij '.$site)
            ->greeting('Hoi,')
            ->line('Je vroeg een nieuw wachtwoord aan bij '.$site.'. Gebruik de knop hieronder:')
            ->action('Nieuw wachtwoord instellen', $url)
            ->line('Deze link is '.$minutes.' minuten geldig.')
            ->line('Heb je geen reset aangevraagd? Negeer deze mail.')
            ->salutation('Groet,'.PHP_EOL.$site);
    }

    protected function resetUrl($notifiable): string
    {
        return url(route('account.wachtwoord.reset', [
            'token' => $this->token,
            'email' => $notifiable->getEmailForPasswordReset(),
        ], false));
    }
}
