<?php

namespace App\Http\Controllers\Admin\Cotiz;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Cotiz\SeccionMontajeStoreRequest;
use App\Http\Requests\Admin\Cotiz\SeccionMontajeUpdateRequest;
use App\Http\Requests\Admin\Cotiz\SeccionPersonalUpsertRequest;
use App\Http\Requests\Admin\Cotiz\SeccionRendimientoStoreRequest;
use App\Http\Requests\Admin\Cotiz\SeccionRendimientoUpdateRequest;
use App\Models\Cotiz\FaseMontaje;
use App\Models\Cotiz\Obra;
use App\Models\Cotiz\PersonalCategoria;
use App\Models\Cotiz\SeccionFaseRendimiento;
use App\Models\Cotiz\SeccionMontaje;
use App\Models\Cotiz\SeccionPersonal;
use App\Services\Cotiz\MontajeDerivations;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Secciones/zonas de montaje de una obra (Fase 4): alta/edición/borrado + el detalle
 * editable de una zona (matriz personal × fase y renglones de rendimiento por fase).
 */
class SeccionMontajeController extends Controller
{
    public function store(SeccionMontajeStoreRequest $request, Obra $obra): RedirectResponse
    {
        $seccion = $obra->seccionesMontaje()->create([
            'nombre' => $request->input('nombre', 'Nueva zona'),
            'area_m2' => $request->input('area_m2'),
            'orden' => $request->integer('orden', $obra->seccionesMontaje()->count() + 1),
        ]);

        return to_route('admin.cotiz.secciones.edit', $seccion);
    }

    public function edit(SeccionMontaje $seccion, MontajeDerivations $montaje): Response
    {
        $seccion->load(['obra:id,nombre,factor_contratista', 'personal', 'rendimientos.fase:id,nombre,unidad,orden']);

        $factor = (float) $seccion->obra->factor_contratista;
        $importe = $montaje->importeSeccion($seccion, $factor);
        $importePorFase = $montaje->importePorFase($seccion);

        $categorias = PersonalCategoria::query()->orderBy('orden')->orderBy('codigo')->get(['id', 'codigo', 'nombre', 'sueldo_semanal', 'orden']);
        $fases = FaseMontaje::query()->orderBy('orden')->orderBy('codigo')->get(['id', 'codigo', 'nombre', 'unidad', 'orden']);

        $rendimientos = $seccion->rendimientos
            ->sortBy([['fase.orden', 'asc'], ['id', 'asc']])
            ->values()
            ->map(fn (SeccionFaseRendimiento $r) => [
                'id' => $r->id,
                'fase_id' => $r->fase_id,
                'fase_nombre' => $r->fase?->nombre,
                'fase_unidad' => $r->fase?->unidad,
                'concepto' => $r->concepto,
                'largo_pza' => $r->largo_pza,
                'cantidad' => (float) $r->cantidad,
                'rendimiento' => (float) $r->rendimiento,
                'total_dias' => (float) $r->rendimiento > 0 ? (float) $r->cantidad / (float) $r->rendimiento : 0.0,
            ]);

        return Inertia::render('admin/cotiz/analisis-mo/seccion-edit', [
            'seccion' => [
                'id' => $seccion->id,
                'obra_id' => $seccion->obra_id,
                'nombre' => $seccion->nombre,
                'area_m2' => $seccion->area_m2 !== null ? (float) $seccion->area_m2 : null,
                'importe_directo' => $importe['importe_directo'],
                'importe_total' => $importe['importe_total'],
            ],
            'categorias' => $categorias,
            'fases' => $fases,
            'celdas' => $seccion->personal->map(fn (SeccionPersonal $p) => [
                'fase_id' => $p->fase_id,
                'categoria_id' => $p->categoria_id,
                'cantidad' => (int) $p->cantidad,
            ]),
            'resumenPorFase' => $importePorFase,
            'rendimientos' => $rendimientos,
        ]);
    }

    public function update(SeccionMontajeUpdateRequest $request, SeccionMontaje $seccion): RedirectResponse
    {
        $seccion->update($request->validated());

        return back();
    }

    public function destroy(SeccionMontaje $seccion): RedirectResponse
    {
        $obraId = $seccion->obra_id;
        $seccion->delete();

        return to_route('admin.cotiz.analisis-mo.index', $obraId);
    }

    public function personalUpsert(SeccionPersonalUpsertRequest $request, SeccionMontaje $seccion): RedirectResponse
    {
        $data = $request->validated();

        SeccionPersonal::query()->updateOrCreate(
            [
                'seccion_id' => $seccion->id,
                'fase_id' => $data['fase_id'],
                'categoria_id' => $data['categoria_id'],
            ],
            ['cantidad' => $data['cantidad']],
        );

        return back();
    }

    public function rendimientoStore(SeccionRendimientoStoreRequest $request, SeccionMontaje $seccion): RedirectResponse
    {
        $seccion->rendimientos()->create([
            'fase_id' => $request->integer('fase_id'),
            'concepto' => $request->input('concepto', 'Nuevo renglón'),
            'cantidad' => 0,
            'rendimiento' => 1,
        ]);

        return back();
    }

    public function rendimientoUpdate(SeccionRendimientoUpdateRequest $request, SeccionFaseRendimiento $rendimiento): RedirectResponse
    {
        $rendimiento->update($request->validated());

        return back();
    }

    public function rendimientoDestroy(SeccionFaseRendimiento $rendimiento): RedirectResponse
    {
        $rendimiento->delete();

        return back();
    }
}
