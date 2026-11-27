<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\URL;

/**
 * Waarschuwing: onbevestigd account wordt op een genoemde datum verwijderd.
 * Bevestigingslink blijft geldig tot die datum (niet de standaard 24 uur).
 */
class UnverifiedDeletionNoticeDutch extends VerifyEmail
{
    public function __construct(public readonly CarbonInterface $verwijderOp) {}

    public function toMail($notifiable): MailMessage
    {
        $url = $this->verificationUrl($notifiable);
        $site = (string) config('site.name');
        $datum = $this->verwijderDatum();

        return (new MailMessage)
            ->subject('Je account bij '.$site.' wordt op '.$datum.' verwijderd')
            ->greeting('Hoi,')
            ->line('Je hebt je aangemeld bij '.$site.', maar je e-mailadres is nog niet bevestigd.')
            ->line('Wil je je aanmelding afmaken? Klik dan op de knop hieronder. Als ik vóór '.$datum.' niets van je hoor, verwijder ik je account.')
            ->action('Bevestig mijn e-mailadres', $url)
            ->line('Deze link is geldig tot '.$datum.' en werkt één keer.')
            ->line('Wil je geen account, of heb je je niet aangemeld? Dan hoef je niets te doen. Op '.$datum.' verwijder ik het account vanzelf.')
            ->salutation('Groet,'.PHP_EOL.$site);
    }

    public function verwijderDatum(): string
    {
        return $this->verwijderOp
            ->timezone('Europe/Amsterdam')
            ->locale('nl')
            ->translatedFormat('j F Y');
    }

    protected function verificationUrl($notifiable): string
    {
        $token = '';
        if ($notifiable instanceof User) {
            $token = (string) ($notifiable->email_verification_token ?? '');
            if ($token === '') {
                $token = $notifiable->rotateEmailVerificationToken();
            }
        }

        $minutes = (int) ceil(($this->verwijderOp->getTimestamp() - time()) / 60);
        if ($minutes < 60) {
            $minutes = max(60, (int) Config::get('stekjes.unverified_deletion.grace_days', 7) * 24 * 60);
        }

        return URL::temporarySignedRoute(
            'account.bevestigen',
            now()->addMinutes($minutes),
            [
                'id' => $notifiable->getKey(),
                'hash' => sha1($notifiable->getEmailForVerification()),
                'token' => $token,
            ]
        );
    }
}
