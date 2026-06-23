<?php

namespace App\Http\Controllers\Admin\Cob;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Cob\CambiarEstadoEstimacionRequest;
use App\Http\Requests\Admin\Cob\EstimacionStoreRequest;
use App\Http\Requests\Admin\Cob\EstimacionUpdateRequest;
use App\Models\Cob\Estimacion;
use App\Models\Cob\Partida;
use App\Models\Cob\TipoRetencion;
use App\Models\Proyecto;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class EstimacionController extends Controller
{
    /** @var array<string, list<string>> */
    private const TRANSICIONES = [
        'pendiente' => ['generada'],
        'generada' => ['ingresada'],
        'ingresada' => ['revisada'],
        'revisada' => ['autorizada'],
        'autorizada' => ['facturada'],
        'facturada' => ['pago_parcial', 'pagado'],
        'pago_parcial' => ['pagado'],
        'pagado' => [],
    ];

    public function create(Proyecto $proyecto): Response
    {
        $nextNumber = ($proyecto->estimaciones()->max('numero_estimacion') ?? 0) + 1;

        return Inertia::render('admin/cob/estimaciones/create', [
            'proyecto' => $proyecto->only('id', 'no', 'descripcion'),
            'obras' => $this->obrasConPartidas($proyecto),
            'nextNumber' => $nextNumber,
        ]);
    }

    public function store(EstimacionStoreRequest $request, Proyecto $proyecto): RedirectResponse
    {
        $obraId = $this->resolverObra($request, $proyecto);
        if ($obraId === false) {
            return back()->withErrors(['obra_id' => 'La obra no pertenece al proyecto.']);
        }

        $partidaIds = $this->resolverPartidas($request, $obraId);
        if ($partidaIds === false) {
            return back()->withErrors(['partida_ids' => 'Las partidas no pertenecen a la obra.']);
        }

        DB::transaction(function () use ($request, $proyecto, $obraId, $partidaIds): void {
            // Consecutivo autoritativo del proyecto (abarca todas sus obras). Se
            // calcula con lock dentro de la transacción para evitar duplicados en
            // creaciones concurrentes; el valor enviado por el cliente se ignora.
            $siguienteNumero = ($proyecto->estimaciones()->lockForUpdate()->max('numero_estimacion') ?? 0) + 1;

            $estimacion = $proyecto->estimaciones()->create([
                ...$request->safe()->only([
                    'folio', 'tipo', 'fecha_emision', 'inicio', 'fin',
                    'monto_estimado', 'monto_total', 'moneda', 'comentarios',
                ]),
                'numero_estimacion' => $siguienteNumero,
                'nivel' => $request->validated('nivel'),
                'obra_id' => $obraId,
                'estado' => 'pendiente',
            ]);

            $estimacion->partidas()->sync($partidaIds);
        });

        return to_route('admin.cob.proyectos.show', $proyecto);
    }

    public function edit(Proyecto $proyecto, Estimacion $estimacion): Response
    {
        $estimacion->load(['pagos.comprobantes', 'historial.usuario', 'retenciones.tipoRetencion', 'partidas:id']);

        return Inertia::render('admin/cob/estimaciones/edit', [
            'proyecto' => $proyecto->only('id', 'no', 'descripcion'),
            'obras' => $this->obrasConPartidas($proyecto),
            'estimacion' => $estimacion,
            'partidaIds' => $estimacion->partidas->pluck('id'),
            'tiposRetencion' => TipoRetencion::all(),
        ]);
    }

    public function update(EstimacionUpdateRequest $request, Proyecto $proyecto, Estimacion $estimacion): RedirectResponse
    {
        $obraId = $this->resolverObra($request, $proyecto);
        if ($obraId === false) {
            return back()->withErrors(['obra_id' => 'La obra no pertenece al proyecto.']);
        }

        $partidaIds = $this->resolverPartidas($request, $obraId);
        if ($partidaIds === false) {
            return back()->withErrors(['partida_ids' => 'Las partidas no pertenecen a la obra.']);
        }

        DB::transaction(function () use ($request, $estimacion, $obraId, $partidaIds): void {
            $estimacion->update([
                ...$request->safe()->only([
                    'folio', 'tipo', 'fecha_emision', 'inicio', 'fin',
                    'monto_estimado', 'monto_total', 'moneda', 'comentarios',
                ]),
                'nivel' => $request->validated('nivel'),
                'obra_id' => $obraId,
            ]);

            $estimacion->partidas()->sync($partidaIds);
        });

        return back();
    }

    public function destroy(Proyecto $proyecto, Estimacion $estimacion): RedirectResponse
    {
        if ($estimacion->pagos()->exists()) {
            return back()->withErrors(['delete' => 'No se puede eliminar una estimacion con pagos registrados.']);
        }

        $estimacion->delete();

        return to_route('admin.cob.proyectos.show', $proyecto);
    }

    public function cambiarEstado(CambiarEstadoEstimacionRequest $request, Proyecto $proyecto, Estimacion $estimacion): RedirectResponse
    {
        $estadoActual = $estimacion->estado;
        $estadoNuevo = $request->validated('estado');

        if (! in_array($estadoNuevo, self::TRANSICIONES[$estadoActual] ?? [])) {
            return back()->withErrors(['estado' => "No se puede cambiar de '{$estadoActual}' a '{$estadoNuevo}'."]);
        }

        $fechaCambio = $request->validated('fecha_cambio') ?? now();

        DB::transaction(function () use ($estimacion, $estadoActual, $estadoNuevo, $request, $fechaCambio) {
            $estimacion->update([
                'estado' => $estadoNuevo,
                'fecha_ultimo_cambio_estado' => $fechaCambio,
            ]);

            $estimacion->historial()->create([
                'estado_anterior' => $estadoActual,
                'estado_nuevo' => $estadoNuevo,
                'folio' => $request->validated('folio'),
                'usuario_id' => auth()->id(),
                'comentario' => $request->validated('comentario'),
                'fecha_cambio' => $fechaCambio,
            ]);
        });

        return back();
    }

    /**
     * Obra de la estimación según el nivel. `null` si es global; `false` si la
     * obra no pertenece al proyecto.
     */
    private function resolverObra(EstimacionStoreRequest|EstimacionUpdateRequest $request, Proyecto $proyecto): int|false|null
    {
        if ($request->validated('nivel') === 'proyecto') {
            return null;
        }

        $obraId = (int) $request->validated('obra_id');

        return $proyecto->obras()->whereKey($obraId)->exists() ? $obraId : false;
    }

    /**
     * Partidas a sincronizar. `false` si alguna no pertenece a la obra.
     *
     * @return list<int>|false
     */
    private function resolverPartidas(EstimacionStoreRequest|EstimacionUpdateRequest $request, int|false|null $obraId): array|false
    {
        if ($request->validated('nivel') !== 'partida' || $obraId === false || $obraId === null) {
            return [];
        }

        /** @var list<int> $ids */
        $ids = array_values(array_map('intval', $request->validated('partida_ids', [])));

        $validas = Partida::query()
            ->where('obra_id', $obraId)
            ->whereIn('id', $ids)
            ->count();

        return $validas === count($ids) ? $ids : false;
    }

    /**
     * Obras del proyecto con sus partidas, para los selectores del formulario.
     *
     * @return Collection<int, array<string, mixed>>
     */
    private function obrasConPartidas(Proyecto $proyecto): Collection
    {
        return $proyecto->obras()
            ->with('partidas:id,obra_id,descripcion,tipo,monto')
            ->orderByRaw("CASE WHEN tipo = 'base' THEN 0 ELSE 1 END")
            ->orderBy('no')
            ->get(['id', 'no', 'descripcion'])
            ->map(fn ($obra) => [
                'id' => $obra->id,
                'no' => $obra->no,
                'descripcion' => $obra->descripcion,
                'partidas' => $obra->partidas->map(fn ($p) => $p->only('id', 'descripcion', 'tipo', 'monto'))->values(),
            ]);
    }
}
