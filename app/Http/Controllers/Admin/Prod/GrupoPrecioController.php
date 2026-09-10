<?php

namespace App\Http\Controllers\Admin\Prod;

use App\Enums\Prod\TipoPago;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Prod\GrupoPrecioStoreRequest;
use App\Http\Requests\Admin\Prod\GrupoPrecioUpdateRequest;
use App\Models\Concepto;
use App\Models\Obra;
use App\Models\Prod\GrupoPrecio;
use App\Models\Prod\GrupoPrecioConcepto;
use App\Models\Prod\GrupoPrecioProceso;
use App\Models\Prod\GrupoPrecioSubproceso;
use App\Models\Prod\LiquidacionDetalle;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class GrupoPrecioController extends Controller
{
    public function index(Request $request): Response
    {
        // Sólo obras con catálogo: sin marcas no hay nada a qué ponerle precio.
        $obras = Obra::query()
            ->sinPlanta()
            ->whereHas('catalogos', fn ($q) => $q->where('vigente', true))
            ->withCount([
                'conceptos as conceptos_count' => fn ($q) => $q->deCatalogoVigente()->where('activo', true),
                'conceptos as conceptos_sin_precio_count' => fn ($q) => $q->deCatalogoVigente()->where('activo', true)->whereDoesntHave('grupoPrecioConceptos'),
            ])
            ->when($request->search, fn ($q, $s) => $q->where(fn ($q) => $q->where('no', 'like', "%{$s}%")
                ->orWhere('descripcion', 'like', "%{$s}%")))
            ->orderBy('no')
            ->paginate(15)
            ->withQueryString();

        return Inertia::render('admin/prod/grupo-precios/index', [
            'obras' => $obras,
            'filters' => $request->only(['search']),
        ]);
    }

    public function create(Request $request): Response
    {
        // Si se llega desde una obra, se da por dada y no se vuelve a elegir.
        $obra = $request->obra_id
            ? Obra::sinPlanta()->find($request->obra_id)
            : null;

        return Inertia::render('admin/prod/grupo-precios/create', [
            'obra' => $obra,
            'obras' => $obra ? [] : Obra::sinPlanta()
                ->whereHas('catalogos', fn ($q) => $q->where('vigente', true))
                ->orderBy('no')
                ->get(['id', 'no', 'descripcion']),
            'procesos' => $this->procesosDeObra($obra),
            'tiposPago' => TipoPago::opciones(),
        ]);
    }

    public function store(GrupoPrecioStoreRequest $request): RedirectResponse
    {
        $grupoPrecio = GrupoPrecio::create([
            'obra_id' => $request->obra_id,
            'descripcion' => $request->descripcion,
            'tipo_pago' => $request->tipo_pago,
        ]);

        $this->guardarPrecios($grupoPrecio, $request);

        return to_route('admin.prod.grupo-precios.show-by-obra', $request->obra_id);
    }

    public function showByObra(Obra $obra): Response
    {
        abort_if($obra->es_planta, 404);

        $grupoPrecios = GrupoPrecio::query()
            ->where('obra_id', $obra->id)
            ->with(['grupoPrecioConceptos.concepto', 'precios.proceso', 'subprocesos.proceso'])
            ->withCount('grupoPrecioConceptos')
            ->orderBy('descripcion')
            ->get();

        $unassignedConceptos = Concepto::query()
            ->where('obra_id', $obra->id)
            ->deCatalogoVigente()
            ->where('activo', true)
            ->whereDoesntHave('grupoPrecioConceptos')
            ->orderBy('marca')
            ->get();

        return Inertia::render('admin/prod/grupo-precios/show', [
            'obra' => $obra,
            'grupoPrecios' => $grupoPrecios,
            'unassignedConceptos' => $unassignedConceptos,
            'procesos' => $this->procesosDeObra($obra),
            'tiposPago' => TipoPago::opciones(),
        ]);
    }

    public function edit(GrupoPrecio $grupoPrecio): Response
    {
        $grupoPrecio->load(['grupoPrecioConceptos.concepto.obra', 'precios', 'subprocesos', 'obra']);

        return Inertia::render('admin/prod/grupo-precios/edit', [
            'grupoPrecio' => $grupoPrecio,
            'obras' => Obra::sinPlanta()
                ->whereHas('catalogos', fn ($q) => $q->where('vigente', true))
                ->with(['conceptos' => fn ($q) => $q->deCatalogoVigente()->where('activo', true)->orderBy('marca')])
                ->orderBy('no')
                ->get(),
            'procesos' => $this->procesosDeObra($grupoPrecio->obra),
            'tiposPago' => TipoPago::opciones(),
            // Una vez liquidado, el renglon pagado y el nuevo no son comparables.
            'puedeCambiarModalidad' => ! $this->yaSeLiquido($grupoPrecio),
        ]);
    }

    public function assignConceptos(Request $request, GrupoPrecio $grupoPrecio): RedirectResponse
    {
        $request->validate([
            'concepto_ids' => ['required', 'array', 'min:1'],
            'concepto_ids.*' => ['exists:conceptos,id'],
        ]);

        foreach ($request->concepto_ids as $conceptoId) {
            GrupoPrecioConcepto::firstOrCreate([
                'concepto_id' => $conceptoId,
                'grupo_precio_id' => $grupoPrecio->id,
            ]);
        }

        return back()->with('success', 'Conceptos asignados correctamente.');
    }

    public function update(GrupoPrecioUpdateRequest $request, GrupoPrecio $grupoPrecio): RedirectResponse
    {
        $nuevaModalidad = TipoPago::from($request->string('tipo_pago')->value());

        // Cambiar de modalidad con semanas ya pagadas dejaria la liquidacion
        // vieja valorada con una regla que el grupo ya no tiene, y el avance de
        // las piezas mezclando topes por proceso con topes por paso.
        if ($nuevaModalidad !== $grupoPrecio->tipo_pago && $this->yaSeLiquido($grupoPrecio)) {
            return back()->withErrors([
                'tipo_pago' => 'Este grupo ya tiene produccion liquidada; no se puede cambiar su forma de pago. Crea un grupo nuevo y reasigna las marcas.',
            ]);
        }

        $grupoPrecio->update([
            'obra_id' => $request->obra_id,
            'descripcion' => $request->descripcion,
            'tipo_pago' => $nuevaModalidad,
        ]);

        $this->guardarPrecios($grupoPrecio->refresh(), $request);

        return to_route('admin.prod.grupo-precios.show-by-obra', $grupoPrecio->obra_id);
    }

    /** Hay al menos un renglon pagado que cito a este grupo de precios. */
    private function yaSeLiquido(GrupoPrecio $grupoPrecio): bool
    {
        return LiquidacionDetalle::query()->where('grupo_precio_id', $grupoPrecio->id)->exists();
    }

    /**
     * Los precios que aplican segun la modalidad. Guardar los dos lados dejaria
     * tarifas fantasma que nadie ve pero que reviven si el grupo cambia.
     */
    private function guardarPrecios(GrupoPrecio $grupoPrecio, GrupoPrecioStoreRequest|GrupoPrecioUpdateRequest $request): void
    {
        if ($grupoPrecio->pagaPorSubproceso()) {
            $this->guardarSubprocesos($grupoPrecio, $request->input('subprocesos', []));

            return;
        }

        $this->guardarTarifas($grupoPrecio, $request->input('precios', []));
    }

    /**
     * Sincroniza los pasos del grupo: los del formulario se crean o actualizan y
     * los que ya no vienen se apagan si tienen produccion capturada, o se borran
     * si nunca se usaron.
     *
     * No se borra lo que tiene registros porque el subproceso es quien pone el
     * precio: sin el, el renglon capturado se queda sin importe.
     *
     * @param  array<int, array<string, mixed>>  $subprocesos
     */
    private function guardarSubprocesos(GrupoPrecio $grupoPrecio, array $subprocesos): void
    {
        $permitidos = $this->procesosDeObra($grupoPrecio->obra)->pluck('id');
        $vigentes = [];

        foreach ($subprocesos as $orden => $subproceso) {
            $procesoId = (int) ($subproceso['proceso_id'] ?? 0);

            if (! $permitidos->contains($procesoId)) {
                continue;
            }

            $fila = GrupoPrecioSubproceso::updateOrCreate(
                [
                    'grupo_precio_id' => $grupoPrecio->id,
                    'proceso_id' => $procesoId,
                    'nombre' => trim((string) $subproceso['nombre']),
                ],
                [
                    'precio' => (float) $subproceso['precio'],
                    'orden' => (int) ($subproceso['orden'] ?? $orden),
                    'activo' => (bool) ($subproceso['activo'] ?? true),
                ],
            );

            $vigentes[] = $fila->id;
        }

        $sobrantes = $grupoPrecio->subprocesos()->whereNotIn('id', $vigentes ?: [0])->get();

        foreach ($sobrantes as $sobrante) {
            $sobrante->registros()->exists()
                ? $sobrante->update(['activo' => false])
                : $sobrante->delete();
        }
    }

    /**
     * Procesos que la obra paga: son los unicos que necesitan tarifa.
     *
     * @return \Illuminate\Support\Collection<int, \App\Models\Prod\Proceso>
     */
    private function procesosDeObra(?Obra $obra): \Illuminate\Support\Collection
    {
        return $obra === null ? collect() : $obra->procesos()->where('activo', true)->get();
    }

    /**
     * Una tarifa por proceso. Los procesos que la obra no paga se ignoran, para
     * que un grupo no arrastre precios de trabajo que ahi no se hace.
     *
     * @param  array<int|string, mixed>  $precios  procesoId => precio por kilo
     */
    private function guardarTarifas(GrupoPrecio $grupoPrecio, array $precios): void
    {
        $permitidos = $this->procesosDeObra($grupoPrecio->obra)->pluck('id');

        foreach ($precios as $procesoId => $precioKilo) {
            if (! $permitidos->contains((int) $procesoId)) {
                continue;
            }

            GrupoPrecioProceso::updateOrCreate(
                ['grupo_precio_id' => $grupoPrecio->id, 'proceso_id' => (int) $procesoId],
                ['precio_kilo' => (float) $precioKilo],
            );
        }
    }

    public function destroy(GrupoPrecio $grupoPrecio): RedirectResponse
    {
        if ($grupoPrecio->grupoPrecioConceptos()->exists()) {
            return back()->withErrors(['error' => 'No se puede eliminar un grupo de precios que tiene conceptos asignados.']);
        }

        $obraId = $grupoPrecio->obra_id;
        $grupoPrecio->delete();

        return to_route('admin.prod.grupo-precios.show-by-obra', $obraId);
    }
}
