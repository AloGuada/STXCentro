<?php

namespace App\Mail;

use App\Models\Costos\ComplementoPago;
use App\Models\Proveedor;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class ComplementoPendienteMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public ComplementoPago $complemento,
        public Proveedor $proveedor,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Complemento de pago pendiente - '.$this->complemento->folio,
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.complemento-pendiente',
        );
    }

    /**
     * @return array<int, \Illuminate\Mail\Mailables\Attachment>
     */
    public function attachments(): array
    {
        return [];
    }
}
