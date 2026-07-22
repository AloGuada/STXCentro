<?php

namespace App\Enums\Costos;

/**
 * Clasificación canónica de archivos adjuntos en el módulo Costos.
 * Se persiste en App\Models\Media::$descripcion y reemplaza los strings
 * ad-hoc ('xml', 'pdf', 'archivo', 'comprobante', etc.) que había antes.
 *
 * Cada caso declara su regla de mimetype aceptable para validación en
 * Form Requests (ver Costos/DocumentoValidacion::rulesFor()).
 */
enum DocumentoTipo: string
{
    // Factura
    case XmlFactura = 'xml_factura';
    case PdfFactura = 'pdf_factura';

    // Nota de crédito (CFDI tipo Egreso)
    case XmlNotaCredito = 'xml_nota_credito';
    case PdfNotaCredito = 'pdf_nota_credito';

    // Complemento de pago (CFDI tipo Pago, facturas PPD)
    case XmlComplementoPago = 'xml_complemento_pago';
    case PdfComplementoPago = 'pdf_complemento_pago';

    // Orden de compra
    case OcArchivo = 'oc_archivo';
    case OcPdfFormato = 'oc_pdf_formato';
    case OcPdfFirmado = 'oc_pdf_firmado';

    // Recepción
    case EvidenciaRecepcion = 'evidencia_recepcion';

    // Comprobante de recepción que el proveedor adjunta a su factura
    case ComprobanteRecepcion = 'comprobante_recepcion';

    // Devolución (Fase 13)
    case EvidenciaDevolucion = 'evidencia_devolucion';

    // Pago
    case ComprobantePago = 'comprobante_pago';

    // Contrarecibo
    case Contrarecibo = 'contrarecibo';

    // Solicitud de pago
    case SolicitudArchivo = 'solicitud_archivo';
    case SolicitudFirmada = 'solicitud_firmada';

    public function label(): string
    {
        return match ($this) {
            self::XmlFactura => 'XML de factura',
            self::PdfFactura => 'PDF de factura',
            self::XmlNotaCredito => 'XML de nota de crédito',
            self::PdfNotaCredito => 'PDF de nota de crédito',
            self::XmlComplementoPago => 'XML de complemento de pago',
            self::PdfComplementoPago => 'PDF de complemento de pago',
            self::OcArchivo => 'Archivo de OC',
            self::OcPdfFormato => 'Formato de OC (PDF)',
            self::OcPdfFirmado => 'OC firmada (PDF)',
            self::EvidenciaRecepcion => 'Evidencia de recepción',
            self::ComprobanteRecepcion => 'Comprobante de recepción',
            self::EvidenciaDevolucion => 'Evidencia de devolución',
            self::ComprobantePago => 'Comprobante de pago',
            self::Contrarecibo => 'Contrarecibo',
            self::SolicitudArchivo => 'Anexo de solicitud',
            self::SolicitudFirmada => 'Solicitud firmada',
        };
    }

    /**
     * Regla de `mimes:` para Form Request según el tipo de documento.
     */
    public function mimes(): string
    {
        return match ($this) {
            self::XmlFactura,
            self::XmlNotaCredito,
            self::XmlComplementoPago => 'xml,txt',
            self::PdfFactura,
            self::PdfNotaCredito,
            self::PdfComplementoPago,
            self::OcPdfFormato,
            self::OcPdfFirmado,
            self::ComprobantePago,
            self::Contrarecibo,
            self::SolicitudFirmada => 'pdf',
            self::EvidenciaRecepcion,
            self::ComprobanteRecepcion,
            self::EvidenciaDevolucion => 'pdf,jpg,jpeg,png,webp',
            self::OcArchivo,
            self::SolicitudArchivo => 'pdf,jpg,jpeg,png,webp,doc,docx,xls,xlsx',
        };
    }

    /**
     * @return array<string, string>
     */
    public static function options(): array
    {
        return collect(self::cases())
            ->mapWithKeys(fn (self $c) => [$c->value => $c->label()])
            ->all();
    }
}
