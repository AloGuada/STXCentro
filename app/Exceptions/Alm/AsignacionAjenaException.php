<?php

namespace App\Exceptions\Alm;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use RuntimeException;

/**
 * Hay material, pero está comprometido con otra obra.
 *
 * No es lo mismo que `ExistenciaInsuficienteException`: ahí no hay nada que
 * entregar, y aquí sí lo hay, con dueño. El mensaje tiene que decirlo, porque
 * quien está en el mostrador ve el material en el anaquel y necesita entender
 * por qué el sistema no se lo deja sacar.
 *
 * La salida consume lo de su propia obra y luego lo libre sin pedir nada; sólo
 * pasar sobre lo de un tercero exige `alm.salidas.tomar-asignado`. Con ese
 * permiso, esta excepción no se lanza.
 *
 * Renderizable por lo mismo que su hermana: los controladores no la atrapan.
 */
class AsignacionAjenaException extends RuntimeException
{
    public function __construct(
        public readonly int $almacenId,
        public readonly int $productoId,
        public readonly float $libre,
        public readonly float $solicitado,
        public readonly float $asignadoAOtras,
        public readonly string $descripcionProducto = '',
    ) {
        parent::__construct(sprintf(
            '%s: hay %s sin asignar y se pidieron %s. Las otras %s están comprometidas con otra obra; '
            .'para llevártelas necesitas reasignarlas o el permiso para tomar material asignado.',
            $descripcionProducto !== '' ? $descripcionProducto : "El producto #{$productoId}",
            self::formatear($libre),
            self::formatear($solicitado),
            self::formatear($asignadoAOtras),
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
