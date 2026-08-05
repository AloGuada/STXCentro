<?php

namespace App\Services\Prod;

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
    public function __construct(private RepartoDelGrupo $reparto) {}

    /**
     * Cierra el destajo generando una liquidacion inmutable por grupo de trabajo.
     *
     * Por grupo: agrupa la produccion por pieza y proceso (kilos = peso de la
     * marca x porcentaje pagado, total = kilos x precio del proceso en el grupo
     * de precios), suma los pagos extra y reparte el total entre los empleados
     * con RepartoDelGrupo.
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
     * Clave de agrupacion del renglon liquidado: una pieza en un proceso. Si la
     * misma pieza se captura dos veces en la semana (dos parcialidades), los
     * porcentajes se suman en un solo renglon.
     */
    private function clavePiezaProceso(Registro $registro): string
    {
        return $registro->pieza_id.'|'.$registro->proceso_id;
    }

    /**
     * Marcas con produccion en el destajo que no tienen tarifa para el proceso
     * en que se trabajaron. Se pagarian en cero silenciosamente; se usa para
     * advertir antes de cerrar.
     *
     * @return Collection<int, array{concepto_id: int, marca: string, etapa: ?string, proceso: string, piezas: int}>
     */
    public function piezasSinPrecio(Destajo $destajo): Collection
    {
        return $this->registrosDelDestajo($destajo)
            ->filter(fn (Registro $r) => $r->pieza?->marca !== null)
            ->groupBy(fn (Registro $r) => $r->pieza->concepto_id.'|'.$r->proceso_id)
            ->map(function (Collection $registros) {
                $primero = $registros->first();
                $marca = $primero->pieza->marca;

                if ($this->precioKilo($marca->id, $marca->obra_id, (int) $primero->proceso_id) > 0) {
                    return null;
                }

                return [
                    'concepto_id' => (int) $marca->id,
                    'marca' => $marca->marca,
                    'etapa' => $marca->etapa,
                    'proceso' => $primero->proceso?->nombre ?? '',
                    'piezas' => $registros->unique('pieza_id')->count(),
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

        foreach ($registrosGrupo->groupBy($this->clavePiezaProceso(...)) as $registrosPieza) {
            $primero = $registrosPieza->first();
            $pieza = $primero->pieza;
            $marca = $pieza?->marca;

            if ($pieza === null || $marca === null) {
                continue;
            }

            $procesoId = (int) $primero->proceso_id;
            $porcentaje = round((float) $registrosPieza->sum(fn (Registro $r) => (float) $r->porcentaje), 2);
            $kilos = round((float) $marca->peso_unitario * ($porcentaje / 100), 3);

            $grupoPrecioConcepto = $this->grupoPrecioConcepto((int) $marca->id, (int) $marca->obra_id);
            $precioKilo = (float) ($grupoPrecioConcepto?->grupoPrecio?->precioKilo($procesoId) ?? 0);
            $total = round($kilos * $precioKilo, 2);

            $totalKilos += $kilos;
            $totalProduccion += $total;

            $detallesData[] = [
                // Snapshot del renglon: la orden de pago de una semana cerrada
                // no debe cambiar aunque despues se edite o borre el catalogo.
                'concepto_id' => $marca->id,
                'pieza_id' => $pieza->id,
                'qs' => $pieza->qs,
                'obra_id' => $marca->obra_id,
                'marca' => $marca->marca,
                'etapa' => $marca->etapa,
                'proceso_id' => $procesoId,
                'proceso_nombre' => $primero->proceso?->nombre,
                'descripcion' => $marca->descripcion,
                'peso_unitario' => $marca->peso_unitario,
                'longitud' => $marca->longitud,
                'grupo_precio_id' => $grupoPrecioConcepto?->grupo_precio_id ?? 0,
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

        $grupoTrabajo = GrupoTrabajo::with(['empleados.categoria', 'ubicaciones'])->find($grupoTrabajoId);

        if ($grupoTrabajo === null) {
            return;
        }

        $reparto = $this->reparto->calcular($destajo, $grupoTrabajo, $totalFinal);

        foreach ($reparto['empleados'] as $fila) {
            $liquidacion->empleados()->create($fila);
        }
    }

    /**
     * Datos normalizados de la orden de pago, una entrada por grupo de trabajo.
     * Si el destajo esta cerrado se lee de las liquidaciones inmutables; si esta
     * abierto se calcula del preview (los numeros aun pueden cambiar al cerrar).
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
            'liquidaciones.grupoTrabajo.ubicaciones',
            'liquidaciones.detalles',
            'liquidaciones.empleados',
        ]);

        $obras = Obra::query()
            ->whereIn('id', $destajo->liquidaciones->flatMap->detalles->pluck('obra_id')->filter()->unique())
            ->get()
            ->keyBy('id');

        return $destajo->liquidaciones->map(function (Liquidacion $liq) use ($tipos, $pagosPorGrupo, $obras) {
            // Todo sale del snapshot del renglon, nunca del catalogo vivo.
            $piezas = $this->agruparParaImprimir(
                $liq->detalles->map(fn (LiquidacionDetalle $d) => [
                    'marca' => $d->marca ?? '-',
                    'etapa' => $d->etapa,
                    'proceso' => $d->proceso_nombre ?? '-',
                    'obra' => $this->etiquetaObra($d->obra_id !== null ? $obras->get($d->obra_id) : null),
                    'qs' => $d->qs,
                    'porcentaje' => (float) $d->porcentaje,
                    'largo' => $d->longitud,
                    'peso_unitario' => $d->peso_unitario !== null ? (float) $d->peso_unitario : null,
                    'kilos' => (float) $d->kilos,
                    'precio_kilo' => (float) $d->precio_kilo_aplicado,
                    'importe' => (float) $d->total,
                    'descripcion' => $d->descripcion ?? '',
                ])
            );

            // Snapshot del reparto: no se recalcula ni se relee la categoria.
            $empleados = $liq->empleados->map(fn ($e) => [
                'nombre' => $e->nombre,
                'no_empleado' => $e->no_empleado,
                'dias_pagados' => (float) $e->dias_pagados,
                'categoria' => $e->categoria_nombre,
                'categoria_valor' => (int) $e->categoria_valor,
                'salario_diario' => (float) $e->salario_diario,
                'sueldo_base' => (float) $e->sueldo_base,
                'monto_destajo' => (float) $e->monto_destajo,
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

        return $grupoIds->map(function ($grupoId) use ($destajo, $registrosPorGrupo, $pagosPorGrupo, $tipos) {
            $grupoId = (int) $grupoId;
            $registrosGrupo = $registrosPorGrupo->get($grupoId, collect());

            $renglones = [];
            $totalKilos = 0.0;
            $totalProduccion = 0.0;

            foreach ($registrosGrupo->groupBy($this->clavePiezaProceso(...)) as $registrosPieza) {
                $primero = $registrosPieza->first();
                $pieza = $primero->pieza;
                $marca = $pieza?->marca;

                if ($pieza === null || $marca === null) {
                    continue;
                }

                $procesoId = (int) $primero->proceso_id;
                $porcentaje = round((float) $registrosPieza->sum(fn (Registro $r) => (float) $r->porcentaje), 2);
                $kilos = round((float) $marca->peso_unitario * ($porcentaje / 100), 3);
                $precioKilo = $this->precioKilo((int) $marca->id, (int) $marca->obra_id, $procesoId);
                $importe = round($kilos * $precioKilo, 2);

                $totalKilos += $kilos;
                $totalProduccion += $importe;

                $renglones[] = [
                    'marca' => $marca->marca,
                    'etapa' => $marca->etapa,
                    'proceso' => $primero->proceso?->nombre ?? '-',
                    'obra' => $this->etiquetaObra($marca->obra),
                    'qs' => $pieza->qs,
                    'porcentaje' => $porcentaje,
                    'largo' => $marca->longitud,
                    'peso_unitario' => (float) $marca->peso_unitario,
                    'kilos' => $kilos,
                    'precio_kilo' => $precioKilo,
                    'importe' => $importe,
                    'descripcion' => $marca->descripcion,
                ];
            }

            $pagosGrupo = $pagosPorGrupo->get($grupoId, collect());
            $totalExtras = (float) $pagosGrupo->sum(fn (PagoExtra $pe) => $pe->monto);
            $totalFinal = $totalProduccion + $totalExtras;

            $grupoTrabajo = GrupoTrabajo::with(['empleados.categoria', 'ubicaciones'])->find($grupoId);

            // Mismo calculo que al cerrar, para que el preview no mienta.
            $empleados = collect($this->reparto->calcular($destajo, $grupoTrabajo, $totalFinal)['empleados'])
                ->map(fn (array $fila) => [
                    'nombre' => $fila['nombre'],
                    'no_empleado' => $fila['no_empleado'],
                    'dias_pagados' => $fila['dias_pagados'],
                    'categoria' => $fila['categoria_nombre'],
                    'categoria_valor' => $fila['categoria_valor'],
                    'salario_diario' => $fila['salario_diario'],
                    'sueldo_base' => $fila['sueldo_base'],
                    'monto_destajo' => $fila['monto_destajo'],
                    'porcentaje' => $fila['porcentaje'],
                    'monto' => $fila['monto_asignado'],
                ])
                ->all();

            return $this->armarGrupo(
                $grupoTrabajo,
                $this->agruparParaImprimir(collect($renglones)),
                $totalKilos,
                $totalProduccion,
                $totalExtras,
                $totalFinal,
                $empleados,
                $tipos,
                $pagosGrupo,
            );
        })->values();
    }

    /**
     * La orden de pago se imprime por marca, no pieza por pieza: un renglon por
     * (marca, etapa, proceso, porcentaje) con el conteo de QS y sus totales
     * sumados. El detalle por QS sigue guardado en la liquidacion para poder
     * auditar exactamente que se pago.
     *
     * @param  Collection<int, array<string, mixed>>  $renglones
     * @return array<int, array<string, mixed>>
     */
    private function agruparParaImprimir(Collection $renglones): array
    {
        return $renglones
            ->groupBy(fn (array $r) => implode('|', [$r['marca'], $r['etapa'] ?? '', $r['proceso'], $r['porcentaje'], $r['obra']]))
            ->map(function (Collection $grupo) {
                $primero = $grupo->first();

                return [
                    'marca' => $primero['marca'],
                    'etapa' => $primero['etapa'],
                    'proceso' => $primero['proceso'],
                    'descripcion' => $primero['descripcion'],
                    'obra' => $primero['obra'],
                    'pzs' => $grupo->count(),
                    'qs' => $grupo->pluck('qs')->filter()->values()->all(),
                    'porcentaje' => (float) $primero['porcentaje'],
                    'largo' => $primero['largo'],
                    'peso_unitario' => $primero['peso_unitario'],
                    'kilos' => round((float) $grupo->sum('kilos'), 3),
                    'precio_kilo' => (float) $primero['precio_kilo'],
                    'importe' => round((float) $grupo->sum('importe'), 2),
                ];
            })
            ->values()
            ->all();
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
                'ubicaciones' => $grupo?->ubicaciones->pluck('nombre')->implode(' · ') ?: null,
            ],
            // Resumen de la hoja de reparto: bases garantizadas y excedente.
            'total_bases' => round((float) collect($empleados)->sum('sueldo_base'), 2),
            'total_destajo_repartido' => round((float) collect($empleados)->sum('monto_destajo'), 2),
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
            ->with(['pieza.marca.obra', 'proceso', 'grupoTrabajo.empleados'])
            ->whereBetween('fecha', [$destajo->fecha_inicio, $destajo->fecha_fin])
            ->get();
    }

    /**
     * El precio se resuelve por marca, pero se pregunta una vez por pieza: una
     * semana de 500 QS serían 500 consultas iguales. Se cachea por corrida.
     *
     * @var array<string, GrupoPrecioConcepto|null>
     */
    private array $preciosPorMarca = [];

    private function grupoPrecioConcepto(int $conceptoId, int $obraId): ?GrupoPrecioConcepto
    {
        return $this->preciosPorMarca[$conceptoId.'|'.$obraId] ??= GrupoPrecioConcepto::query()
            ->whereHas('grupoPrecio', fn ($q) => $q->where('obra_id', $obraId))
            ->where('concepto_id', $conceptoId)
            ->with('grupoPrecio.precios')
            ->first();
    }

    private function precioKilo(int $conceptoId, int $obraId, int $procesoId): float
    {
        return (float) ($this->grupoPrecioConcepto($conceptoId, $obraId)?->grupoPrecio?->precioKilo($procesoId) ?? 0);
    }
}
