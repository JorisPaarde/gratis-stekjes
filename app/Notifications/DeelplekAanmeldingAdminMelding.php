<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\Deelplek;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class DeelplekAanmeldingAdminMelding extends Notification
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
        $plek = $this->deelplek;
        $labels = config('stekjes.deelplek_types', []);
        $typeLabel = is_array($labels) ? ($labels[$plek->type] ?? $plek->type) : $plek->type;

        return (new MailMessage)
            ->subject('Nieuwe deelplek-aanmelding: '.$plek->naam)
            ->greeting('Beheer,')
            ->line('Er is een nieuwe aanmelding binnengekomen (status: in afwachting).')
            ->line('Naam: '.$plek->naam)
            ->line('Type: '.$typeLabel)
            ->line('Plaats: '.$plek->plaats)
            ->line('Contact: '.$plek->contact_email)
            ->action('Open beheer', route('beheer.index'))
            ->salutation($site);
    }
}
