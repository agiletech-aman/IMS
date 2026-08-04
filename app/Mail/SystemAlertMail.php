<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class SystemAlertMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public string $alertTitle,
        public string $alertMessage,
        public string $severity,
        public string $module,
    ) {
    }

    public function envelope(): Envelope
    {
        return new Envelope(subject: '[IIM] '.$this->alertTitle);
    }

    public function content(): Content
    {
        return new Content(view: 'emails.system-alert');
    }
}
