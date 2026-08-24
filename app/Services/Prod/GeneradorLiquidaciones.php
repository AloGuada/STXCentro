<?php

namespace App\Services\Prod;

use App\Models\Concepto;
use App\Models\Obra;
use App\Models\Prod\Destajo;
use App\Models\Prod\GrupoPrecioSubproceso;
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
    public function __construct(
        private RepartoDelGrupo $reparto,
        private ModalidadDePago $modalidad,
    ) {}

    /**
     * Cierra el destajo generando una liquidacion inmutable por grupo de trabajo.
     *
     * Por grupo: agrupa la produccion por pieza, proceso y subproceso, valora
     * cada renglon segun la modalidad de su grupo de precios (por kilo: peso x
     * porcentaje x $/kg; por subproceso: precio fijo x porcentaje), suma los
     * pagos extra y reparte el total entre los empleados con RepartoDelGrupo.
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
     * Clave de agrupacion del renglon liquidado: una pieza en un proceso y, si
     * el grupo paga por pasos, en un subproceso. Si la misma pieza se captura
     * dos veces en la semana (dos parcialidades), los porcentajes se suman en un
     * solo renglon.
     *
     * El subproceso entra en la clave porque cada paso se cobra aparte: armar y
     * puntear la misma pieza son dos renglones, no uno al 200%.
     */
    private function claveDelRenglon(Registro $registro): string
    {
        return $registro->pieza_id.'|'.$registro->proceso_id.'|'.($registro->subproceso_id ?? 0);
    }

    /**
     * Marcas con produccion en el destajo que no tienen tarifa para el paso en
     * que se trabajaron. Se pagarian en cero silenciosamente; se usa para
     * advertir antes de cerrar.
     *
     * Se agrupa tambien por subproceso: un grupo puede tener capturado el precio
     * de "Armado" y faltarle el de "Punteado", y avisar solo del proceso
     * mandaria a revisar una tarifa que si esta.
     *
     * @return Collection<int, array{concepto_id: int, marca: string, lote: ?string, proceso: string, subproceso: ?string, piezas: int}>
     */
    public function piezasSinPrecio(Destajo $destajo): Collection
    {
        return $this->registrosDelDestajo($destajo)
            ->filter(fn (Registro $r) => $r->pieza?->marca !== null)
            ->groupBy(fn (Registro $r) => $r->pieza->concepto_id.'|'.$r->proceso_id.'|'.($r->subproceso_id ?? 0))
            ->map(function (Collection $registros) {
                $primero = $registros->first();
                $marca = $primero->pieza->marca;

                if ($this->precioDelRenglon($marca, (int) $primero->proceso_id, $primero->subproceso) > 0) {
                    return null;
                }

                return [
                    'concepto_id' => (int) $marca->id,
                    'marca' => $marca->marca,
                    'lote' => $marca->lote,
                    'proceso' => $primero->proceso?->nombre ?? '',
                    'subproceso' => $primero->subproceso?->nombre,
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

        foreach ($registrosGrupo->groupBy($this->claveDelRenglon(...)) as $registrosPieza) {
            $primero = $registrosPieza->first();
            $pieza = $primero->pieza;
            $marca = $pieza?->marca;

            if ($pieza === null || $marca === null) {
                continue;
            }

            $procesoId = (int) $primero->proceso_id;
            $porcentaje = round((float) $registrosPieza->sum(fn (Registro $r) => (float) $r->porcentaje), 2);
            $valor = $this->valorar($marca, $procesoId, $primero->subproceso, $porcentaje);

            $totalKilos += $valor['kilos'];
            $totalProduccion += $valor['total'];

            $detallesData[] = [
                // Snapshot del renglon: la orden de pago de una semana cerrada
                // no debe cambiar aunque despues se edite o borre el catalogo.
                'concepto_id' => $marca->id,
                'pieza_id' => $pieza->id,
                'qr' => $pieza->qr,
                'qs' => $pieza->qs,
                'obra_id' => $marca->obra_id,
                'marca' => $marca->marca,
                'lote' => $marca->lote,
                'proceso_id' => $procesoId,
                'proceso_nombre' => $primero->proceso?->nombre,
                'subproceso_id' => $primero->subproceso_id,
                'subproceso_nombre' => $primero->subproceso?->nombre,
                'descripcion' => $marca->descripcion,
                'peso_unitario' => $marca->peso_unitario,
                'longitud' => $marca->longitud,
                'grupo_precio_id' => $valor['grupo_precio_id'],
                'porcentaje' => $porcentaje,
                'kilos' => $valor['kilos'],
                'precio_kilo_aplicado' => $valor['precio_kilo'],
                'precio_subproceso_aplicado' => $valor['precio_subproceso'],
                'total' => $valor['total'],
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
                    'lote' => $d->lote,
                    'proceso' => $d->proceso_nombre ?? '-',
                    'subproceso' => $d->subproceso_nombre,
                    'obra' => $this->etiquetaObra($d->obra_id !== null ? $obras->get($d->obra_id) : null),
                    'qs' => $d->qs,
                    'porcentaje' => (float) $d->porcentaje,
                    'largo' => $d->longitud,
                    'peso_unitario' => $d->peso_unitario !== null ? (float) $d->peso_unitario : null,
                    'kilos' => (float) $d->kilos,
                    'precio_unitario' => (float) ($d->precio_subproceso_aplicado ?? $d->precio_kilo_aplicado ?? 0),
                    'unidad' => $d->subproceso_nombre !== null ? 'pza' : 'kg',
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

            foreach ($registrosGrupo->groupBy($this->claveDelRenglon(...)) as $registrosPieza) {
                $primero = $registrosPieza->first();
                $pieza = $primero->pieza;
                $marca = $pieza?->marca;

                if ($pieza === null || $marca === null) {
                    continue;
                }

                $procesoId = (int) $primero->proceso_id;
                $porcentaje = round((float) $registrosPieza->sum(fn (Registro $r) => (float) $r->porcentaje), 2);
                $subproceso = $primero->subproceso;
                $valor = $this->valorar($marca, $procesoId, $subproceso, $porcentaje);

                $totalKilos += $valor['kilos'];
                $totalProduccion += $valor['total'];

                $renglones[] = [
                    'marca' => $marca->marca,
                    'lote' => $marca->lote,
                    'proceso' => $primero->proceso?->nombre ?? '-',
                    'subproceso' => $subproceso?->nombre,
                    'obra' => $this->etiquetaObra($marca->obra),
                    'qs' => $pieza->qs,
                    'porcentaje' => $porcentaje,
                    'largo' => $marca->longitud,
                    'peso_unitario' => (float) $marca->peso_unitario,
                    'kilos' => $valor['kilos'],
                    'precio_unitario' => (float) ($valor['precio_subproceso'] ?? $valor['precio_kilo'] ?? 0),
                    'unidad' => $valor['precio_subproceso'] !== null ? 'pza' : 'kg',
                    'importe' => $valor['total'],
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
     * (marca, lote, proceso, subproceso, porcentaje) con el conteo de QS y sus
     * totales sumados. El detalle por QS sigue guardado en la liquidacion para
     * poder auditar exactamente que se pago.
     *
     * El subproceso entra en la clave porque cada paso lleva su propio precio:
     * juntarlos sumaria importes de tarifas distintas bajo un solo $/pza.
     *
     * @param  Collection<int, array<string, mixed>>  $renglones
     * @return array<int, array<string, mixed>>
     */
    private function agruparParaImprimir(Collection $renglones): array
    {
        return $renglones
            ->groupBy(fn (array $r) => implode('|', [$r['marca'], $r['lote'] ?? '', $r['proceso'], $r['subproceso'] ?? '', $r['porcentaje'], $r['obra']]))
            ->map(function (Collection $grupo) {
                $primero = $grupo->first();

                return [
                    'marca' => $primero['marca'],
                    'lote' => $primero['lote'],
                    'proceso' => $primero['proceso'],
                    'subproceso' => $primero['subproceso'] ?? null,
                    'descripcion' => $primero['descripcion'],
                    'obra' => $primero['obra'],
                    'pzs' => $grupo->count(),
                    'qs' => $grupo->pluck('qs')->filter()->values()->all(),
                    'porcentaje' => (float) $primero['porcentaje'],
                    'largo' => $primero['largo'],
                    'peso_unitario' => $primero['peso_unitario'],
                    'kilos' => round((float) $grupo->sum('kilos'), 3),
                    'precio_unitario' => (float) $primero['precio_unitario'],
                    'unidad' => $primero['unidad'],
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
            ->with(['pieza.marca.obra', 'proceso', 'subproceso', 'grupoTrabajo.empleados'])
            ->whereBetween('fecha', [$destajo->fecha_inicio, $destajo->fecha_fin])
            ->get();
    }

    /**
     * El importe de un renglon segun la modalidad de su grupo de precios.
     *
     * Por kilo paga el peso: peso unitario x porcentaje x $/kg.
     * Por subproceso paga el paso: precio fijo de la pieza x porcentaje, y los
     * **kilos van en cero**. Si contaran, una pieza de 100 kg con tres pasos
     * sumaria 300 kg al total de la liquidacion; el peso sigue guardado en el
     * snapshot del renglon, pero no se acumula.
     *
     * @return array{kilos: float, precio_kilo: ?float, precio_subproceso: ?float, total: float, grupo_precio_id: int}
     */
    private function valorar(Concepto $marca, int $procesoId, ?GrupoPrecioSubproceso $subproceso, float $porcentaje): array
    {
        $conceptoId = (int) $marca->id;
        $obraId = (int) $marca->obra_id;
        $grupo = $this->modalidad->grupo($conceptoId, $obraId);
        $fraccion = $porcentaje / 100;

        if ($grupo?->pagaPorSubproceso() && $subproceso !== null) {
            $precio = $this->modalidad->precioSubproceso($conceptoId, $obraId, (int) $subproceso->id);

            return [
                'kilos' => 0.0,
                'precio_kilo' => null,
                'precio_subproceso' => $precio,
                'total' => round($precio * $fraccion, 2),
                'grupo_precio_id' => (int) $grupo->id,
            ];
        }

        $kilos = round((float) $marca->peso_unitario * $fraccion, 3);
        $precioKilo = $this->modalidad->precioKilo($conceptoId, $obraId, $procesoId);

        return [
            'kilos' => $kilos,
            'precio_kilo' => $precioKilo,
            'precio_subproceso' => null,
            'total' => round($kilos * $precioKilo, 2),
            'grupo_precio_id' => (int) ($grupo?->id ?? 0),
        ];
    }

    /**
     * El precio se resuelve por marca, pero se pregunta una vez por pieza: una
     * semana de 500 QS serian 500 consultas iguales. ModalidadDePago lo cachea.
     */
    private function precioDelRenglon(Concepto $marca, int $procesoId, ?GrupoPrecioSubproceso $subproceso): float
    {
        return $subproceso !== null && $this->modalidad->pagaPorSubproceso((int) $marca->id, (int) $marca->obra_id)
            ? $this->modalidad->precioSubproceso((int) $marca->id, (int) $marca->obra_id, (int) $subproceso->id)
            : $this->modalidad->precioKilo((int) $marca->id, (int) $marca->obra_id, $procesoId);
    }
}
