<?php

namespace App\Services\Qal\Formatos;

use Illuminate\Support\Collection;

/**
 * Tres lecturas del mismo dato, que no conviene mezclar:
 *
 * - **Final**: una fila por pieza-etapa, su última inspección. Es la hoja de
 *   liberación, la que se entrega.
 * - **Revisión**: la final, pero cualquier criterio que salió con defecto en
 *   un intento anterior manda, y se juntan los defectos y observaciones de
 *   todos los intentos. Es la que sirve para auditar el retrabajo.
 * - **Histórico**: una fila por inspección. La trazabilidad: cuándo se
 *   rechazó, con qué, quién la volvió a mirar.
 *
 * La última inspección suele ser la que sólo cambió el estatus; las medidas se
 * tomaron antes. Por eso la fila final se completa con el dato más reciente de
 * los intentos previos cuando a ella le falta —si no, una pieza liberada salía
 * sin peso ni elementos, como si nunca se hubiera medido—. Sólo se arrastran
 * medidas, nunca criterios: arrastrar una D sería inventarle un defecto.
 */
class VistaDeReporte
{
    /** La pintura y la adherencia van enteras: son las medidas de la pieza, no un criterio. */
    private const ARRASTRA = ['kg', 'linea', 'modulo', 'soldador', 'pintura', 'adherencia'];

    /** Puntos que son una medida de la pieza, no un criterio. */
    private const ARRASTRA_PUNTOS = ['p2_elem'];

    /**
     * @param  Collection<int, array<string, mixed>>  $filas
     * @param  Collection<int, array<string, mixed>>  $universo
     * @return Collection<int, array<string, mixed>>
     */
    public function aplicar(Collection $filas, Collection $universo, string $vista): Collection
    {
        if ($vista === 'historico') {
            return $filas->values();
        }

        $historia = $universo->groupBy('etapa');

        return $filas
            ->groupBy('etapa')
            ->map(function (Collection $intentos) use ($historia, $vista): array {
                $ultima = $intentos->sortBy('inspeccion')->last();
                $previas = ($historia[$ultima['etapa']] ?? collect())
                    ->where('inspeccion', '<', $ultima['inspeccion'])
                    ->sortByDesc('inspeccion')
                    ->values();

                $fila = $this->completar($ultima, $previas);

                return $vista === 'revision' ? $this->consolidar($fila, $previas) : $fila;
            })
            ->values();
    }

    /**
     * Las piezas que necesitaron volver a inspeccionarse, para la nota al pie:
     * en la vista final no se ve de otro modo.
     *
     * @param  Collection<int, array<string, mixed>>  $filas
     */
    public function nota(Collection $filas, string $vista): ?string
    {
        if ($vista === 'historico') {
            return null;
        }

        $con = $filas->filter(fn (array $fila): bool => $fila['inspeccion'] > 1);

        if ($con->isEmpty()) {
            return null;
        }

        $lista = $con->map(fn (array $fila): string => trim($fila['marca'].' #'.$fila['consecutivo'], ' #')." ({$fila['inspeccion']} insp.)")->implode(' · ');

        return $con->count().' pieza(s) necesitaron re-inspección: '.$lista.'. '.($vista === 'revision'
            ? 'Hoja de revisión: cada fila es el estado final de la pieza con los defectos que tuvo en cualquiera de sus inspecciones.'
            : 'Se muestra la última inspección; la hoja de revisión enseña los defectos que tuvo y el histórico cada intento.');
    }

    /**
     * @param  array<string, mixed>  $fila
     * @param  Collection<int, array<string, mixed>>  $previas
     * @return array<string, mixed>
     */
    private function completar(array $fila, Collection $previas): array
    {
        foreach (self::ARRASTRA as $campo) {
            if ($this->vacio($fila[$campo])) {
                $fila[$campo] = $previas->map(fn (array $previa) => $previa[$campo])->first(fn ($valor): bool => ! $this->vacio($valor));
            }
        }

        foreach (self::ARRASTRA_PUNTOS as $clave) {
            if (! isset($fila['puntos'][$clave])) {
                $previo = $previas->first(fn (array $previa): bool => isset($previa['puntos'][$clave]));

                if ($previo) {
                    $fila['puntos'][$clave] = $previo['puntos'][$clave];
                }
            }
        }

        return $fila;
    }

    /**
     * @param  array<string, mixed>  $fila
     * @param  Collection<int, array<string, mixed>>  $previas
     * @return array<string, mixed>
     */
    private function consolidar(array $fila, Collection $previas): array
    {
        $observaciones = array_filter([$fila['observaciones']]);

        foreach ($previas as $previa) {
            foreach ($previa['puntos'] as $clave => $punto) {
                if ($punto['resultado'] === 'no_ok') {
                    $fila['puntos'][$clave] = $punto;
                }
            }

            foreach ($previa['defectos'] as $nombre => $cantidad) {
                $fila['defectos'][$nombre] = max($fila['defectos'][$nombre] ?? 0, $cantidad);
            }

            if ($previa['observaciones'] !== '' && ! in_array($previa['observaciones'], $observaciones, true)) {
                $observaciones[] = $previa['observaciones'];
            }
        }

        $fila['observaciones'] = implode(' · ', $observaciones);

        return $fila;
    }

    private function vacio(mixed $valor): bool
    {
        return $valor === null || $valor === '' || $valor === 0.0;
    }
}
