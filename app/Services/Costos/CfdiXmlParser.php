<?php

namespace App\Services\Costos;

use RuntimeException;
use SimpleXMLElement;

/**
 * Parser mínimo de CFDI (Comprobante Fiscal Digital por Internet) 3.3 / 4.0.
 * Toma el XML como fuente de verdad fiscal y extrae:
 *
 * - uuid_fiscal (del complemento TimbreFiscalDigital)
 * - folio_fiscal (atributo Folio del Comprobante)
 * - fecha_factura (atributo Fecha)
 * - subtotal, total
 * - moneda (en minúsculas, como las guarda el sistema) y tipo_cambio (null en MXN)
 * - iva_trasladado (suma de impuestos trasladados IVA)
 * - iva_retenido, isr_retenido
 * - impuestos_detalle (snapshot JSON con traslados y retenciones)
 * - rfc_emisor, rfc_receptor (para validaciones posteriores)
 *
 * No valida contra SAT (eso es Fase 15). Solo parseo estructural.
 */
class CfdiXmlParser
{
    /**
     * Parsea un XML CFDI y retorna un array con los campos autoritativos.
     *
     * @return array{
     *   uuid_fiscal: ?string,
     *   folio_fiscal: ?string,
     *   fecha_factura: ?string,
     *   subtotal: float,
     *   total: float,
     *   moneda: string,
     *   tipo_cambio: ?float,
     *   iva_trasladado: float,
     *   iva_retenido: float,
     *   isr_retenido: float,
     *   rfc_emisor: ?string,
     *   rfc_receptor: ?string,
     *   metodo_pago: ?string,
     *   forma_pago: ?string,
     *   tipo_comprobante: ?string,
     *   impuestos_detalle: array<string, mixed>,
     * }
     */
    public function parse(string $xmlString): array
    {
        $xml = $this->loadXml($xmlString);
        $ns = $xml->getNamespaces(true);
        $cfdi = $ns['cfdi'] ?? 'http://www.sat.gob.mx/cfd/4';

        $subtotal = (float) $this->attr($xml, 'SubTotal');
        $total = (float) $this->attr($xml, 'Total');
        $fechaRaw = $this->attr($xml, 'Fecha');
        $fecha = $fechaRaw !== null ? substr($fechaRaw, 0, 10) : null;
        $folioFiscal = $this->attr($xml, 'Folio');
        $moneda = strtolower($this->attr($xml, 'Moneda') ?? 'MXN');
        $tipoCambio = $this->attr($xml, 'TipoCambio');

        [$rfcEmisor, $rfcReceptor] = $this->extractRfcs($xml, $cfdi);
        [$ivaTrasladado, $ivaRetenido, $isrRetenido, $impuestosDetalle] = $this->extractImpuestos($xml, $cfdi);
        $uuid = $this->extractUuid($xml, $ns);

        return [
            'uuid_fiscal' => $uuid,
            'folio_fiscal' => $folioFiscal,
            'fecha_factura' => $fecha,
            'subtotal' => $subtotal,
            'total' => $total,
            'moneda' => $moneda,
            // En MXN el SAT lo omite o lo fija en 1: no dice nada.
            'tipo_cambio' => $moneda !== 'mxn' && (float) $tipoCambio > 0 ? (float) $tipoCambio : null,
            'iva_trasladado' => $ivaTrasladado,
            'iva_retenido' => $ivaRetenido,
            'isr_retenido' => $isrRetenido,
            'rfc_emisor' => $rfcEmisor,
            'rfc_receptor' => $rfcReceptor,
            'metodo_pago' => $this->attr($xml, 'MetodoPago'),
            'forma_pago' => $this->attr($xml, 'FormaPago'),
            'tipo_comprobante' => $this->attr($xml, 'TipoDeComprobante'),
            'impuestos_detalle' => $impuestosDetalle,
        ];
    }

    /**
     * Parsea un CFDI de tipo "P" (Pago) y extrae el complemento de pagos:
     * la fecha y monto del pago, y los documentos relacionados (facturas
     * liquidadas) por su UUID e importe pagado.
     *
     * @return array{
     *   tipo_comprobante: ?string,
     *   uuid_fiscal: ?string,
     *   fecha_pago: ?string,
     *   monto_total: float,
     *   docs_relacionados: array<int, array{uuid: string, imp_pagado: float}>,
     * }
     */
    public function parseComplementoPago(string $xmlString): array
    {
        $xml = $this->loadXml($xmlString);
        $ns = $xml->getNamespaces(true);
        $cfdi = $ns['cfdi'] ?? 'http://www.sat.gob.mx/cfd/4';
        $pagoNs = $ns['pago20'] ?? $ns['pago10'] ?? 'http://www.sat.gob.mx/Pagos20';

        $docs = [];
        $fechaPago = null;
        $montoTotal = 0.0;

        $complemento = $xml->children($cfdi)->Complemento ?? null;
        if ($complemento !== null) {
            $pagos = $complemento->children($pagoNs)->Pagos ?? null;
            if ($pagos !== null) {
                foreach ($pagos->children($pagoNs)->Pago as $pago) {
                    $fechaRaw = $this->attr($pago, 'FechaPago');
                    if ($fechaPago === null && $fechaRaw !== null) {
                        $fechaPago = substr($fechaRaw, 0, 10);
                    }
                    $montoTotal += (float) ($this->attr($pago, 'Monto') ?? 0);

                    foreach ($pago->children($pagoNs)->DoctoRelacionado as $doc) {
                        $idDoc = $this->attr($doc, 'IdDocumento');
                        if ($idDoc === null) {
                            continue;
                        }
                        $docs[] = [
                            'uuid' => strtoupper($idDoc),
                            'imp_pagado' => (float) ($this->attr($doc, 'ImpPagado') ?? 0),
                        ];
                    }
                }
            }
        }

        return [
            'tipo_comprobante' => $this->attr($xml, 'TipoDeComprobante'),
            'uuid_fiscal' => $this->extractUuid($xml, $ns),
            'fecha_pago' => $fechaPago,
            'monto_total' => round($montoTotal, 2),
            'docs_relacionados' => $docs,
        ];
    }

    private function loadXml(string $xmlString): SimpleXMLElement
    {
        $prev = libxml_use_internal_errors(true);
        try {
            $xml = simplexml_load_string($xmlString);
            if ($xml === false) {
                $errores = libxml_get_errors();
                $mensaje = $errores[0]->message ?? 'XML inválido';
                throw new RuntimeException('CFDI inválido: '.trim($mensaje));
            }

            return $xml;
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($prev);
        }
    }

    /**
     * Lee un atributo (posiblemente de un elemento namespaced). `$el['Attr']`
     * no funciona confiablemente tras `children($ns)` — hay que pasar por
     * `attributes()`.
     */
    private function attr(SimpleXMLElement $el, string $name): ?string
    {
        $attrs = $el->attributes();
        if ($attrs === null) {
            return null;
        }
        $value = $attrs->{$name} ?? null;

        return $value === null ? null : (string) $value;
    }

    /**
     * @return array{0: ?string, 1: ?string}
     */
    private function extractRfcs(SimpleXMLElement $xml, string $cfdi): array
    {
        $emisor = $xml->children($cfdi)->Emisor ?? null;
        $receptor = $xml->children($cfdi)->Receptor ?? null;

        return [
            $emisor !== null ? $this->attr($emisor, 'Rfc') : null,
            $receptor !== null ? $this->attr($receptor, 'Rfc') : null,
        ];
    }

    /**
     * @param  array<string, string>  $namespaces
     */
    private function extractUuid(SimpleXMLElement $xml, array $namespaces): ?string
    {
        $tfd = $namespaces['tfd'] ?? 'http://www.sat.gob.mx/TimbreFiscalDigital';
        $cfdi = $namespaces['cfdi'] ?? 'http://www.sat.gob.mx/cfd/4';

        $complemento = $xml->children($cfdi)->Complemento ?? null;
        if ($complemento === null) {
            return null;
        }

        $timbre = $complemento->children($tfd)->TimbreFiscalDigital ?? null;
        if ($timbre === null) {
            return null;
        }

        return $this->attr($timbre, 'UUID');
    }

    /**
     * Suma trasladados IVA y retenciones IVA/ISR. Retorna tambien un snapshot
     * JSON-friendly con el detalle fino para `impuestos_detalle`.
     *
     * @return array{0: float, 1: float, 2: float, 3: array<string, mixed>}
     */
    private function extractImpuestos(SimpleXMLElement $xml, string $cfdi): array
    {
        $impuestos = $xml->children($cfdi)->Impuestos ?? null;
        if ($impuestos === null) {
            return [0.0, 0.0, 0.0, []];
        }

        $traslados = [];
        $retenciones = [];
        $ivaTrasladado = 0.0;
        $ivaRetenido = 0.0;
        $isrRetenido = 0.0;

        $bloqueTraslados = $impuestos->children($cfdi)->Traslados ?? null;
        if ($bloqueTraslados !== null) {
            foreach ($bloqueTraslados->children($cfdi)->Traslado as $t) {
                $impuesto = (string) $this->attr($t, 'Impuesto');
                $importe = (float) ($this->attr($t, 'Importe') ?? 0);
                $traslados[] = [
                    'impuesto' => $impuesto,
                    'tipo_factor' => (string) ($this->attr($t, 'TipoFactor') ?? ''),
                    'tasa' => (string) ($this->attr($t, 'TasaOCuota') ?? ''),
                    'base' => (float) ($this->attr($t, 'Base') ?? 0),
                    'importe' => $importe,
                ];
                if ($impuesto === '002') { // 002 = IVA
                    $ivaTrasladado += $importe;
                }
            }
        }

        $bloqueRetenciones = $impuestos->children($cfdi)->Retenciones ?? null;
        if ($bloqueRetenciones !== null) {
            foreach ($bloqueRetenciones->children($cfdi)->Retencion as $r) {
                $impuesto = (string) $this->attr($r, 'Impuesto');
                $importe = (float) ($this->attr($r, 'Importe') ?? 0);
                $retenciones[] = [
                    'impuesto' => $impuesto,
                    'importe' => $importe,
                ];
                if ($impuesto === '002') {
                    $ivaRetenido += $importe;
                } elseif ($impuesto === '001') { // 001 = ISR
                    $isrRetenido += $importe;
                }
            }
        }

        $totalTrasladadosAttr = (float) ($this->attr($impuestos, 'TotalImpuestosTrasladados') ?? 0);
        $totalRetenidosAttr = (float) ($this->attr($impuestos, 'TotalImpuestosRetenidos') ?? 0);

        return [
            round($ivaTrasladado, 2),
            round($ivaRetenido, 2),
            round($isrRetenido, 2),
            [
                'traslados' => $traslados,
                'retenciones' => $retenciones,
                'total_trasladados' => $totalTrasladadosAttr,
                'total_retenidos' => $totalRetenidosAttr,
            ],
        ];
    }
}
