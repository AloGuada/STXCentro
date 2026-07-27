<?php

namespace App\Services\Prod;

use App\Models\Prod\Destajo;
use App\Models\Prod\GrupoPrecioConcepto;
use App\Models\Prod\GrupoTrabajo;
use App\Models\Prod\Liquidacion;
use App\Models\Prod\PagoExtra;
use App\Models\Prod\Registro;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class GeneradorLiquidaciones
{
    /**
     * Cierra el destajo generando una liquidacion inmutable por grupo de trabajo.
     *
     * Por grupo: agrupa la produccion por concepto (kilos = cantidad x peso_unitario,
     * total = kilos x precio_kilo del grupo de precio), suma los pagos extra
     * (precio x dias x personas) y reparte el total a cada empleado por su porcentaje.
     */
    public function generar(Destajo $destajo): void
    {
        DB::transaction(function () use ($destajo) {
            $registrosPorGrupo = $this->registrosDelDestajo($destajo)->groupBy('grupo_trabajo_id');

            $pagosExtraPorGrupo = PagoExtra::query()
                ->where('destajo_id', $destajo->id)
                ->get()
                ->groupBy('grupo_trabajo_id');

            $grupoIds = $registrosPorGrupo->keys()->merge($pagosExtraPorGrupo->keys())->unique();

            foreach ($grupoIds as $grupoTrabajoId) {
                $this->generarLiquidacionGrupo(
                    $destajo,
                    (int) $grupoTrabajoId,
                    $registrosPorGrupo->get($grupoTrabajoId, collect()),
                    $pagosExtraPorGrupo->get($grupoTrabajoId, collect()),
                );
            }

            $destajo->update([
                'cerrado' => true,
                'fecha_cierre' => now(),
            ]);
        });
    }

    /**
     * Piezas con produccion en el destajo que no tienen precio asignado.
     * Se pagarian en cero silenciosamente; se usa para advertir antes de cerrar.
     *
     * @return Collection<int, array{concepto_id: int, marca: string, descripcion: string, cantidad: int}>
     */
    public function piezasSinPrecio(Destajo $destajo): Collection
    {
        return $this->registrosDelDestajo($destajo)
            ->groupBy('concepto_id')
            ->map(function (Collection $registros, int $conceptoId) {
                $concepto = $registros->first()->concepto;

                if ($concepto === null || $this->precioKilo($conceptoId, $concepto->obra_id) !== null) {
                    return null;
                }

                return [
                    'concepto_id' => $conceptoId,
                    'marca' => $concepto->marca,
                    'descripcion' => $concepto->descripcion,
                    'cantidad' => (int) $registros->sum('cantidad'),
                ];
            })
            ->filter()
            ->values();
    }

    /**
     * @param  Collection<int, Registro>  $registrosGrupo
     * @param  Collection<int, PagoExtra>  $pagosExtraGrupo
     */
    private function generarLiquidacionGrupo(Destajo $destajo, int $grupoTrabajoId, Collection $registrosGrupo, Collection $pagosExtraGrupo): void
    {
        $totalKilos = 0.0;
        $totalProduccion = 0.0;
        $detallesData = [];

        foreach ($registrosGrupo->groupBy('concepto_id') as $conceptoId => $registrosConcepto) {
            $concepto = $registrosConcepto->first()->concepto;
            $cantidadTotal = (int) $registrosConcepto->sum('cantidad');
            $kilos = round($cantidadTotal * (float) $concepto->peso_unitario, 3);

            $grupoPrecioConcepto = $this->grupoPrecioConcepto((int) $conceptoId, $concepto->obra_id);
            $precioKilo = (float) ($grupoPrecioConcepto?->grupoPrecio?->precio_kilo ?? 0);
            $total = round($kilos * $precioKilo, 2);

            $totalKilos += $kilos;
            $totalProduccion += $total;

            $detallesData[] = [
                'concepto_id' => (int) $conceptoId,
                'grupo_precio_id' => $grupoPrecioConcepto?->grupo_precio_id ?? 0,
                'cantidad' => $cantidadTotal,
                'kilos' => $kilos,
                'precio_kilo_aplicado' => $precioKilo,
                'total' => $total,
            ];
        }

        $totalExtras = (float) $pagosExtraGrupo->sum(fn (PagoExtra $pe) => $pe->precio * $pe->dias * $pe->personas);
        $totalFinal = $totalProduccion + $totalExtras;

        $liquidacion = Liquidacion::create([
            'destajo_id' => $destajo->id,
            'grupo_trabajo_id' => $grupoTrabajoId,
            'total_kilos' => $totalKilos,
            'total_produccion' => $totalProduccion,
            'total_extras' => $totalExtras,
            'total_final' => $totalFinal,
            'generado_en' => now(),
            'generado_por' => auth()->id(),
        ]);

        foreach ($detallesData as $detalle) {
            $liquidacion->detalles()->create($detalle);
        }

        $grupoTrabajo = $registrosGrupo->isNotEmpty()
            ? $registrosGrupo->first()->grupoTrabajo
            : GrupoTrabajo::with('empleados')->find($grupoTrabajoId);

        if ($grupoTrabajo === null) {
            return;
        }

        foreach ($grupoTrabajo->empleados as $empleado) {
            $liquidacion->empleados()->create([
                'nombre' => $empleado->nombre,
                'no_empleado' => $empleado->no_empleado,
                'porcentaje' => $empleado->porcentaje,
                'monto_asignado' => round($totalFinal * ((float) $empleado->porcentaje / 100), 2),
            ]);
        }
    }

    /**
     * @return \Illuminate\Database\Eloquent\Collection<int, Registro>
     */
    private function registrosDelDestajo(Destajo $destajo)
    {
        return Registro::query()
            ->with(['concepto', 'grupoTrabajo.empleados'])
            ->whereBetween('fecha', [$destajo->fecha_inicio, $destajo->fecha_fin])
            ->get();
    }

    private function grupoPrecioConcepto(int $conceptoId, int $obraId): ?GrupoPrecioConcepto
    {
        return GrupoPrecioConcepto::query()
            ->whereHas('grupoPrecio', fn ($q) => $q->where('obra_id', $obraId))
            ->where('concepto_id', $conceptoId)
            ->with('grupoPrecio')
            ->first();
    }

    private function precioKilo(int $conceptoId, int $obraId): ?float
    {
        $precio = $this->grupoPrecioConcepto($conceptoId, $obraId)?->grupoPrecio?->precio_kilo;

        return $precio === null ? null : (float) $precio;
    }
}
