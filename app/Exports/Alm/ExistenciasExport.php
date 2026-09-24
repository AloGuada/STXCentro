<?php

namespace App\Exports\Alm;

use App\Models\Alm\Existencia;
use App\Models\Alm\PrestamoDetalle;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;

/**
 * El inventario filtrado, a Excel: una hoja por almacén activo, un renglón por
 * existencia con lo que pide quien lo concilia afuera del sistema.
 *
 * Recibe la misma consulta que arma la pantalla, sin paginar, para que el
 * archivo traiga exactamente lo que se ve y no una versión distinta del
 * filtro. Los almacenes dados de baja se quedan fuera: su lista ya no es de
 * nadie. El precio es el costo promedio del renglón: lo que vale hoy cada
 * unidad de lo que hay en esa bodega.
 *
 * La hoja lleva el nombre del almacén y no su clave: la clave se repite entre
 * obras («CONST» de MBP y de T4).
 *
 * Lo prestado no sale del saldo —sigue siendo del almacén—, así que el stock
 * lo incluye y aparte se dice cuánto anda afuera, con quién y en dónde. Sale
 * de los resguardos abiertos y no del cacheado de la existencia, porque éste
 * sólo lleva los activos por cantidad y las piezas con serie lo dicen por
 * estatus. Un renglón con varios resguardos pone uno por línea, en el mismo
 * orden en las dos columnas, para que se lean parejas.
 */
class ExistenciasExport implements WithMultipleSheets
{
    /** Lo más largo que Excel acepta en el nombre de una hoja. */
    private const LARGO_HOJA = 31;

    /**
     * @param  Builder<Existencia>  $consulta
     */
    public function __construct(private readonly Builder $consulta) {}

    /**
     * @return list<ExistenciasAlmacenSheet>
     */
    public function sheets(): array
    {
        $existencias = (clone $this->consulta)
            ->with(['almacen:id,nombre', 'articulo:id,codigo,descripcion,unidad,tipo,area_id', 'articulo.area:id,descripcion'])
            ->join('alm_articulos', 'alm_articulos.id', '=', 'alm_existencias.articulo_id')
            ->join('alm_almacenes', 'alm_almacenes.id', '=', 'alm_existencias.almacen_id')
            ->where('alm_almacenes.activo', true)
            ->orderBy('alm_almacenes.nombre')
            ->orderBy('alm_existencias.almacen_id')
            ->orderBy('alm_articulos.descripcion')
            ->select('alm_existencias.*')
            ->get();

        // Un libro sin hojas no abre: si el filtro no encontró nada, va una vacía.
        if ($existencias->isEmpty()) {
            return [new ExistenciasAlmacenSheet('Existencias', collect())];
        }

        $prestamos = $this->prestamosAbiertosDe($existencias);
        $usados = [];
        $hojas = [];

        foreach ($existencias->groupBy('almacen_id') as $delAlmacen) {
            $hojas[] = new ExistenciasAlmacenSheet(
                $this->nombreDeHoja((string) $delAlmacen->first()->almacen?->nombre, $usados),
                $delAlmacen->map(fn (Existencia $e): array => $this->renglon($e, $prestamos))->values(),
            );
        }

        return $hojas;
    }

    /**
     * @param  Collection<string, Collection<int, array{responsable: string, destino: string, cantidad: float}>>  $prestamos
     * @return array<string, mixed>
     */
    private function renglon(Existencia $e, Collection $prestamos): array
    {
        $afuera = $prestamos->get($e->almacen_id.'|'.$e->articulo_id, collect());

        return [
            'almacen' => $e->almacen?->nombre,
            'codigo' => $e->articulo?->codigo,
            'descripcion' => $e->articulo?->descripcion,
            'tipo' => $e->articulo?->tipo?->etiqueta(),
            'stock' => (float) $e->cantidad,
            'prestado' => (float) $afuera->sum('cantidad'),
            'unidad' => $e->articulo?->unidad,
            'precio' => (float) $e->costo_promedio,
            'area' => $e->articulo?->area?->descripcion,
            'prestado_a' => $afuera->isEmpty() ? null : $afuera->map(fn (array $p): string => "{$p['responsable']} ({$this->numero($p['cantidad'])})")->implode("\n"),
            'ubicacion_prestamo' => $afuera->isEmpty() ? null : $afuera->pluck('destino')->implode("\n"),
        ];
    }

    /**
     * El nombre del almacén cabido en la pestaña: sin el «Almacén de» que todos
     * repiten, sin los caracteres que Excel prohíbe y sin chocar con otra hoja.
     *
     * @param  array<string, true>  $usados
     */
    private function nombreDeHoja(string $nombre, array &$usados): string
    {
        $base = trim(preg_replace('/^Almac[eé]n\s+(de\s+)?/iu', '', str_replace(['[', ']', ':', '*', '?', '/', '\\'], ' ', $nombre)) ?? '');
        $base = Str::substr($base !== '' ? $base : $nombre, 0, self::LARGO_HOJA);

        $hoja = $base;

        for ($n = 2; isset($usados[Str::lower($hoja)]); $n++) {
            $hoja = Str::substr($base, 0, self::LARGO_HOJA - Str::length(" ({$n})"))." ({$n})";
        }

        $usados[Str::lower($hoja)] = true;

        return $hoja;
    }

    /**
     * Lo que sigue afuera de cada renglón, sumado por persona y destino: dos
     * resguardos de Juan a la misma obra son una sola línea.
     *
     * @param  Collection<int, Existencia>  $existencias
     * @return Collection<string, Collection<int, array{responsable: string, destino: string, cantidad: float}>>
     */
    private function prestamosAbiertosDe(Collection $existencias): Collection
    {
        return PrestamoDetalle::query()
            ->whereIn('articulo_id', $existencias->pluck('articulo_id')->unique())
            ->whereHas('prestamo', fn (Builder $q) => $q->abiertos()->whereIn('almacen_id', $existencias->pluck('almacen_id')->unique()))
            ->whereColumn('cantidad_devuelta', '<', 'cantidad')
            ->with(['prestamo.responsable:id,name', 'prestamo.obra:id,no', 'prestamo.grupoTrabajo:id,descripcion'])
            ->orderBy('prestamo_id')
            ->get()
            ->groupBy(fn (PrestamoDetalle $d): string => $d->prestamo->almacen_id.'|'.$d->articulo_id)
            ->map(fn (Collection $renglones): Collection => $renglones
                ->groupBy(fn (PrestamoDetalle $d): string => $d->prestamo->responsable_id.'|'.$d->prestamo->destino())
                ->map(fn (Collection $mismos): array => [
                    'responsable' => $mismos->first()->prestamo->responsable?->name ?? 'Sin responsable',
                    'destino' => $mismos->first()->prestamo->destino(),
                    'cantidad' => (float) $mismos->sum(fn (PrestamoDetalle $d): float => $d->pendiente()),
                ])
                ->values());
    }

    /** La cantidad sin ceros de relleno: «2», no «2.0000». */
    private function numero(float $cantidad): string
    {
        return rtrim(rtrim(number_format($cantidad, 4, '.', ','), '0'), '.');
    }
}
