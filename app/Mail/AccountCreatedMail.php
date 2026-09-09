<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class AccountCreatedMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly string $recipientName,
        public readonly string $accountEmail,
        public readonly string $temporaryPassword,
        public readonly string $roleLabel,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "Welcome to Smart NSTP — Your {$this->roleLabel} account",
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.account-created',
            text: 'emails.account-created-text',
            with: [
                'loginUrl' => route('login'),
            ],
        );
    }
}
