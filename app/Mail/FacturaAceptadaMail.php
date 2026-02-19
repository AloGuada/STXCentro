<?php

namespace App\Mail;

use App\Models\Costos\Factura;
use App\Models\Proveedor;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class FacturaAceptadaMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public Factura $factura,
        public Proveedor $proveedor,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Factura Aceptada - '.$this->factura->folio,
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.factura-aceptada',
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
