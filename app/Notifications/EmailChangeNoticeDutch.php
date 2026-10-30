<?php

declare(strict_types=1);

namespace App\Notifications;

use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class EmailChangeNoticeDutch extends Notification
{
    public function __construct(public readonly string $newEmail) {}

    /** @return list<string> */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $site = (string) config('site.name');

        return (new MailMessage)
            ->subject('E-mailwijziging aangevraagd bij '.$site)
            ->greeting('Hoi,')
            ->line('Er is een verzoek gedaan om het e-mailadres van je account bij '.$site.' te wijzigen naar:')
            ->line($this->newEmail)
            ->line('Als jij dit was: bevestig via de link die we naar het nieuwe adres stuurden. Pas daarna is het nieuwe adres actief.')
            ->line('Was jij dit niet? Log in en wijzig je wachtwoord, of neem contact op.')
            ->salutation('Groet,'.PHP_EOL.'Joris ('.$site.')');
    }
}
