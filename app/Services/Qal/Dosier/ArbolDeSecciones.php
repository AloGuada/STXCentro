<?php

namespace App\Services\Qal\Dosier;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * El árbol de secciones de un dosier o de su plantilla: se guarda plano
 * (`padre_id` + `orden`) y se lee anidado con el número calculado.
 *
 * El número sale de la posición —el tercer hijo del segundo capítulo es el
 * 2.3—, así que mover o quitar una sección renumera todo lo demás sin tocar
 * nada más. Los índices reales de Steelex traen números repetidos
 * precisamente porque se numeraban a mano.
 */
class ArbolDeSecciones
{
    /** Hasta 1.1.3.2: la profundidad del índice más hondo que usa Steelex. */
    public const PROFUNDIDAD_MAXIMA = 4;

    /**
     * @param  iterable<Model>  $secciones  con id, padre_id, orden, titulo y nota
     * @return list<array{id: int, numero: string, titulo: string, nota: string|null, hijos: list<array<string, mixed>>}>
     */
    public function anidar(iterable $secciones): array
    {
        $porPadre = collect($secciones)->sortBy('orden')->groupBy(fn (Model $seccion): int => $seccion->padre_id ?? 0);

        $construir = function (int $padre, string $prefijo) use (&$construir, $porPadre): array {
            return ($porPadre[$padre] ?? collect())
                ->values()
                ->map(function (Model $seccion, int $i) use ($construir, $prefijo): array {
                    $numero = $prefijo === '' ? (string) ($i + 1) : "{$prefijo}.".($i + 1);

                    return [
                        'id' => $seccion->id,
                        'numero' => $numero,
                        'titulo' => $seccion->titulo,
                        'nota' => $seccion->nota,
                        'hijos' => $construir($seccion->id, $numero),
                    ];
                })
                ->all();
        };

        return $construir(0, '');
    }

    /**
     * El árbol en orden de lectura, cada sección con su número y su nivel: el
     * índice del dosier.
     *
     * @param  list<array<string, mixed>>  $arbol  anidado, como lo devuelve `anidar`
     * @return list<array<string, mixed>>
     */
    public function aplanar(array $arbol, int $nivel = 1): array
    {
        $lista = [];

        foreach ($arbol as $nodo) {
            $lista[] = [...array_diff_key($nodo, ['hijos' => true]), 'nivel' => $nivel];
            array_push($lista, ...$this->aplanar($nodo['hijos'], $nivel + 1));
        }

        return $lista;
    }

    /**
     * Deja las secciones de la relación exactamente como el árbol recibido:
     * actualiza las que traen un id suyo, crea las que no y borra las que ya
     * no vienen (con sus hijas). Un id que no es de esta relación se toma como
     * sección nueva, así que copiar el árbol de otra plantilla es guardarlo.
     *
     * @param  HasMany<Model, Model>  $relacion
     * @param  list<array{id?: int|null, titulo: string, nota?: string|null, hijos?: list<array<string, mixed>>}>  $arbol
     */
    public function guardar(HasMany $relacion, array $arbol): void
    {
        DB::transaction(function () use ($relacion, $arbol): void {
            $existentes = (clone $relacion)->pluck('id')->flip();
            $vistos = [];

            $recorrer = function (array $nodos, ?int $padre) use (&$recorrer, $relacion, $existentes, &$vistos): void {
                foreach (array_values($nodos) as $posicion => $nodo) {
                    $datos = [
                        'padre_id' => $padre,
                        'orden' => $posicion + 1,
                        'titulo' => trim((string) $nodo['titulo']),
                        'nota' => filled($nodo['nota'] ?? null) ? trim((string) $nodo['nota']) : null,
                    ];
                    $id = isset($nodo['id']) ? (int) $nodo['id'] : null;

                    if ($id !== null && $existentes->has($id) && ! isset($vistos[$id])) {
                        $relacion->getRelated()->newQuery()->whereKey($id)->update($datos);
                    } else {
                        $id = (clone $relacion)->create($datos)->getKey();
                    }

                    $vistos[$id] = true;
                    $recorrer($nodo['hijos'] ?? [], $id);
                }
            };

            $recorrer($arbol, null);

            $sobran = $existentes->keys()->reject(fn (int $id): bool => isset($vistos[$id]));
            $relacion->getRelated()->newQuery()->whereKey($sobran->all())->delete();
        });
    }

    /**
     * Cuántas secciones hay en el árbol, de cualquier nivel.
     *
     * @param  list<array<string, mixed>>  $arbol
     */
    public function contar(array $arbol): int
    {
        return (new Collection($arbol))->sum(fn (array $nodo): int => 1 + $this->contar($nodo['hijos'] ?? []));
    }
}
