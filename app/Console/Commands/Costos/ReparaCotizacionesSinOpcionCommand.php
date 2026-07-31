<?php

namespace App\Console\Commands\Costos;

use App\Models\Costos\RequisicionCotizacionOpcion;
use App\Models\Costos\RequisicionCotizacionPrecio;
use App\Models\Costos\RequisicionSeleccion;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Repara las cotizaciones que quedaron sin `opcion_id`.
 *
 * El comparativo dibuja una columna por opción y busca la celda por
 * `opcion_id`, así que una cotización sin opción es invisible: no se puede
 * cotizar sobre ella y, si una selección le apunta, la celda que sí se ve nunca
 * se marca como seleccionada (se queda en verde de "mejor precio" en vez de
 * azul).
 *
 * Las dejó `duplicar()`, que copiaba precios sin copiar las opciones. Para cada
 * huérfana:
 *  - si su partida ya tiene una celda visible del mismo proveedor, se le pasan
 *    las selecciones y la huérfana se borra (es la copia muerta);
 *  - si no, se le asigna la opción del proveedor en esa requisición, creándola
 *    si hace falta, para que la celda aparezca con su precio.
 *
 * Por defecto corre en dry-run. Requiere --force para escribir.
 */
class ReparaCotizacionesSinOpcionCommand extends Command
{
    protected $signature = 'costos:reparar-cotizaciones-sin-opcion
        {--requisicion= : Limita la reparación a una requisición (id)}
        {--force : Ejecuta los cambios; sin esta bandera sólo reporta}';

    protected $description = 'Repara cotizaciones sin opcion_id, que quedan invisibles en el comparativo';

    public function handle(): int
    {
        $huerfanas = RequisicionCotizacionPrecio::query()
            ->whereNull('opcion_id')
            ->with('detalle:id,requisicion_id')
            ->when($this->option('requisicion'), fn ($q, $id) => $q->whereHas(
                'detalle',
                fn ($d) => $d->where('requisicion_id', (int) $id),
            ))
            ->orderBy('id')
            ->get();

        if ($huerfanas->isEmpty()) {
            $this->info('No hay cotizaciones sin opción.');

            return self::SUCCESS;
        }

        $plan = [];
        foreach ($huerfanas as $cotizacion) {
            $plan[] = $this->planear($cotizacion);
        }

        $this->table(
            ['Cotización', 'Requisición', 'Partida', 'Proveedor', 'Precio', 'Selecciones', 'Acción'],
            array_map(fn (array $p) => [
                $p['cotizacion_id'],
                $p['requisicion_id'],
                $p['detalle_id'],
                $p['proveedor_id'],
                $p['precio'],
                $p['selecciones'],
                $p['accion'],
            ], $plan),
        );

        if (! $this->option('force')) {
            $this->newLine();
            $this->warn('DRY-RUN: no se escribió nada. Vuelve a ejecutar con --force.');

            return self::SUCCESS;
        }

        $reapuntadas = 0;
        $asignadas = 0;

        DB::transaction(function () use ($huerfanas, &$reapuntadas, &$asignadas): void {
            foreach ($huerfanas as $cotizacion) {
                $gemela = $this->gemelaVisible($cotizacion);

                if ($gemela !== null) {
                    RequisicionSeleccion::where('cotizacion_precio_id', $cotizacion->id)
                        ->update(['cotizacion_precio_id' => $gemela->id]);
                    $cotizacion->delete();
                    $reapuntadas++;

                    continue;
                }

                $cotizacion->update(['opcion_id' => $this->opcionDelProveedor($cotizacion)->id]);
                $asignadas++;
            }
        });

        $this->newLine();
        $this->info("Listo: {$reapuntadas} cotizaciones fusionadas con su celda visible y {$asignadas} conectadas a una opción nueva.");

        return self::SUCCESS;
    }

    /**
     * @return array<string, mixed>
     */
    private function planear(RequisicionCotizacionPrecio $cotizacion): array
    {
        $gemela = $this->gemelaVisible($cotizacion);
        $selecciones = RequisicionSeleccion::where('cotizacion_precio_id', $cotizacion->id)->count();

        return [
            'cotizacion_id' => $cotizacion->id,
            'requisicion_id' => $cotizacion->detalle?->requisicion_id,
            'detalle_id' => $cotizacion->requisicion_detalle_id,
            'proveedor_id' => $cotizacion->proveedor_id,
            'precio' => (string) $cotizacion->precio_unitario,
            'selecciones' => $selecciones,
            'accion' => $gemela !== null
                ? "fusionar en #{$gemela->id}".($selecciones > 0 ? " (mueve {$selecciones} selección/es)" : '')
                : 'asignar opción',
        ];
    }

    /** La celda que sí se dibuja: misma partida y proveedor, pero con opción. */
    private function gemelaVisible(RequisicionCotizacionPrecio $cotizacion): ?RequisicionCotizacionPrecio
    {
        return RequisicionCotizacionPrecio::query()
            ->where('requisicion_detalle_id', $cotizacion->requisicion_detalle_id)
            ->where('proveedor_id', $cotizacion->proveedor_id)
            ->whereNotNull('opcion_id')
            ->orderBy('id')
            ->first();
    }

    /** Opción del proveedor en esa requisición; se crea si aún no existe. */
    private function opcionDelProveedor(RequisicionCotizacionPrecio $cotizacion): RequisicionCotizacionOpcion
    {
        $requisicionId = $cotizacion->detalle->requisicion_id;

        return RequisicionCotizacionOpcion::firstOrCreate(
            [
                'requisicion_id' => $requisicionId,
                'proveedor_id' => $cotizacion->proveedor_id,
            ],
            [
                'orden' => (int) RequisicionCotizacionOpcion::where('requisicion_id', $requisicionId)->max('orden') + 1,
            ],
        );
    }
}
