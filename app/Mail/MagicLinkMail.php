<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class MagicLinkMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public string $url,
        public bool $isNewAccount = false,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: $this->isNewAccount
                ? 'Confirm your Framework account'
                : 'Your Framework sign-in link',
        );
    }

    public function content(): Content
    {
        return new Content(markdown: 'mail.magic-link');
    }
}
