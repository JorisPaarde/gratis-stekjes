<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\Vereniging;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class VerenigingGoedgekeurd extends Notification
{
    public function __construct(
        public readonly Vereniging $vereniging,
    ) {}

    /** @return list<string> */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $naam = (string) $this->vereniging->naam;
        $site = (string) config('site.name');

        return (new MailMessage)
            ->subject('Je vereniging staat op '.$site.': '.$naam)
            ->greeting('Hoi,')
            ->line('Ik heb “'.$naam.'” goedgekeurd — je vereniging staat nu op '.$site.'.')
            ->line('Op de pagina kunnen buurtgenoten je vereniging terugvinden.')
            ->action('Bekijk je pagina', $this->vereniging->url())
            ->salutation('Groet,'.PHP_EOL.'Joris');
    }
}
