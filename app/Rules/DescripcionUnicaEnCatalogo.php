<?php

namespace App\Rules;

use App\Services\Catalogo\CatalogoMaestro;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * No hay dos activos en el catálogo maestro que se llamen igual. Aplica en las
 * altas y ediciones manuales —Artículos y Productos—, donde quien captura tiene
 * el buscador enfrente y lo correcto es que use el existente. La requisición no
 * la usa: ahí lo tecleado al vuelo se reutiliza en silencio.
 *
 * En el alta sólo estorba el que ya tiene **esta** cara: si Compras ya compró
 * "TALADRO MAGNETICO" y Almacén lo da de alta, eso no es un duplicado, es la
 * cara de Almacén del mismo insumo y el modelo la liga solo. En la edición
 * estorba cualquiera que no sea uno mismo, porque renombrar a un nombre
 * ocupado chocaría con el índice del maestro sin importar la cara.
 */
class DescripcionUnicaEnCatalogo implements ValidationRule
{
    private function __construct(
        private readonly ?string $cara,
        private readonly ?int $ignorarItemId,
    ) {}

    /** @param  'producto'|'articulo'  $cara */
    public static function paraAlta(string $cara): self
    {
        return new self($cara, null);
    }

    public static function paraEdicion(?int $itemId): self
    {
        return new self(null, $itemId);
    }

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $item = app(CatalogoMaestro::class)->buscar((string) $value);

        if ($item === null || $item->id === $this->ignorarItemId) {
            return;
        }

        if ($this->cara !== null && ! $item->{$this->cara}()->exists()) {
            return;
        }

        $codigo = $item->codigo !== null ? " ({$item->codigo})" : '';

        $fail("Ya existe \"{$item->descripcion}\"{$codigo} en el catálogo; usa ese en lugar de crear otro.");
    }
}
