<?php

namespace App\Services\Costos;

use App\Enums\Costos\DocumentoTipo;
use App\Models\Costos\Factura;
use App\Models\Costos\OrdenCompra;
use Closure;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * Registro de facturas de proveedor a partir de un CFDI parseado, único para
 * los dos caminos de captura (admin de OC de contado y portal del proveedor):
 * valida UUID duplicado y saldo facturable, crea la Factura con los datos
 * fiscales del CFDI, adjunta los archivos y recalcula el estatus de la OC —
 * todo dentro de una transacción.
 */
class RegistradorFacturaCfdi
{
    /**
     * Mensaje de error si el CFDI no puede registrarse contra la OC, o null si
     * es registrable (UUID no duplicado y total dentro del saldo facturable).
     *
     * @param  array<string, mixed>  $fiscal
     */
    public function validar(OrdenCompra $oc, array $fiscal): ?string
    {
        if (! empty($fiscal['uuid_fiscal'])
            && Factura::where('uuid_fiscal', $fiscal['uuid_fiscal'])->exists()) {
            return 'Ya existe una factura registrada con ese UUID fiscal.';
        }

        $saldoFacturable = (float) $oc->saldo_facturable;
        $totalCfdi = (float) ($fiscal['total'] ?? 0);

        if ($totalCfdi > $saldoFacturable + (float) config('costos.epsilon_monto')) {
            return sprintf(
                'El total del CFDI ($%s) excede el saldo facturable de la OC ($%s). Verifica que el XML corresponda a esta orden.',
                number_format($totalCfdi, 2),
                number_format($saldoFacturable, 2),
            );
        }

        return null;
    }

    /**
     * Crea la factura con los campos fiscales del CFDI más los `$extra` propios
     * del flujo (estatus inicial, aprobaciones, notas), ejecuta `$adjuntar` para
     * los archivos y recalcula el estatus de la OC, en una sola transacción.
     *
     * @param  array<string, mixed>  $fiscal
     * @param  array<string, mixed>  $extra
     * @param  ?Closure(Factura): void  $adjuntar
     */
    public function registrar(OrdenCompra $oc, array $fiscal, array $extra, ?Closure $adjuntar = null): Factura
    {
        return DB::transaction(function () use ($oc, $fiscal, $extra, $adjuntar): Factura {
            $factura = Factura::create([
                'orden_compra_id' => $oc->id,
                'proveedor_id' => $oc->proveedor_id,
                'uuid_fiscal' => $fiscal['uuid_fiscal'] ?? null,
                'folio_fiscal' => $fiscal['folio_fiscal'] ?? null,
                'subtotal' => $fiscal['subtotal'] ?? 0,
                'iva' => $fiscal['iva_trasladado'] ?? 0,
                'iva_trasladado' => $fiscal['iva_trasladado'] ?? 0,
                'iva_retenido' => $fiscal['iva_retenido'] ?? 0,
                'isr_retenido' => $fiscal['isr_retenido'] ?? 0,
                'impuestos_detalle' => $fiscal['impuestos_detalle'] ?? null,
                'total' => $fiscal['total'] ?? 0,
                'moneda' => $oc->moneda,
                'tipo_cambio' => $oc->tipo_cambio,
                'metodo_pago' => $fiscal['metodo_pago'] ?? null,
                'forma_pago' => $fiscal['forma_pago'] ?? null,
                'fecha_factura' => $fiscal['fecha_factura'] ?? null,
                ...$extra,
            ]);

            if ($adjuntar) {
                $adjuntar($factura);
            }

            $oc->recalcularEstatus();

            return $factura;
        });
    }

    /**
     * Adjunta un archivo recién subido a la carpeta definitiva de la factura.
     */
    public function adjuntarArchivo(Factura $factura, UploadedFile $archivo, DocumentoTipo $tipo, string $nombreArchivo, ?string $mime = null): void
    {
        $path = $archivo->storeAs($this->directorio($factura), $nombreArchivo, 'public');

        $factura->media()->create([
            'descripcion' => $tipo->value,
            'nombre_original' => $archivo->getClientOriginalName(),
            'path' => $path,
            'mime' => $mime ?? $archivo->getMimeType(),
            'size' => Storage::disk('public')->size($path),
        ]);
    }

    /**
     * Mueve un archivo del storage temporal (preview del portal) a la carpeta
     * definitiva de la factura y lo adjunta.
     */
    public function adjuntarDesdeTemporal(Factura $factura, string $rutaTemporal, string $nombreOriginal, DocumentoTipo $tipo, string $nombreArchivo, string $mime): void
    {
        $destino = $this->directorio($factura).'/'.$nombreArchivo;
        Storage::disk('public')->move($rutaTemporal, $destino);

        $factura->media()->create([
            'descripcion' => $tipo->value,
            'nombre_original' => $nombreOriginal,
            'path' => $destino,
            'mime' => $mime,
            'size' => Storage::disk('public')->size($destino),
        ]);
    }

    private function directorio(Factura $factura): string
    {
        return "facturas/{$factura->proveedor_id}/{$factura->id}";
    }
}
