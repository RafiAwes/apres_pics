<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\{Content, Envelope};
use Illuminate\Queue\SerializesModels;

class InviteGuestMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public $link;
    public $password;
    public $eventName;

    public function __construct($link, $password = null, $eventName = null)
    {
        $this->link = $link;
        $this->password = $password;
        $this->eventName = $eventName;
    }

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        return new Envelope(
            subject: $this->eventName ? "Invitation to: {$this->eventName}" : 'You are invited to an event!',
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        return new Content(
            view: 'emails.invite_guest',
        );
    }

    /**
     * Get the attachments for the message.
     *
     * @return array<int, \Illuminate\Mail\Mailables\Attachment>
     */
    public function attachments(): array
    {
        return [];
    }
}
