<?php

namespace App\Console\Commands\Costos;

use App\Models\Costos\SolicitudPago;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Spatie\Activitylog\Models\Activity;

/**
 * Restaura el `monto_total` que la reasignación de centros de costos sobrescribió.
 *
 * Hasta el 18/08/2026 el servicio de reasignación recalculaba el total de la
 * solicitud desde el desglose (`update(['monto_total' => $sumaNueva])`). Corregir
 * a qué centro se carga el gasto reescribía lo que se le debía al proveedor: el
 * total firmado se perdía. Desde entonces sólo se mueve el cargo presupuestal.
 *
 * Lo único que hay que deshacer es ese campo. El presupuesto quedó bien: el
 * acumulado de cada rubro cuadra con el desglose, que es justo lo que debe
 * cargarse. Aquí no se toca ningún RubroAfectado ni ningún detalle.
 *
 * El valor bueno se recupera de la bitácora: `attribute_changes.old.monto_total`
 * de la primera escritura del campo a partir de la primera reasignación. Como
 * una solicitud sólo es editable en borrador, cualquier cambio del monto después
 * de firmada vino de la reasignación.
 *
 * Por defecto corre en dry-run. Requiere --force para escribir.
 */
class ReparaMontoTotalReasignadoCommand extends Command
{
    /** Margen hacia atrás: el update se registra instantes antes que la bitácora de la reasignación. */
    private const MARGEN_SEGUNDOS = 60;

    protected $signature = 'costos:reparar-monto-total-reasignado
        {--solicitud= : Limita la reparación a una solicitud (id o folio)}
        {--force : Ejecuta los cambios; sin esta bandera sólo reporta}';

    protected $description = 'Restaura el monto_total que la reasignación de centros de costos sobrescribió';

    public function handle(): int
    {
        $solicitudes = $this->solicitudesReasignadas();

        if ($solicitudes->isEmpty()) {
            $this->info('No hay solicitudes con reasignación de centros de costos.');

            return self::SUCCESS;
        }

        $plan = $solicitudes
            ->map(fn (SolicitudPago $solicitud): array => $this->planear($solicitud))
            ->sortByDesc(fn (array $p): float => abs((float) ($p['diferencia'] ?? 0)))
            ->values();

        $this->table(
            ['Folio', 'Monto hoy', 'Desglose', 'Monto original', 'Diferencia', 'Acción'],
            $plan->map(fn (array $p): array => [
                $p['folio'],
                number_format($p['monto_hoy'], 2),
                number_format($p['desglose'], 2),
                $p['monto_original'] !== null ? number_format($p['monto_original'], 2) : '-',
                $p['diferencia'] !== null ? number_format($p['diferencia'], 2) : '-',
                $p['accion'],
            ])->all(),
        );

        $reparables = $plan->where('reparable', true);
        $revisar = $plan->where('revisar', true);

        $this->newLine();
        $this->line("Solicitudes con reasignación: {$plan->count()}");
        $this->line("A reparar: {$reparables->count()}");

        if ($revisar->isNotEmpty()) {
            $this->warn("Requieren revisión manual: {$revisar->count()} (ver la columna Acción).");
        }

        if ($reparables->isEmpty()) {
            $this->info('No hay nada que restaurar.');

            return self::SUCCESS;
        }

        if (! $this->option('force')) {
            $this->newLine();
            $this->warn('DRY-RUN: no se escribió nada. Vuelve a ejecutar con --force.');

            return self::SUCCESS;
        }

        $reparadas = 0;

        DB::transaction(function () use ($reparables, &$reparadas): void {
            foreach ($reparables as $p) {
                $solicitud = $p['solicitud'];
                $montoSobrescrito = round((float) $solicitud->monto_total, 2);

                $solicitud->update(['monto_total' => $p['monto_original']]);

                activity('costos')
                    ->performedOn($solicitud)
                    ->withProperties([
                        'monto_sobrescrito' => $montoSobrescrito,
                        'monto_restaurado' => $p['monto_original'],
                        'reasignado_at' => $p['reasignado_at']?->toDateTimeString(),
                    ])
                    ->log('Monto total restaurado tras reasignación');

                $reparadas++;
            }
        });

        $this->newLine();
        $this->info("Listo: {$reparadas} solicitudes con su monto original restaurado.");

        return self::SUCCESS;
    }

    /**
     * @return Collection<int, SolicitudPago>
     */
    private function solicitudesReasignadas(): Collection
    {
        $ids = Activity::query()
            ->where('subject_type', SolicitudPago::class)
            ->where('description', 'Centros de costos reasignados')
            ->distinct()
            ->pluck('subject_id');

        return SolicitudPago::query()
            ->whereIn('id', $ids)
            ->when($this->option('solicitud'), function ($query, $solicitud): void {
                $query->where(fn ($q) => $q->where('id', (int) $solicitud)->orWhere('folio', $solicitud));
            })
            ->withSum('detalles as suma_desglose', 'subtotal')
            ->orderBy('folio')
            ->get();
    }

    /**
     * Arma el diagnóstico de una solicitud sin escribir nada.
     *
     * @return array<string, mixed>
     */
    private function planear(SolicitudPago $solicitud): array
    {
        $montoHoy = round((float) $solicitud->monto_total, 2);

        $base = [
            'solicitud' => $solicitud,
            'folio' => $solicitud->folio,
            'monto_hoy' => $montoHoy,
            'desglose' => round((float) ($solicitud->suma_desglose ?? 0), 2),
            'monto_original' => null,
            'diferencia' => null,
            'reasignado_at' => null,
            'reparable' => false,
            'revisar' => false,
        ];

        $primera = $this->primeraReasignacion($solicitud);

        if ($primera === null) {
            return [...$base, 'accion' => 'sin bitácora de reasignación'];
        }

        $base['reasignado_at'] = $primera->created_at;
        $sobrescrituras = $this->sobrescrituras($solicitud, $primera);

        if ($sobrescrituras->isEmpty()) {
            return [...$base, 'accion' => 'la reasignación no tocó el monto (ya está sano)'];
        }

        $montoOriginal = round((float) data_get($sobrescrituras->first()->attribute_changes, 'old.monto_total'), 2);
        $ultimoEscrito = (float) data_get($sobrescrituras->last()->attribute_changes, 'attributes.monto_total');

        $base['monto_original'] = $montoOriginal;
        $base['diferencia'] = round($montoHoy - $montoOriginal, 2);

        // La última escritura registrada tiene que ser el valor de hoy. Si no,
        // algo fuera de la reasignación movió el monto después y la bitácora ya
        // no alcanza para decidir cuál es el bueno.
        if (abs($ultimoEscrito - $montoHoy) > 0.005) {
            return [...$base, 'revisar' => true, 'accion' => 'REVISAR: otro proceso movió el monto después'];
        }

        if (abs((float) $base['diferencia']) < 0.005) {
            return [...$base, 'accion' => 'el monto ya coincide con el original'];
        }

        return [...$base, 'reparable' => true, 'accion' => 'restaurar'];
    }

    private function primeraReasignacion(SolicitudPago $solicitud): ?Activity
    {
        return Activity::query()
            ->where('subject_type', SolicitudPago::class)
            ->where('subject_id', $solicitud->id)
            ->where('description', 'Centros de costos reasignados')
            ->orderBy('created_at')
            ->orderBy('id')
            ->first();
    }

    /**
     * Escrituras de `monto_total` a partir de la primera reasignación, en orden.
     * La primera guarda el valor firmado; la última, el que quedó.
     *
     * @return Collection<int, Activity>
     */
    private function sobrescrituras(SolicitudPago $solicitud, Activity $primera): Collection
    {
        return Activity::query()
            ->where('subject_type', SolicitudPago::class)
            ->where('subject_id', $solicitud->id)
            ->where('created_at', '>=', $primera->created_at->subSeconds(self::MARGEN_SEGUNDOS))
            ->orderBy('created_at')
            ->orderBy('id')
            ->get()
            ->filter(fn (Activity $a): bool => data_get($a->attribute_changes, 'old.monto_total') !== null
                && data_get($a->attribute_changes, 'attributes.monto_total') !== null)
            ->values();
    }
}
