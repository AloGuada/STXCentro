<?php

namespace App\Exceptions\Alm;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use RuntimeException;

/**
 * Se quiso sacar más de lo que hay.
 *
 * Es renderizable a propósito: los controladores de almacén no la atrapan, y
 * quien capturó la salida regresa a su formulario con lo que ya había tecleado
 * y el renglón señalado. Atraparla en cada `store` sería repetir el mismo
 * `catch` seis veces.
 */
class ExistenciaInsuficienteException extends RuntimeException
{
    public function __construct(
        public readonly int $almacenId,
        public readonly int $productoId,
        public readonly float $disponible,
        public readonly float $solicitado,
        public readonly string $descripcionProducto = '',
    ) {
        parent::__construct(sprintf(
            'No hay existencia suficiente de %s: hay %s y se pidieron %s.',
            $descripcionProducto !== '' ? $descripcionProducto : "el producto #{$productoId}",
            self::formatear($disponible),
            self::formatear($solicitado),
        ));
    }

    public function render(Request $request): RedirectResponse
    {
        return back()->withInput()->withErrors(['detalles' => $this->getMessage()]);
    }

    /** Sin ceros de relleno: «12.5», no «12.5000». */
    private static function formatear(float $cantidad): string
    {
        return rtrim(rtrim(number_format($cantidad, 4, '.', ''), '0'), '.') ?: '0';
    }
}
