<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\Deelplek;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class DeelplekGoedgekeurd extends Notification
{
    public function __construct(
        public readonly Deelplek $deelplek,
    ) {}

    /** @return list<string> */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $naam = (string) $this->deelplek->naam;
        $site = (string) config('site.name');
        $plaats = trim((string) $this->deelplek->plaats);
        $url = $plaats !== ''
            ? route('deelplekken', ['plaats' => $plaats])
            : route('deelplekken');

        return (new MailMessage)
            ->subject('Je deelplek staat op '.$site.': '.$naam)
            ->greeting('Hoi,')
            ->line('Ik heb “'.$naam.'” goedgekeurd — je deelplek staat nu op '.$site.'.')
            ->line('Mensen in de buurt kunnen je plek daar terugvinden.')
            ->action('Bekijk je deelplek', $url)
            ->salutation('Groet,'.PHP_EOL.'Joris');
    }
}
