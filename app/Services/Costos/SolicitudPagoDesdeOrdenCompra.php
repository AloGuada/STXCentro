<?php

namespace App\Services\Costos;

use App\Enums\Costos\DocumentoTipo;
use App\Enums\Costos\SolicitudPagoEstatus;
use App\Models\Costos\AprobacionDepartamento;
use App\Models\Costos\OrdenCompra;
use App\Models\Costos\SolicitudPago;
use App\Models\Costos\TipoSolicitud;
use App\Models\Media;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class SolicitudPagoDesdeOrdenCompra
{
    public const TIPO_TITULO = 'Pago de orden de compra (contado)';

    /**
     * Genera la solicitud de pago de anticipo para una OC de contado: arma su
     * cadena de aprobación por departamento y adjunta el PDF de la OC como
     * respaldo. NO aparta ni afecta el presupuesto (la OC ya aplicó su impacto
     * permanente al crearse), evitando duplicar el acumulado del rubro.
     *
     * Idempotente: si la OC ya tiene una solicitud asociada, no hace nada.
     */
    public function crear(OrdenCompra $oc, string $userId): ?SolicitudPago
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
                'tipo_pago' => 'transferencia',
                'tipo_moneda' => $oc->moneda,
                'fecha_pago_solicitada' => now()->toDateString(),
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

            $this->crearCadenaAprobacion($solicitud);
        } catch (\Throwable $e) {
            Storage::disk('public')->delete($media->path);

            throw $e;
        }

        return $solicitud;
    }

    /**
     * Renderiza el formato de la OC y lo persiste en disco + tabla media,
     * activando la relación OrdenCompra::pdfFormato().
     */
    private function generarPdfMedia(OrdenCompra $oc): Media
    {
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

    /**
     * Replica la cadena de aprobación por departamento (multiusuario por nivel)
     * filtrando por el tipo de aprobación de solicitudes de pago.
     */
    private function crearCadenaAprobacion(SolicitudPago $solicitud): void
    {
        $cadena = AprobacionDepartamento::where('departamento_id', $solicitud->departamento_id)
            ->whereHas('permiso', fn ($q) => $q->where('tipo_aprobacion', SolicitudPago::TIPO_APROBACION))
            ->with('permiso')
            ->get()
            ->sortBy('permiso.nivel')
            ->values();

        if ($cadena->isEmpty()) {
            Log::warning('Solicitud de pago de OC contado sin cadena de aprobación configurada.', [
                'solicitud_id' => $solicitud->id,
                'departamento_id' => $solicitud->departamento_id,
            ]);

            return;
        }

        foreach ($cadena as $asignacion) {
            $solicitud->aprobaciones()->create([
                'nivel' => $asignacion->permiso->nivel,
                'aprobador_id' => $asignacion->aprobador_id,
                'estatus' => 'pendiente',
            ]);
        }
    }
}
