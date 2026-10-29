<?php

declare(strict_types=1);

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\URL;

class VerifyPendingEmailDutch extends Notification
{
    use Queueable;

    public function __construct(public readonly string $pendingEmail) {}

    /** @return list<string> */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $url = $this->verificationUrl($notifiable);
        $site = (string) config('site.name');

        return (new MailMessage)
            ->subject('Bevestig je nieuwe e-mailadres bij '.$site)
            ->greeting('Hoi,')
            ->line('Bevestig je nieuwe e-mailadres bij '.$site.' via deze knop:')
            ->action('Nieuw e-mailadres bevestigen', $url)
            ->line('Of kopieer deze link in je browser:')
            ->line($url)
            ->line('Heb je dit niet aangevraagd? Negeer deze mail — je huidige adres blijft actief.')
            ->salutation('Groet,'.PHP_EOL.'Joris ('.$site.')');
    }

    protected function verificationUrl(object $notifiable): string
    {
        return URL::temporarySignedRoute(
            'account.email.bevestigen',
            Carbon::now()->addMinutes((int) Config::get('auth.verification.expire', 60)),
            [
                'id' => $notifiable->getKey(),
                'hash' => sha1($this->pendingEmail),
            ]
        );
    }
}
