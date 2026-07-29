<?php

namespace App\Services\Prod;

use App\Models\Concepto;
use App\Models\Obra;
use App\Models\Prod\Destajo;
use App\Models\Prod\GrupoPrecioConcepto;
use App\Models\Prod\GrupoTrabajo;
use App\Models\Prod\Liquidacion;
use App\Models\Prod\LiquidacionDetalle;
use App\Models\Prod\PagoExtra;
use App\Models\Prod\Registro;
use App\Models\Prod\TipoPagoExtra;
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

    /** Clave de agrupacion: misma pieza pagada al mismo porcentaje. */
    private function clavePiezaPorcentaje(Registro $registro): string
    {
        return $registro->concepto_id.'|'.number_format((float) $registro->porcentaje, 2, '.', '');
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

        // Se agrupa por pieza Y porcentaje: un mismo lote pagado al 60% y otro
        // al 100% en la misma semana son renglones distintos de la orden.
        foreach ($registrosGrupo->groupBy($this->clavePiezaPorcentaje(...)) as $registrosConcepto) {
            $primero = $registrosConcepto->first();
            $concepto = $primero->concepto;
            $conceptoId = (int) $primero->concepto_id;
            $porcentaje = (float) $primero->porcentaje;

            $cantidadTotal = (int) $registrosConcepto->sum('cantidad');
            $kilos = round($cantidadTotal * (float) $concepto->peso_unitario * ($porcentaje / 100), 3);

            $grupoPrecioConcepto = $this->grupoPrecioConcepto($conceptoId, $concepto->obra_id);
            $precioKilo = (float) ($grupoPrecioConcepto?->grupoPrecio?->precio_kilo ?? 0);
            $total = round($kilos * $precioKilo, 2);

            $totalKilos += $kilos;
            $totalProduccion += $total;

            $detallesData[] = [
                'concepto_id' => $conceptoId,
                // Snapshot del renglon: la orden de pago de una semana cerrada
                // no debe cambiar aunque despues se edite o borre la pieza.
                'obra_id' => $concepto->obra_id,
                'marca' => $concepto->marca,
                'descripcion' => $concepto->descripcion,
                'peso_unitario' => $concepto->peso_unitario,
                'longitud' => $concepto->longitud,
                'grupo_precio_id' => $grupoPrecioConcepto?->grupo_precio_id ?? 0,
                'cantidad' => $cantidadTotal,
                'porcentaje' => $porcentaje,
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
     * Datos normalizados de la orden de pago, una entrada por grupo de trabajo.
     * Si el destajo esta cerrado se lee de las liquidaciones inmutables; si esta
     * abierto se calcula del preview (los numeros aun pueden cambiar al cerrar).
     * Las secciones de pagos extra salen del catalogo TipoPagoExtra en ambos casos.
     *
     * @return Collection<int, array<string, mixed>>
     */
    public function ordenDePago(Destajo $destajo): Collection
    {
        $tipos = TipoPagoExtra::orderBy('orden')->get();

        $pagosPorGrupo = PagoExtra::query()
            ->where('destajo_id', $destajo->id)
            ->get()
            ->groupBy('grupo_trabajo_id');

        return $destajo->cerrado
            ? $this->ordenDesdeLiquidaciones($destajo, $tipos, $pagosPorGrupo)
            : $this->ordenDesdePreview($destajo, $tipos, $pagosPorGrupo);
    }

    /**
     * @param  Collection<int, TipoPagoExtra>  $tipos
     * @param  Collection<int, Collection<int, PagoExtra>>  $pagosPorGrupo
     * @return Collection<int, array<string, mixed>>
     */
    private function ordenDesdeLiquidaciones(Destajo $destajo, Collection $tipos, Collection $pagosPorGrupo): Collection
    {
        $destajo->loadMissing([
            'liquidaciones.grupoTrabajo',
            'liquidaciones.detalles.concepto.obra',
            'liquidaciones.empleados',
        ]);

        $obras = Obra::query()
            ->whereIn('id', $destajo->liquidaciones->flatMap->detalles->pluck('obra_id')->filter()->unique())
            ->get()
            ->keyBy('id');

        return $destajo->liquidaciones->map(function (Liquidacion $liq) use ($tipos, $pagosPorGrupo, $obras) {
            // Todo sale del snapshot del renglon, nunca del concepto vivo.
            $piezas = $liq->detalles->map(fn (LiquidacionDetalle $d) => [
                'marca' => $d->marca ?? "#{$d->concepto_id}",
                'descripcion' => $d->descripcion ?? '',
                'obra' => $this->nombreObraSnapshot($d, $obras),
                'pzs' => (int) $d->cantidad,
                'porcentaje' => (float) $d->porcentaje,
                'largo' => $d->longitud,
                'peso_unitario' => $d->peso_unitario !== null ? (float) $d->peso_unitario : null,
                'kilos' => (float) $d->kilos,
                'precio_kilo' => (float) $d->precio_kilo_aplicado,
                'importe' => (float) $d->total,
            ])->all();

            $empleados = $liq->empleados->map(fn ($e) => [
                'nombre' => $e->nombre,
                'no_empleado' => $e->no_empleado,
                'porcentaje' => (float) $e->porcentaje,
                'monto' => (float) $e->monto_asignado,
            ])->all();

            return $this->armarGrupo(
                $liq->grupoTrabajo,
                $piezas,
                (float) $liq->total_kilos,
                (float) $liq->total_produccion,
                (float) $liq->total_extras,
                (float) $liq->total_final,
                $empleados,
                $tipos,
                $pagosPorGrupo->get($liq->grupo_trabajo_id, collect()),
            );
        })->values();
    }

    /**
     * @param  Collection<int, TipoPagoExtra>  $tipos
     * @param  Collection<int, Collection<int, PagoExtra>>  $pagosPorGrupo
     * @return Collection<int, array<string, mixed>>
     */
    private function ordenDesdePreview(Destajo $destajo, Collection $tipos, Collection $pagosPorGrupo): Collection
    {
        $registrosPorGrupo = $this->registrosDelDestajo($destajo)->groupBy('grupo_trabajo_id');
        $grupoIds = $registrosPorGrupo->keys()->merge($pagosPorGrupo->keys())->unique();

        return $grupoIds->map(function ($grupoId) use ($registrosPorGrupo, $pagosPorGrupo, $tipos) {
            $grupoId = (int) $grupoId;
            $registrosGrupo = $registrosPorGrupo->get($grupoId, collect());

            $piezas = [];
            $totalKilos = 0.0;
            $totalProduccion = 0.0;

            foreach ($registrosGrupo->groupBy($this->clavePiezaPorcentaje(...)) as $registrosConcepto) {
                $primero = $registrosConcepto->first();
                $concepto = $primero->concepto;
                if ($concepto === null) {
                    continue;
                }

                $porcentaje = (float) $primero->porcentaje;
                $cantidad = (int) $registrosConcepto->sum('cantidad');
                $kilos = round($cantidad * (float) $concepto->peso_unitario * ($porcentaje / 100), 3);
                $precioKilo = (float) ($this->grupoPrecioConcepto((int) $primero->concepto_id, $concepto->obra_id)?->grupoPrecio?->precio_kilo ?? 0);
                $importe = round($kilos * $precioKilo, 2);

                $totalKilos += $kilos;
                $totalProduccion += $importe;

                $piezas[] = [
                    'marca' => $concepto->marca,
                    'descripcion' => $concepto->descripcion,
                    'obra' => $this->nombreObra($concepto),
                    'pzs' => $cantidad,
                    'porcentaje' => $porcentaje,
                    'largo' => $concepto->longitud,
                    'peso_unitario' => (float) $concepto->peso_unitario,
                    'kilos' => $kilos,
                    'precio_kilo' => $precioKilo,
                    'importe' => $importe,
                ];
            }

            $pagosGrupo = $pagosPorGrupo->get($grupoId, collect());
            $totalExtras = (float) $pagosGrupo->sum(fn (PagoExtra $pe) => $pe->monto);
            $totalFinal = $totalProduccion + $totalExtras;

            $grupoTrabajo = $registrosGrupo->isNotEmpty()
                ? $registrosGrupo->first()->grupoTrabajo
                : GrupoTrabajo::with('empleados')->find($grupoId);

            $empleados = ($grupoTrabajo?->empleados ?? collect())->map(fn ($e) => [
                'nombre' => $e->nombre,
                'no_empleado' => $e->no_empleado,
                'porcentaje' => (float) $e->porcentaje,
                'monto' => round($totalFinal * ((float) $e->porcentaje / 100), 2),
            ])->all();

            return $this->armarGrupo($grupoTrabajo, $piezas, $totalKilos, $totalProduccion, $totalExtras, $totalFinal, $empleados, $tipos, $pagosGrupo);
        })->values();
    }

    /**
     * @param  array<int, array<string, mixed>>  $piezas
     * @param  array<int, array<string, mixed>>  $empleados
     * @param  Collection<int, TipoPagoExtra>  $tipos
     * @param  Collection<int, PagoExtra>  $pagosGrupo
     * @return array<string, mixed>
     */
    private function armarGrupo(?GrupoTrabajo $grupo, array $piezas, float $totalKilos, float $totalProduccion, float $totalExtras, float $totalFinal, array $empleados, Collection $tipos, Collection $pagosGrupo): array
    {
        return [
            'grupo' => [
                'descripcion' => $grupo?->descripcion ?? 'Grupo',
                'linea' => $grupo?->linea,
                'modulo' => $grupo?->modulo,
            ],
            'piezas' => $piezas,
            'total_kilos' => $totalKilos,
            'total_produccion' => $totalProduccion,
            'total_extras' => $totalExtras,
            'total_final' => $totalFinal,
            'empleados' => $empleados,
            'secciones' => $this->seccionesExtra($tipos, $pagosGrupo),
        ];
    }

    /**
     * Una seccion por cada tipo del catalogo (aunque no tenga pagos), con sus
     * lineas descripcion/precio/dias/personas y el subtotal del tipo.
     *
     * @param  Collection<int, TipoPagoExtra>  $tipos
     * @param  Collection<int, PagoExtra>  $pagosGrupo
     * @return array<int, array<string, mixed>>
     */
    private function seccionesExtra(Collection $tipos, Collection $pagosGrupo): array
    {
        return $tipos->map(function (TipoPagoExtra $tipo) use ($pagosGrupo) {
            $pagos = $pagosGrupo->where('tipo_id', $tipo->id)->values();

            return [
                'tipo' => $tipo->descripcion,
                'pagos' => $pagos->map(fn (PagoExtra $p) => [
                    'descripcion' => $p->descripcion,
                    'precio' => (float) $p->precio,
                    'dias' => (int) $p->dias,
                    'personas' => (int) $p->personas,
                    'importe' => (float) $p->monto,
                ])->all(),
                'subtotal' => (float) $pagos->sum(fn (PagoExtra $p) => $p->monto),
            ];
        })->all();
    }

    private function nombreObra(?Concepto $concepto): string
    {
        return $this->etiquetaObra($concepto?->obra);
    }

    /**
     * La obra del renglon liquidado se resuelve por el obra_id del snapshot,
     * no por el concepto (que pudo cambiar de catalogo o desaparecer).
     *
     * @param  \Illuminate\Support\Collection<int, Obra>  $obras
     */
    private function nombreObraSnapshot(LiquidacionDetalle $detalle, Collection $obras): string
    {
        return $this->etiquetaObra(
            $detalle->obra_id !== null ? $obras->get($detalle->obra_id) : $detalle->concepto?->obra
        );
    }

    private function etiquetaObra(?Obra $obra): string
    {
        if ($obra === null) {
            return '-';
        }

        return trim(($obra->no ? $obra->no.' - ' : '').$obra->descripcion);
    }

    /**
     * @return \Illuminate\Database\Eloquent\Collection<int, Registro>
     */
    private function registrosDelDestajo(Destajo $destajo)
    {
        return Registro::query()
            ->with(['concepto.obra', 'grupoTrabajo.empleados'])
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
