<?php

declare(strict_types=1);

namespace App\Notifications;

use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\URL;

class VerifyPendingEmailDutch extends Notification
{
    public function __construct(
        public readonly string $pendingEmail,
        public readonly int $userId,
    ) {}

    /** @return list<string> */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $url = $this->verificationUrl();
        $site = (string) config('site.name');

        return (new MailMessage)
            ->subject('Bevestig je nieuwe e-mailadres bij '.$site)
            ->greeting('Hoi,')
            ->line('Bevestig je nieuwe e-mailadres bij '.$site.' via deze knop:')
            ->action('Nieuw e-mailadres bevestigen', $url)
            ->line('Heb je dit niet aangevraagd? Negeer deze mail — je huidige adres blijft actief.')
            ->salutation('Groet,'.PHP_EOL.'Joris ('.$site.')');
    }

    protected function verificationUrl(): string
    {
        return URL::temporarySignedRoute(
            'account.email.bevestigen',
            Carbon::now()->addMinutes((int) Config::get('auth.verification.expire', 1440)),
            [
                'id' => $this->userId,
                'hash' => sha1($this->pendingEmail),
            ]
        );
    }
}
