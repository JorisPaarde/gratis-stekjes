<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\Vereniging;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class VerenigingAangemeld extends Notification
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
        $v = $this->vereniging;

        return (new MailMessage)
            ->subject('Nieuwe vereniging door een lid: '.$v->naam)
            ->greeting('Beheer,')
            ->line('Een lid heeft een vereniging toegevoegd die nog niet in de lijst stond (status: wacht op controle).')
            ->line('Naam: '.$v->naam)
            ->line('Plaats: '.$v->plaats)
            ->line('Soort: '.$v->typeLabel())
            ->action('Open beheer', route('beheer.index').'#verenigingen')
            ->salutation((string) config('site.name'));
    }
}
