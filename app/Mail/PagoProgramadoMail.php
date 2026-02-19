<?php

namespace App\Mail;

use App\Models\Costos\Pago;
use App\Models\Proveedor;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class PagoProgramadoMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public Pago $pago,
        public Proveedor $proveedor,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Pago Programado - '.$this->pago->folio,
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.pago-programado',
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
