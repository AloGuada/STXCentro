<?php

namespace App\Http\Controllers\Admin\Alm;

use App\Enums\Alm\MovimientoTipo;
use App\Http\Controllers\Controller;
use App\Models\Alm\Almacen;
use App\Models\Alm\Articulo;
use App\Models\Alm\Movimiento;
use App\Models\Obra;
use App\Support\HoraLocal;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Inertia\Inertia;
use Inertia\Response;

/**
 * El libro de movimientos: sólo lectura.
 *
 * Corregir un error es capturar el movimiento contrario, no borrar el renglón —
 * por eso aquí no hay más que `index`.
 *
 * El saldo de cada renglón **se lee** de `saldo_despues` en vez de recalcularse
 * sumando la columna: ése es el punto entero del ledger. Recalcularlo daría un
 * número distinto en cuanto la página filtre o pagine, porque el corrido dejaría
 * de arrancar desde cero.
 */
class KardexController extends Controller
{
    public function index(Request $request): Response
    {
        $filtros = $request->only(['almacen_id', 'articulo_id', 'obra_id', 'tipo', 'desde', 'hasta', 'referencia']);

        $base = Movimiento::query()
            ->whereIn('almacen_id', $this->almacenesVisibles($request))
            ->filtrados($filtros);

        $movimientos = (clone $base)
            ->with([
                'almacen:id,clave',
                'articulo:id,codigo,descripcion,unidad',
                'obra:id,no',
                'usuario:id,name',
            ])
            ->cronologico(descendente: true)
            ->paginate(50)
            ->withQueryString()
            ->through(fn (Movimiento $m): array => [
                'id' => $m->id,
                'fecha' => HoraLocal::texto($m->created_at),
                'almacen' => $m->almacen?->clave,
                'codigo' => $m->articulo?->codigo,
                'descripcion' => $m->articulo?->descripcion,
                'unidad' => $m->articulo?->unidad,
                'tipo' => $m->tipo->value,
                'tipo_etiqueta' => $m->tipo->etiqueta(),
                // De quién era el material que movió el asiento. Vacío = libre,
                // sin dueño. Es lo que explica la partición de Existencias.
                'obra' => $m->obra?->no,
                'cantidad' => (float) $m->cantidad,
                'saldo_despues' => (float) $m->saldo_despues,
                'costo_unitario' => $m->costo_unitario === null ? null : (float) $m->costo_unitario,
                'referencia' => $m->referencia,
                'es_reverso' => $m->es_reverso,
                'observaciones' => $m->observaciones,
                'usuario' => $m->usuario?->name,
            ]);

        return Inertia::render('admin/almacen/kardex/index', [
            'movimientos' => $movimientos,
            'filters' => $filtros,
            // Los totales van sobre el filtro completo, no sobre la página: si
            // sólo sumaran lo visible, cambiar de página cambiaría el total y
            // el número dejaría de significar nada.
            'totales' => $this->totales(clone $base),
            'almacenes' => $this->almacenes($request),
            'productos' => $this->productos(),
            // Para el filtro por obra. `libre` se agrega en la pantalla: es una
            // opción del filtro, no una obra del catálogo.
            'obras' => Obra::query()->orderBy('no')->get(['id', 'no']),
            'tipos' => array_map(
                fn (MovimientoTipo $t): array => ['value' => $t->value, 'label' => $t->etiqueta()],
                MovimientoTipo::cases(),
            ),
        ]);
    }

    /**
     * @param  \Illuminate\Database\Eloquent\Builder<Movimiento>  $query
     * @return array{movimientos: int, entradas: float, salidas: float}
     */
    private function totales($query): array
    {
        $fila = $query
            // La reasignación no entra: cambia de dueño, no de bodega, y su
            // pareja de asientos suma cero. Contarla inflaría los dos lados con
            // material que nunca se movió.
            ->where('tipo', '!=', MovimientoTipo::Reasignacion->value)
            ->selectRaw('COUNT(*) as movimientos')
            ->selectRaw('COALESCE(SUM(CASE WHEN cantidad > 0 THEN cantidad ELSE 0 END), 0) as entradas')
            ->selectRaw('COALESCE(SUM(CASE WHEN cantidad < 0 THEN cantidad ELSE 0 END), 0) as salidas')
            ->first();

        return [
            'movimientos' => (int) ($fila->movimientos ?? 0),
            'entradas' => (float) ($fila->entradas ?? 0),
            'salidas' => (float) ($fila->salidas ?? 0),
        ];
    }

    /**
     * @return Collection<int, int>
     */
    private function almacenesVisibles(Request $request): Collection
    {
        return Almacen::query()->visiblesPara($request->user())->pluck('id');
    }

    /**
     * @return Collection<int, Almacen>
     */
    private function almacenes(Request $request): Collection
    {
        return Almacen::query()
            ->visiblesPara($request->user())
            ->with('obra:id,no')
            ->orderBy('obra_id')
            ->orderBy('clave')
            ->get(['id', 'clave', 'nombre', 'obra_id', 'tipo']);
    }

    /**
     * Sólo los que tienen algo que contar: el desplegable con el catálogo
     * completo obliga a buscar entre artículos que nunca han movido nada.
     *
     * @return Collection<int, Articulo>
     */
    private function productos(): Collection
    {
        return Articulo::query()
            ->whereHas('existencias')
            ->orderBy('descripcion')
            ->get(['id', 'codigo', 'descripcion', 'unidad', 'requiere_verificacion']);
    }
}
