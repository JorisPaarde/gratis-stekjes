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
 * Zachte tip ~2 dagen na e-mailbevestiging als er nog geen aanbod is.
 * Max. 1×; afmelden via ondertekende link. Geen aantallen deelplekken.
 */
class ActivatieHerinneringMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly string $voornaam = '',
        public readonly string $aanbiedenUrl = '',
        public readonly string $uitzettenUrl = '',
    ) {}

    public function envelope(): Envelope
    {
        $site = (string) config('site.name');

        return new Envelope(
            subject: 'Zet je eerste stekje online op '.$site,
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
            text: 'mail.activatie-herinnering',
            with: [
                'voornaam' => trim($this->voornaam),
                'aanbiedenUrl' => $this->aanbiedenUrl !== '' ? $this->aanbiedenUrl : route('aanbieden'),
                'uitzettenUrl' => $this->uitzettenUrl,
                'site' => (string) config('site.name'),
            ],
        );
    }
}
