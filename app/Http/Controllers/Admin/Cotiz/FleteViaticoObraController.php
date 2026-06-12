<?php

namespace App\Http\Controllers\Admin\Cotiz;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Cotiz\ObraFleteViaticoStoreRequest;
use App\Http\Requests\Admin\Cotiz\ObraFleteViaticoUpdateRequest;
use App\Models\Cotiz\FleteViaticoCatalogo;
use App\Models\Cotiz\Obra;
use App\Models\Cotiz\ObraFleteViatico;
use App\Services\Cotiz\FletesViaticosCalculator;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;

/**
 * Fletes y Viáticos de una obra (Fase 4, M043): items con fórmulas que el motor
 * `FletesViaticosCalculator` recalcula y persiste tras cada cambio relevante.
 */
class FleteViaticoObraController extends Controller
{
    public function __construct(private readonly FletesViaticosCalculator $calculator) {}

    public function store(ObraFleteViaticoStoreRequest $request, Obra $obra): RedirectResponse
    {
        $grupo = $request->validated('grupo');
        $maxOrden = (int) $obra->fletesViaticos()->where('grupo', $grupo)->max('orden');

        $obra->fletesViaticos()->create([
            'grupo' => $grupo,
            'orden' => $request->integer('orden', $maxOrden + 1),
            'concepto' => $request->input('concepto', 'Nuevo item'),
            'cantidad' => 0,
            'p_unit' => 0,
        ]);

        return back();
    }

    public function update(ObraFleteViaticoUpdateRequest $request, ObraFleteViatico $fleteViatico): RedirectResponse
    {
        $data = $this->normalizar($request->validated());

        if (array_key_exists('clave', $data)) {
            $data['clave'] = $this->sanitizarClave($data['clave']);
        }

        $fleteViatico->update($data);
        $this->calculator->recalcular($fleteViatico->obra);

        return back();
    }

    public function destroy(ObraFleteViatico $fleteViatico): RedirectResponse
    {
        $fleteViatico->delete();

        return back();
    }

    /**
     * Copia todos los items del catálogo global al final de los de la obra (no borra nada).
     */
    public function importarPlantilla(Obra $obra): RedirectResponse
    {
        $catalogo = FleteViaticoCatalogo::query()->orderBy('grupo')->orderBy('orden')->orderBy('id')->get();

        DB::transaction(function () use ($obra, $catalogo) {
            foreach ($catalogo as $tpl) {
                $obra->fletesViaticos()->create([
                    'grupo' => $tpl->grupo,
                    'orden' => $tpl->orden,
                    'concepto' => $tpl->concepto,
                    'unidad' => $tpl->unidad,
                    'cantidad' => 0,
                    'p_unit' => $tpl->p_unit_default,
                    'notas' => $tpl->notas,
                    'clave' => $tpl->clave,
                    'formula_cantidad' => $tpl->formula_cantidad,
                    'formula_p_unit' => $tpl->formula_p_unit,
                ]);
            }
        });

        $this->calculator->recalcular($obra);

        return back();
    }

    /**
     * Copia clave + fórmulas del catálogo a los items de la obra que coincidan por concepto.
     */
    public function aplicarFormulas(Obra $obra): RedirectResponse
    {
        $catalogo = FleteViaticoCatalogo::query()
            ->where(fn ($q) => $q->whereNotNull('formula_cantidad')->orWhereNotNull('formula_p_unit')->orWhereNotNull('clave'))
            ->get();

        $items = $obra->fletesViaticos()->get();

        DB::transaction(function () use ($catalogo, $items) {
            foreach ($catalogo as $tpl) {
                $destino = $items->filter(
                    fn (ObraFleteViatico $i) => mb_strtolower(trim($i->concepto)) === mb_strtolower(trim($tpl->concepto)),
                );
                foreach ($destino as $item) {
                    $item->update([
                        'clave' => $tpl->clave,
                        'formula_cantidad' => $tpl->formula_cantidad,
                        'formula_p_unit' => $tpl->formula_p_unit,
                    ]);
                }
            }
        });

        $this->calculator->recalcular($obra);

        return back();
    }

    public function recalcular(Obra $obra): RedirectResponse
    {
        $this->calculator->recalcular($obra);

        return back();
    }

    /**
     * Convierte cadenas vacías en NULL (unidad/notas/clave/fórmulas "limpiadas").
     *
     * @param  array<string, mixed>  $datos
     * @return array<string, mixed>
     */
    private function normalizar(array $datos): array
    {
        return array_map(fn ($valor) => ($valor === '' ? null : $valor), $datos);
    }

    private function sanitizarClave(?string $clave): ?string
    {
        if ($clave === null || trim($clave) === '') {
            return null;
        }

        $limpia = preg_replace('/[^A-Z0-9_]+/', '_', strtoupper(trim($clave))) ?? '';

        return trim($limpia, '_') ?: null;
    }
}
