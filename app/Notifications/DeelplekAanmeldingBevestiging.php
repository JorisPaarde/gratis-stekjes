<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\Deelplek;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class DeelplekAanmeldingBevestiging extends Notification
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
        $site = (string) config('site.name');
        $naam = (string) $this->deelplek->naam;

        return (new MailMessage)
            ->subject('Aanmelding ontvangen: '.$naam)
            ->greeting('Hoi,')
            ->line('Ik heb je aanmelding voor “'.$naam.'” ontvangen.')
            ->line('Na controle zet ik de plek op '.$site.' (deelplekken en eventueel stadspagina’s). Je hoort het als het rond is.')
            ->line('Heb je dit niet zelf aangevraagd? Negeer deze mail.')
            ->salutation('Groet,'.PHP_EOL.'Joris ('.$site.')');
    }
}
