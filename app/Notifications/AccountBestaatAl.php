<?php

declare(strict_types=1);

namespace App\Notifications;

use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Iemand probeerde een account te maken met een e-mailadres dat al een account heeft.
 * Op het scherm verandert niets (geen enumeratie); de eigenaar van het adres krijgt deze mail.
 */
class AccountBestaatAl extends Notification
{
    /** @return list<string> */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $site = (string) config('site.name');

        return (new MailMessage)
            ->subject('Je hebt al een account bij '.$site)
            ->greeting('Hoi,')
            ->line('Er is net geprobeerd een account te maken met dit e-mailadres. Je hebt al een account, dus je kunt gewoon inloggen.')
            ->action('Inloggen', route('account.inloggen'))
            ->line('Wachtwoord kwijt? Vraag een nieuw aan: '.route('account.wachtwoord.vergeten'))
            ->line('Was jij dit niet? Dan hoef je niets te doen. Er is niets veranderd aan je account.')
            ->salutation($site);
    }
}
