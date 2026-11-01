<?php

declare(strict_types=1);

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Mail\Mailables\Headers;
use Illuminate\Queue\SerializesModels;

/**
 * Melding van een nieuw bericht. Bevat de voornaam van de afzender, waar het
 * gesprek over gaat en een link, maar nooit de berichtinhoud of contactgegevens.
 */
class NewMessageMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public string $afzender = '',
        public ?string $onderwerp = null,
        public string $gesprekUrl = '',
        public string $uitzettenUrl = '',
    ) {}

    public function envelope(): Envelope
    {
        $site = (string) config('site.name');
        $wie = trim($this->afzender);

        return new Envelope(
            subject: $wie !== ''
                ? $wie.' stuurde je een bericht op '.$site
                : 'Je hebt een nieuw bericht op '.$site,
        );
    }

    public function headers(): Headers
    {
        $headers = [];
        if ($this->uitzettenUrl !== '') {
            $headers['List-Unsubscribe'] = '<'.$this->uitzettenUrl.'>';
        }

        return new Headers(text: $headers);
    }

    public function content(): Content
    {
        return new Content(
            text: 'mail.new-message',
            with: [
                'afzender' => trim($this->afzender),
                'onderwerp' => $this->onderwerp,
                'gesprekUrl' => $this->gesprekUrl !== '' ? $this->gesprekUrl : route('account.inbox'),
                'uitzettenUrl' => $this->uitzettenUrl,
            ],
        );
    }
}
