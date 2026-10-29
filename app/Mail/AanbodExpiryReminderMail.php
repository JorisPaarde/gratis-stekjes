<?php

declare(strict_types=1);

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class AanbodExpiryReminderMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly string $renewUrl,
        public readonly string $tekstPreview = '',
    ) {}

    public function envelope(): Envelope
    {
        $site = (string) config('site.name');

        return new Envelope(
            subject: 'Over een week verdwijnt je aanbod op '.$site,
        );
    }

    public function content(): Content
    {
        return new Content(
            text: 'mail.aanbod-expiry-reminder',
        );
    }
}
