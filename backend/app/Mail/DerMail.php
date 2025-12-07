<?php

namespace App\Mail;

use App\Models\Client;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * DER Mail
 *
 * Email envoyé au prospect avec le Document d'Entrée en Relation en pièce jointe
 */
class DerMail extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * Create a new message instance.
     */
    public function __construct(
        public Client $client,
        public User $chargeClientele,
        public string $derFilePath
    ) {}

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Votre Document d\'Entrée en Relation - ' . $this->client->prenom . ' ' . strtoupper($this->client->nom),
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        return new Content(
            view: 'emails.der',
            with: [
                'client' => $this->client,
                'chargeClientele' => $this->chargeClientele,
            ],
        );
    }

    /**
     * Get the attachments for the message.
     */
    public function attachments(): array
    {
        return [
            Attachment::fromPath($this->derFilePath)
                ->as('DER_' . $this->client->nom . '_' . $this->client->prenom . '.docx')
                ->withMime('application/vnd.openxmlformats-officedocument.wordprocessingml.document'),
        ];
    }
}
