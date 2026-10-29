<?php

declare(strict_types=1);

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/** Alleen melding — geen berichtinhoud, geen andermans adres. */
class NewMessageMail extends Mailable
{
    use Queueable, SerializesModels;

    public function envelope(): Envelope
    {
        $site = (string) config('site.name');

        return new Envelope(
            subject: 'Je hebt een nieuw bericht op '.$site,
        );
    }

    public function content(): Content
    {
        return new Content(
            text: 'mail.new-message',
        );
    }
}
