<?php

namespace App\Services\Costos;

use App\Enums\Costos\DocumentoTipo;
use App\Enums\Costos\FacturaEstatus;
use App\Models\Costos\ConfiguracionCostos;
use App\Models\Costos\Factura;
use App\Models\Costos\OrdenCompra;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\ValidationException;

/**
 * La factura que ampara una recepción de almacén.
 *
 * Lo normal es que la factura **no exista** cuando el material llega: el
 * proveedor rara vez la sube al portal y el papel viaja con el camión. Por eso
 * el almacenista adjunta el CFDI al recibir y la factura nace aquí. El otro
 * camino —elegir una que ya está en el sistema— se conserva para cuando el
 * proveedor sí se adelantó.
 *
 * Salga por donde salga, el importe se contrasta contra lo que se está
 * recibiendo: es lo único que atrapa el XML de otra entrega, que de otro modo
 * pasaría sin ruido mientras quepa en el saldo de la orden.
 *
 * Si el CFDI viene en otra moneda que la orden (cotizaron en dólares y
 * facturan en pesos), aquí es donde se convierte: lo que entra dice cuánto
 * esperaba cobrar la orden, y el total del CFDI entre ese esperado es el tipo
 * de cambio que aplicó el proveedor. Ver {@see MonedaDelCfdi}.
 *
 * Los rechazos salen como ValidationException con la llave del campo que los
 * provocó, para que la pantalla los pinte donde se pueden corregir.
 */
class FacturaDeLaRecepcion
{
    public function __construct(
        private readonly CfdiXmlParser $parser,
        private readonly RegistradorFacturaCfdi $registrador,
        private readonly RetencionCalculator $impuestos,
        private readonly MonedaDelCfdi $moneda,
    ) {}

    /**
     * @param  list<array{tipo_fiscal: string, subtotal: float, sin_impuestos: bool}>  $lineasRecibidas
     *
     * @throws ValidationException
     */
    public function resolver(
        OrdenCompra $orden,
        array $lineasRecibidas,
        ?UploadedFile $xml,
        ?UploadedFile $pdf,
        ?int $facturaElegidaId,
    ): Factura {
        $esperado = $this->esperadoConImpuestos($orden, $lineasRecibidas);

        if ($facturaElegidaId !== null) {
            return $this->facturaYaCapturada($orden, $esperado, $pdf, $facturaElegidaId);
        }

        if ($xml === null || $pdf === null) {
            throw ValidationException::withMessages([
                'xml' => 'Adjunta el XML del CFDI, o elige la factura si el proveedor ya la subió.',
            ]);
        }

        return $this->facturaDelCfdi($orden, $esperado, $xml, $pdf);
    }

    /**
     * El proveedor se adelantó y la factura ya está en el sistema con sus
     * archivos: aquí sólo se comprueba que sea de esta orden y que ampare lo
     * que está entrando.
     *
     * @throws ValidationException
     */
    private function facturaYaCapturada(
        OrdenCompra $orden,
        float $esperado,
        ?UploadedFile $pdf,
        int $facturaId,
    ): Factura {
        $factura = $orden->facturas()->find($facturaId);

        if ($factura === null) {
            throw ValidationException::withMessages([
                'factura_id' => 'La factura no pertenece a esta orden de compra.',
            ]);
        }

        $this->exigirQueCuadre((float) $factura->total, $esperado, 'factura_id');

        if ($pdf !== null && ! $factura->mediaPdf()->exists()) {
            $this->registrador->adjuntarArchivo($factura, $pdf, DocumentoTipo::PdfFactura, 'cfdi.pdf', 'application/pdf');
        }

        return $factura;
    }

    /**
     * El caso normal: la factura llega con el material y nace de su CFDI.
     *
     * @throws ValidationException
     */
    private function facturaDelCfdi(
        OrdenCompra $orden,
        float $esperado,
        UploadedFile $xml,
        UploadedFile $pdf,
    ): Factura {
        $fiscal = $this->parsear($xml);

        // Un mismo CFDI ampara varias recepciones parciales: la segunda vez que
        // se adjunta no se da de alta otra factura, se liga la que ya existe.
        $existente = ! empty($fiscal['uuid_fiscal'])
            ? Factura::where('uuid_fiscal', $fiscal['uuid_fiscal'])->first()
            : null;

        if ($existente !== null) {
            if ($existente->orden_compra_id !== $orden->id) {
                throw ValidationException::withMessages([
                    'xml' => 'Ese CFDI ya está registrado en otra orden de compra.',
                ]);
            }

            $this->completarArchivos($existente, $xml, $pdf);

            return $existente;
        }

        if ($error = $this->moneda->error($orden, $fiscal, $esperado)) {
            throw ValidationException::withMessages(['xml' => $error]);
        }

        // Con la orden en divisa el total ya sale cuadrado (el tipo de cambio se
        // dedujo de él, y MonedaDelCfdi lo contrastó con el FIX); con la orden
        // en pesos, el TipoCambio del XML sí puede dejarlo fuera de tolerancia.
        $fiscal = $this->moneda->aMonedaDeLaOrden($orden, $fiscal, $esperado);

        $this->exigirQueCuadre((float) $fiscal['total'], $esperado, 'xml');

        if ($error = $this->registrador->validar($orden, $fiscal)) {
            throw ValidationException::withMessages(['xml' => $error]);
        }

        return $this->registrador->registrar(
            $orden,
            $fiscal,
            ['estatus' => FacturaEstatus::PendienteRecepcion],
            function (Factura $factura) use ($xml, $pdf): void {
                $this->registrador->adjuntarArchivo($factura, $xml, DocumentoTipo::XmlFactura, 'cfdi.xml', 'application/xml');
                $this->registrador->adjuntarArchivo($factura, $pdf, DocumentoTipo::PdfFactura, 'cfdi.pdf', 'application/pdf');
            },
        );
    }

    /**
     * @return array<string, mixed>
     *
     * @throws ValidationException
     */
    private function parsear(UploadedFile $xml): array
    {
        try {
            return $this->parser->parse(file_get_contents($xml->getRealPath()) ?: '');
        } catch (\Throwable $e) {
            throw ValidationException::withMessages([
                'xml' => 'No se pudo leer el CFDI: '.$e->getMessage(),
            ]);
        }
    }

    /**
     * Lo que la factura debería totalizar si ampara exactamente lo que está
     * entrando.
     *
     * Se calcula con {@see RetencionCalculator}, el mismo que armó el total de
     * la orden: así respeta las partidas marcadas `sin_impuestos` y las
     * retenciones que le tocan al régimen del proveedor. Deducir una tasa plana
     * del total de la orden fallaría en cuanto la recepción trajera sólo las
     * líneas exentas, o sólo las de flete.
     *
     * @param  list<array{tipo_fiscal: string, subtotal: float, sin_impuestos: bool}>  $lineas
     */
    private function esperadoConImpuestos(OrdenCompra $orden, array $lineas): float
    {
        if ($orden->proveedor === null || $lineas === []) {
            return round(array_sum(array_column($lineas, 'subtotal')), 2);
        }

        return (float) $this->impuestos->calcular($orden->proveedor, $lineas)['total_neto'];
    }

    /**
     * La tolerancia es una sola, en pesos, y vive en Configuración de Costos
     * (`tolerancia_recepcion`). Lo que quede dentro pasa sin más: la recepción
     * dice qué entró y la factura cuánto cobran, y un redondeo del proveedor no
     * es motivo para cambiar cantidades ni precios. Nunca baja del centavo de
     * `epsilon_monto`, que absorbe el redondeo propio del cálculo.
     *
     * @throws ValidationException
     */
    private function exigirQueCuadre(float $total, float $esperado, string $campo): void
    {
        $tolerancia = max(
            (float) config('costos.epsilon_monto'),
            (float) ConfiguracionCostos::actual()->tolerancia_recepcion,
        );

        if (abs($total - $esperado) <= $tolerancia) {
            return;
        }

        throw ValidationException::withMessages([
            $campo => sprintf(
                'El total de la factura ($%s) no cuadra con lo que estás recibiendo ($%s con IVA): la diferencia es de $%s '
                .'y la tolerancia configurada es de $%s. Revisa cantidades y precios, o que sea la factura de esta entrega.',
                number_format($total, 2),
                number_format($esperado, 2),
                number_format(abs($total - $esperado), 2),
                number_format($tolerancia, 2),
            ),
        ]);
    }

    /**
     * A la factura que subió el proveedor por el portal puede faltarle el PDF, y
     * a la que nació de una parcial anterior no le falta nada: se adjunta sólo
     * lo que no esté, para no duplicar filas de `media` ni pisar su archivo.
     */
    private function completarArchivos(Factura $factura, UploadedFile $xml, UploadedFile $pdf): void
    {
        if (! $factura->mediaXml()->exists()) {
            $this->registrador->adjuntarArchivo($factura, $xml, DocumentoTipo::XmlFactura, 'cfdi.xml', 'application/xml');
        }

        if (! $factura->mediaPdf()->exists()) {
            $this->registrador->adjuntarArchivo($factura, $pdf, DocumentoTipo::PdfFactura, 'cfdi.pdf', 'application/pdf');
        }
    }
}
