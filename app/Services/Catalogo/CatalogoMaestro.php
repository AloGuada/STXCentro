<?php

namespace App\Services\Catalogo;

use App\Models\Item;
use Illuminate\Support\Str;

/**
 * La única puerta para nacer en el catálogo maestro.
 *
 * Todo lo que da de alta un insumo —la requisición tecleada al vuelo, el alta
 * manual de Almacén, la carga inicial de un almacén, la recepción de una orden—
 * pasa por `buscarOCrear()`: si ya hay un activo que se llama igual, es ése; si
 * no, nace uno. Es lo que hace que un segundo almacén con "TALADRO MAGNETICO"
 * reutilice el primero en lugar de estrenar otro código.
 */
class CatalogoMaestro
{
    /**
     * Cómo se compara una descripción: minúsculas, sin acentos, un solo espacio
     * entre palabras y sin espacios en las puntas. "Careta  Facial" y "CARETA
     * FACIAL" son lo mismo; "CARETA FACIAL" y "CARETAS FACIALES" no, y eso ya
     * es criterio de una persona, no de esta función.
     */
    public static function normalizar(string $descripcion): string
    {
        $plana = Str::ascii(mb_strtolower(trim($descripcion)));

        return trim((string) preg_replace('/\s+/', ' ', $plana));
    }

    /** El activo que se llama así, si lo hay. */
    public function buscar(string $descripcion): ?Item
    {
        $normalizada = self::normalizar($descripcion);

        if ($normalizada === '') {
            return null;
        }

        return Item::query()->activos()->where('descripcion_normalizada', $normalizada)->first();
    }

    /**
     * El que se llama así, o uno nuevo si no hay. Cuando reutiliza, **no**
     * sobreescribe unidad ni código con lo que trae la llamada: el maestro ya
     * decidió cómo se mide y cómo se llama ese insumo, y quien lo teclea al
     * vuelo trae lo que recuerda.
     */
    public function buscarOCrear(string $descripcion, string $unidad, ?string $codigo = null, ?string $creadoPor = null): Item
    {
        $existente = $this->buscar($descripcion);

        if ($existente !== null) {
            return $existente;
        }

        return Item::create([
            'codigo' => $codigo,
            'descripcion' => trim($descripcion),
            'unidad' => $unidad,
            'activo' => true,
            'creado_por' => $creadoPor,
        ]);
    }
}
