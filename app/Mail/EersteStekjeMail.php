<?php

declare(strict_types=1);

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class EersteStekjeMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly string $voornaam,
        public readonly string $ervaringUrl,
    ) {}

    public function envelope(): Envelope
    {
        $site = (string) config('site.name');

        return new Envelope(
            subject: 'Gefeliciteerd met je eerste stekje op '.$site,
        );
    }

    public function content(): Content
    {
        return new Content(
            text: 'mail.eerste-stekje',
        );
    }
}
