<?php

namespace App\Http\Controllers\Admin\Cotiz;

use App\Enums\Cotiz\GrupoFlete;
use App\Enums\Cotiz\MetodoFleteEstandar;
use App\Http\Controllers\Controller;
use App\Models\Cotiz\FaseMontaje;
use App\Models\Cotiz\FleteViaticoCatalogo;
use App\Models\Cotiz\Obra;
use App\Models\Cotiz\ObraFleteEstandar;
use App\Models\Cotiz\ObraFleteViatico;
use App\Models\Cotiz\PersonalCategoria;
use App\Models\Cotiz\SeccionMontaje;
use App\Services\Cotiz\CuadrillaGlobalDerivations;
use App\Services\Cotiz\FleteEstandarDerivations;
use App\Services\Cotiz\FletesViaticosCalculator;
use App\Services\Cotiz\FletesViaticosSubtotales;
use App\Services\Cotiz\MontajeDerivations;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Análisis de MO/Montaje de una obra (Fase 4): página única con 4 pestañas —
 * zonas/secciones, montaje global (cuadrilla), fletes y viáticos, fletes estándar.
 *
 * Todos los derivados se calculan en PHP (servicios de derivación que reemplazan las
 * vistas SQL de prepsim). Las fórmulas de Fletes y Viáticos se recalculan al entrar.
 */
class AnalisisMoController extends Controller
{
    public function index(
        Obra $obra,
        MontajeDerivations $montaje,
        CuadrillaGlobalDerivations $cuadrilla,
        FleteEstandarDerivations $fleteEstandar,
        FletesViaticosCalculator $fvCalculator,
        FletesViaticosSubtotales $fvSubtotales,
    ): Response {
        $factor = (float) $obra->factor_contratista;

        // Recalcular fórmulas de fletes/viáticos antes de leer subtotales (server autoritativo).
        $fvCalculator->recalcular($obra);

        $obra->loadMissing([
            'seccionesMontaje.rendimientos',
            'seccionesMontaje.personal.categoria',
        ]);

        $secciones = $obra->seccionesMontaje
            ->sortBy([['orden', 'asc'], ['id', 'asc']])
            ->values()
            ->map(fn (SeccionMontaje $s) => $this->mapSeccion($s, $montaje, $factor));

        // Cuadrilla global: LEFT JOIN del catálogo de personal con las celdas de la obra.
        $cuadrillaCells = $obra->cuadrillaGlobal()->get()->keyBy('categoria_id');
        $categoriasCuadrilla = PersonalCategoria::query()
            ->orderBy('orden')
            ->orderBy('codigo')
            ->get()
            ->map(fn (PersonalCategoria $c) => [
                'categoria_id' => $c->id,
                'codigo' => $c->codigo,
                'nombre' => $c->nombre,
                'sueldo_semanal' => (float) $c->sueldo_semanal,
                'orden' => $c->orden,
                'cantidad_por_grupo' => (int) ($cuadrillaCells->get($c->id)?->cantidad_por_grupo ?? 0),
            ]);

        // Fletes y viáticos (ya recalculados).
        $itemsFv = $obra->fletesViaticos()->orderByRaw($this->ordenGruposSql())->orderBy('orden')->orderBy('id')->get();
        $subtotales = $fvSubtotales->calcular($obra);

        // Fletes estándar con volumen/camiones derivados.
        $obra->loadMissing(['fletesEstandar.tarjeta:id,descripcion']);
        $fletesEstandar = $obra->fletesEstandar
            ->sortBy([['grupo', 'asc'], ['orden', 'asc'], ['id', 'asc']])
            ->values()
            ->map(function (ObraFleteEstandar $f) use ($fleteEstandar) {
                $d = $fleteEstandar->derivar($f);

                return [
                    'id' => $f->id,
                    'tarjeta_id' => $f->tarjeta_id,
                    'tarjeta_nombre' => $f->tarjeta?->descripcion,
                    'metodo' => $f->metodo->value,
                    'grupo' => $f->grupo,
                    'volumen_override' => $f->volumen_override !== null ? (float) $f->volumen_override : null,
                    'volumen' => $d['volumen'],
                    'pzas' => $d['pzas'],
                    'camiones' => $d['camiones'],
                    'kg_por_camion' => (float) $f->kg_por_camion,
                    'pzas_por_camion' => (int) $f->pzas_por_camion,
                    'ml_por_pza' => (float) $f->ml_por_pza,
                    'orden' => $f->orden,
                ];
            });

        $tarjetasUsadas = $obra->fletesEstandar->pluck('tarjeta_id')->all();

        return Inertia::render('admin/cotiz/analisis-mo/index', [
            'obra' => $obra->only(['id', 'nombre', 'op', 'factor_contratista', 'num_grupos']),
            'secciones' => $secciones,
            'cuadrilla' => [
                'categorias' => $categoriasCuadrilla,
                'totales' => $cuadrilla->totales($obra),
            ],
            'fletesViaticos' => [
                'items' => $itemsFv->map(fn (ObraFleteViatico $i) => [
                    'id' => $i->id,
                    'grupo' => $i->grupo?->value,
                    'orden' => $i->orden,
                    'concepto' => $i->concepto,
                    'unidad' => $i->unidad,
                    'cantidad' => (float) $i->cantidad,
                    'p_unit' => (float) $i->p_unit,
                    'importe' => (float) $i->cantidad * (float) $i->p_unit,
                    'clave' => $i->clave,
                    'formula_cantidad' => $i->formula_cantidad,
                    'formula_p_unit' => $i->formula_p_unit,
                ]),
                'subtotales' => $subtotales['subtotales'],
                'total' => $subtotales['total'],
                'variables' => $fvCalculator->construirContexto($obra),
            ],
            'fletesEstandar' => $fletesEstandar,
            'catalogos' => [
                'fasesMontaje' => FaseMontaje::query()->orderBy('orden')->orderBy('codigo')->get(['id', 'codigo', 'nombre', 'unidad']),
                'fletesCatalogo' => FleteViaticoCatalogo::query()->orderBy('grupo')->orderBy('orden')->get(),
                'tarjetas' => $obra->tarjetas()
                    ->whereNotIn('id', $tarjetasUsadas)
                    ->orderBy('id')
                    ->get(['id', 'descripcion']),
                'metodos' => MetodoFleteEstandar::options(),
                'grupos' => GrupoFlete::options(),
            ],
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function mapSeccion(SeccionMontaje $seccion, MontajeDerivations $montaje, float $factor): array
    {
        $importe = $montaje->importeSeccion($seccion, $factor);

        return [
            'id' => $seccion->id,
            'nombre' => $seccion->nombre,
            'area_m2' => $seccion->area_m2 !== null ? (float) $seccion->area_m2 : null,
            'orden' => $seccion->orden,
            'importe_directo' => $importe['importe_directo'],
            'importe_total' => $importe['importe_total'],
            'semanas' => $montaje->semanasSeccion($seccion),
        ];
    }

    /**
     * Orden de los 9 grupos de fletes y viáticos como en el Excel.
     */
    private function ordenGruposSql(): string
    {
        return "CASE grupo
            WHEN 'VIATICOS' THEN 1 WHEN 'SUPERV_MONTAJE' THEN 2 WHEN 'ENERGIA' THEN 3
            WHEN 'VARIOS' THEN 4 WHEN 'FLETES' THEN 5 WHEN 'GRUAS' THEN 6
            WHEN 'PLATAFORMAS' THEN 7 WHEN 'LABORATORIO' THEN 8 WHEN 'TOPOGRAFIA' THEN 9
            ELSE 99 END";
    }
}
