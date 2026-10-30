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
                ? 'Confirm your Framework Cloud account'
                : 'Your Framework Cloud sign-in link',
        );
    }

    public function content(): Content
    {
        return new Content(view: 'mail.magic-link', text: 'mail.magic-link-text');
    }
}
