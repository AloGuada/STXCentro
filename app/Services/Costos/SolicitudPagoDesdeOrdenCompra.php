<?php

namespace App\Services\Costos;

use App\Enums\Costos\DocumentoTipo;
use App\Enums\Costos\SolicitudPagoEstatus;
use App\Models\Costos\ConfiguracionCostos;
use App\Models\Costos\OrdenCompra;
use App\Models\Costos\SolicitudPago;
use App\Models\Costos\TipoSolicitud;
use App\Models\Media;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class SolicitudPagoDesdeOrdenCompra
{
    public const TIPO_TITULO = 'Pago de orden de compra (contado)';

    /**
     * Genera la solicitud de pago de anticipo para una OC de contado: crea su
     * única aprobación (el gerente de compras configurado, ignorando los niveles
     * de aprobación del departamento) y adjunta el PDF de la OC como respaldo.
     * NO aparta ni afecta el presupuesto (la OC ya aplicó su impacto permanente
     * al crearse), evitando duplicar el acumulado del rubro.
     *
     * Idempotente: si la OC ya tiene una solicitud asociada, no hace nada.
     */
    public function crear(OrdenCompra $oc, string $userId, string $metodoPago = 'transferencia', ?string $fechaPago = null): ?SolicitudPago
    {
        if ($oc->solicitudesPago()->exists()) {
            return null;
        }

        $tipo = TipoSolicitud::firstOrCreate(
            ['titulo' => self::TIPO_TITULO],
            [
                'descripcion' => 'Generada automáticamente al liberar una OC de contado.',
                'rubros' => true,
            ],
        );

        $oc->load(['proveedor', 'departamento', 'detalles']);

        $media = $this->generarPdfMedia($oc);

        try {
            $solicitud = SolicitudPago::create([
                'solicitante_id' => $userId,
                'departamento_id' => $oc->departamento_id,
                'proveedor_id' => $oc->proveedor_id,
                'orden_compra_id' => $oc->id,
                'tipo_solicitud_id' => $tipo->id,
                'concepto' => "Pago de contado de orden de compra {$oc->folio}",
                'monto_total' => $oc->total,
                'tipo_pago' => $metodoPago,
                'tipo_moneda' => $oc->moneda,
                'fecha_pago_solicitada' => $this->fechaPagoInicial($fechaPago),
                'estatus' => SolicitudPagoEstatus::PendienteFirma->value,
            ]);

            foreach ($oc->detalles as $detalle) {
                $solicitud->detalles()->create([
                    'obra_rubro_id' => $detalle->obra_rubro_id,
                    'concepto' => $detalle->descripcion,
                    'cantidad' => $detalle->cantidad,
                    'precio_unitario' => $detalle->precio_unitario,
                    'subtotal' => $detalle->subtotal,
                ]);
            }

            $media->mediable()->associate($solicitud);
            $solicitud->archivos()->create([
                'media_id' => $media->id,
                'archivo_id' => null,
                'texto_adicional' => "Formato de OC {$oc->folio}",
            ]);

            if (app(ApprovalChainService::class)->crearAprobacionGerenteCompras($solicitud) === 0) {
                Log::warning('Solicitud de pago de OC contado sin gerente de compras configurado.', [
                    'solicitud_id' => $solicitud->id,
                    'departamento_id' => $solicitud->departamento_id,
                ]);
            }
        } catch (\Throwable $e) {
            Storage::disk('public')->delete($media->path);

            throw $e;
        }

        return $solicitud;
    }

    /**
     * Genera N solicitudes de pago para una OC de contado con parcialidades:
     * una por cada hito, con monto = total × (porcentaje / 100) y su única
     * aprobación del gerente de compras. Comparte el PDF de la OC como respaldo.
     * Idempotente.
     *
     * @param  list<array{porcentaje: float|int|string, concepto?: string|null}>  $parcialidades
     */
    public function crearParcialidades(OrdenCompra $oc, array $parcialidades, string $userId, string $metodoPago = 'transferencia', ?string $fechaPago = null): void
    {
        if ($oc->solicitudesPago()->exists()) {
            return;
        }

        $tipo = TipoSolicitud::firstOrCreate(
            ['titulo' => self::TIPO_TITULO],
            [
                'descripcion' => 'Generada automáticamente al liberar una OC de contado.',
                'rubros' => true,
            ],
        );

        $oc->load(['proveedor', 'departamento', 'detalles']);

        $media = $this->generarPdfMedia($oc);

        try {
            foreach (array_values($parcialidades) as $i => $parcialidad) {
                $pct = (float) $parcialidad['porcentaje'];
                $factor = $pct / 100;
                $monto = round((float) $oc->total * $factor, 2);
                $etiqueta = trim((string) ($parcialidad['concepto'] ?? '')) ?: 'Pago '.($i + 1);

                $solicitud = SolicitudPago::create([
                    'solicitante_id' => $userId,
                    'departamento_id' => $oc->departamento_id,
                    'proveedor_id' => $oc->proveedor_id,
                    'orden_compra_id' => $oc->id,
                    'tipo_solicitud_id' => $tipo->id,
                    'concepto' => "{$etiqueta} (".rtrim(rtrim(number_format($pct, 2, '.', ''), '0'), '.')."%) de orden de compra {$oc->folio}",
                    'monto_total' => $monto,
                    'tipo_pago' => $metodoPago,
                    'tipo_moneda' => $oc->moneda,
                    'fecha_pago_solicitada' => $this->fechaPagoInicial($fechaPago),
                    'estatus' => SolicitudPagoEstatus::PendienteFirma->value,
                ]);

                foreach ($oc->detalles as $detalle) {
                    $solicitud->detalles()->create([
                        'obra_rubro_id' => $detalle->obra_rubro_id,
                        'concepto' => $detalle->descripcion,
                        'cantidad' => $detalle->cantidad,
                        'precio_unitario' => round((float) $detalle->precio_unitario * $factor, 2),
                        'subtotal' => round((float) $detalle->subtotal * $factor, 2),
                    ]);
                }

                $solicitud->archivos()->create([
                    'media_id' => $media->id,
                    'archivo_id' => null,
                    'texto_adicional' => "Formato de OC {$oc->folio}",
                ]);

                if (app(ApprovalChainService::class)->crearAprobacionGerenteCompras($solicitud) === 0) {
                    Log::warning('Solicitud de pago (parcialidad) de OC contado sin gerente de compras configurado.', [
                        'solicitud_id' => $solicitud->id,
                        'departamento_id' => $solicitud->departamento_id,
                    ]);
                }
            }
        } catch (\Throwable $e) {
            Storage::disk('public')->delete($media->path);

            throw $e;
        }
    }

    /**
     * Fecha de pago inicial de la solicitud generada por la OC: respeta la
     * fecha que eligió compras si es hoy o futura (permite este viernes aunque
     * ya haya pasado el corte del miércoles). Si viene vacía o ya pasó, usa el
     * próximo viernes. El recorrido posterior por vencimiento sucede al aprobar.
     */
    private function fechaPagoInicial(?string $fechaPago): string
    {
        $config = ConfiguracionCostos::actual();
        $hoy = CarbonImmutable::now()->startOfDay();

        if ($fechaPago !== null) {
            $fecha = CarbonImmutable::parse($fechaPago)->startOfDay();

            if ($fecha->greaterThanOrEqualTo($hoy)) {
                return $fecha->toDateString();
            }
        }

        return $config->proximoViernes($hoy)->toDateString();
    }

    /**
     * Renderiza el formato de la OC y lo persiste en disco + tabla media,
     * activando la relación OrdenCompra::pdfFormato().
     */
    private function generarPdfMedia(OrdenCompra $oc): Media
    {
        $oc->loadMissing(['detalles.usoCfdi:id,clave', 'requisicion:id,folio']);

        $pdf = Pdf::loadView('pdf.costos.formato-orden-compra', ['oc' => $oc])
            ->setPaper('letter', 'portrait');

        $path = "costos/ordenes-compra/{$oc->id}/OC-{$oc->folio}.pdf";
        Storage::disk('public')->put($path, $pdf->output());

        return $oc->media()->create([
            'descripcion' => DocumentoTipo::OcPdfFormato->value,
            'nombre_original' => "OC-{$oc->folio}.pdf",
            'path' => $path,
            'mime' => 'application/pdf',
            'size' => Storage::disk('public')->size($path),
        ]);
    }
}
