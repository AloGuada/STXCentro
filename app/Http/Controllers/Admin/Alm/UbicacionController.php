<?php

namespace App\Http\Controllers\Admin\Alm;

use App\Enums\Alm\UbicacionTipo;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Alm\UbicacionStoreRequest;
use App\Http\Requests\Admin\Alm\UbicacionUpdateRequest;
use App\Models\Alm\Almacen;
use App\Models\Alm\Existencia;
use App\Models\Alm\Ubicacion;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * El tercer nivel del inventario: obra → almacén → ubicación.
 *
 * Una sola pantalla, con el árbol a la izquierda y el alta a la derecha, porque
 * un lugar son cuatro campos y mandar a otra vista a capturarlos haría más lento
 * dar de alta un rack completo.
 */
class UbicacionController extends Controller
{
    public function index(Request $request): Response
    {
        $almacenes = Almacen::query()
            ->visiblesPara($request->user())
            ->activos()
            ->with('obra:id,no,descripcion')
            ->orderBy('obra_id')
            ->orderBy('clave')
            ->get(['id', 'clave', 'nombre', 'obra_id', 'tipo']);

        $almacen = $this->almacenElegido($request, $almacenes);

        return Inertia::render('admin/almacen/ubicaciones/index', [
            'almacenes' => $almacenes,
            'almacenSeleccionado' => $almacen?->id,
            'ubicaciones' => $almacen === null ? [] : $this->ubicacionesDe($almacen),
            'sinAcomodar' => $almacen === null ? 0 : Existencia::query()
                ->where('almacen_id', $almacen->id)
                ->sinAcomodar()
                ->conSaldo()
                ->count(),
            'tipos' => $this->tipos(),
        ]);
    }

    public function store(UbicacionStoreRequest $request): RedirectResponse
    {
        $almacen = Almacen::findOrFail($request->integer('almacen_id'));

        abort_unless($almacen->esVisiblePara($request->user()), 403);

        Ubicacion::create($request->validated());

        return back();
    }

    public function update(UbicacionUpdateRequest $request, Ubicacion $ubicacion): RedirectResponse
    {
        abort_unless($ubicacion->almacen->esVisiblePara($request->user()), 403);

        $ubicacion->update($request->validated());

        return back();
    }

    /**
     * Baja lógica, nunca borrado: el kardex viejo menciona el lugar, y borrarlo
     * dejaría movimientos apuntando a un anaquel que ya no existe.
     *
     * Al dar de baja un lugar caen también los que cuelgan de él: dejar vivo un
     * nivel dentro de un rack retirado es prometer una dirección que ya nadie
     * puede recorrer.
     */
    public function toggle(Request $request, Ubicacion $ubicacion): RedirectResponse
    {
        abort_unless($ubicacion->almacen->esVisiblePara($request->user()), 403);

        $activa = ! $ubicacion->activa;

        $ubicacion->update(['activa' => $activa]);

        if (! $activa) {
            $this->desactivarDescendientes($ubicacion);
        }

        return back();
    }

    /**
     * Acomoda una existencia en un lugar, o la deja sin acomodar con `null`.
     *
     * Es la única columna de `alm_existencias` que se escribe fuera del ledger:
     * dónde está guardado el material no es un hecho contable, es acomodo, y no
     * cambia ni el saldo ni el costo.
     *
     * Va detrás de `alm.ubicaciones.editar` y no de un permiso de existencias
     * porque es una decisión sobre el lugar, no sobre el inventario.
     */
    public function asignar(Request $request, Existencia $existencia): RedirectResponse
    {
        abort_unless($existencia->almacen->esVisiblePara($request->user()), 403);

        $validado = $request->validate([
            'ubicacion_id' => [
                'nullable', 'integer',
                Rule::exists('alm_ubicaciones', 'id')
                    ->where('almacen_id', $existencia->almacen_id)
                    ->where('activa', true),
            ],
        ], [
            'ubicacion_id.exists' => 'Ese lugar no existe en este almacén o ya está dado de baja.',
        ]);

        $existencia->update(['ubicacion_id' => $validado['ubicacion_id'] ?? null]);

        return back();
    }

    /**
     * El árbol del almacén, ya aplanado en el orden en que se recorre y con la
     * profundidad para sangrarlo, más cuántos artículos vive en cada lugar:
     * dar de baja a ciegas es como se pierde material.
     *
     * @return list<array<string, mixed>>
     */
    private function ubicacionesDe(Almacen $almacen): array
    {
        $ubicaciones = Ubicacion::query()
            ->where('almacen_id', $almacen->id)
            ->orderBy('codigo')
            ->get();

        $conteos = Existencia::query()
            ->where('almacen_id', $almacen->id)
            ->conSaldo()
            ->whereNotNull('ubicacion_id')
            ->selectRaw('ubicacion_id, COUNT(*) as total')
            ->groupBy('ubicacion_id')
            ->pluck('total', 'ubicacion_id');

        $filas = [];

        $colgar = function (?int $padreId, int $nivel) use (&$colgar, &$filas, $ubicaciones, $conteos): void {
            foreach ($ubicaciones->where('padre_id', $padreId) as $ubicacion) {
                $filas[] = [
                    'id' => $ubicacion->id,
                    'padre_id' => $ubicacion->padre_id,
                    'codigo' => $ubicacion->codigo,
                    'nombre' => $ubicacion->nombre,
                    'tipo' => $ubicacion->tipo->value,
                    'activa' => $ubicacion->activa,
                    'nivel' => $nivel,
                    'articulos' => (int) ($conteos[$ubicacion->id] ?? 0),
                ];

                $colgar($ubicacion->id, $nivel + 1);
            }
        };

        $colgar(null, 0);

        return $filas;
    }

    private function desactivarDescendientes(Ubicacion $ubicacion): void
    {
        foreach ($ubicacion->hijas as $hija) {
            $hija->update(['activa' => false]);
            $this->desactivarDescendientes($hija);
        }
    }

    /**
     * @param  \Illuminate\Support\Collection<int, Almacen>  $almacenes
     */
    private function almacenElegido(Request $request, $almacenes): ?Almacen
    {
        $pedido = $request->integer('almacen_id');

        return $almacenes->firstWhere('id', $pedido) ?? $almacenes->first();
    }

    /**
     * @return list<array{value: string, label: string}>
     */
    private function tipos(): array
    {
        return array_map(
            fn (UbicacionTipo $tipo): array => ['value' => $tipo->value, 'label' => $tipo->etiqueta()],
            UbicacionTipo::cases(),
        );
    }
}
