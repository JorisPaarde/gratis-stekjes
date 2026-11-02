<?php

declare(strict_types=1);

namespace App\Notifications;

use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/** Partneraanvraag via /help-mee. Niet opgeslagen; alleen naar de beheerder gemaild. */
class PartnerAanvraag extends Notification
{
    public function __construct(
        public readonly string $naam,
        public readonly string $organisatie,
        public readonly string $soort,
        public readonly string $email,
        public readonly string $bericht,
    ) {}

    /** @return list<string> */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Partneraanvraag: '.$this->organisatie)
            ->replyTo($this->email, $this->naam)
            ->greeting('Nieuwe partneraanvraag')
            ->line('Naam: '.$this->naam)
            ->line('Organisatie: '.$this->organisatie.' ('.$this->soort.')')
            ->line('E-mail: '.$this->email)
            ->line('Bericht:')
            ->line($this->bericht)
            ->line('Beantwoorden kan direct op deze mail.')
            ->salutation((string) config('site.name'));
    }
}
