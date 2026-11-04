<?php

declare(strict_types=1);

namespace App\Mail;

use App\Support\LedenupdateMailContent;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Eenmalige ledenupdate. Gebruikt de site-SMTP (MAIL_*), nooit Resend.
 */
class LedenupdateMail extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * @param  array{naam: string, nr: int, tier: int, vraag_postcode: bool}  $data
     */
    public function __construct(
        public readonly array $data,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            from: new Address(
                (string) config('mail.from.address', 'info@gratisplanten.nl'),
                (string) config('mail.from.name', 'Gratis planten'),
            ),
            subject: LedenupdateMailContent::SUBJECT,
        );
    }

    public function content(): Content
    {
        return new Content(
            html: 'mail.ledenupdate-html',
            text: 'mail.ledenupdate',
            with: [
                'body' => LedenupdateMailContent::plainBody($this->data),
                'html' => LedenupdateMailContent::htmlBody($this->data),
            ],
        );
    }
}
