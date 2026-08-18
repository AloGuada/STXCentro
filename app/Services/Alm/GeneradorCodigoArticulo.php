<?php

namespace App\Services\Alm;

use Illuminate\Support\Facades\DB;

/**
 * El código de un artículo nuevo: un consecutivo global `ART-#####` que pone el
 * sistema y nadie edita.
 *
 * Sin familias ni prefijo por tipo. Se propuso y se descartó: obliga a mantener
 * un catálogo de familias que nadie va a depurar, y la primera decisión del alta
 * pasaría a ser una que el almacenista no sabe contestar. Los códigos viejos con
 * prefijo propio (`TOR-0012`) conviven sin estorbar; simplemente ya no se
 * generan.
 */
class GeneradorCodigoArticulo
{
    private const PREFIJO = 'ART-';

    private const DIGITOS = 5;

    /**
     * Corre dentro de una transacción con `lockForUpdate` para serializar el
     * consecutivo, igual que `HasMonthlyFolio`: mientras un alta calcula y
     * aparta su código, otra espera hasta que la primera confirme.
     *
     * El máximo se saca en PHP sobre la parte numérica y no con `MAX(codigo)`
     * de texto: comparado como cadena, `ART-00099` gana a `ART-00100` y el
     * consecutivo se atasca repitiendo el mismo número — el mismo bug que ya
     * cobró el folio mensual al pasar de 99 en un mes.
     */
    public function siguiente(): string
    {
        return DB::transaction(function (): string {
            $max = DB::table('costos_productos')
                ->where('codigo', 'like', self::PREFIJO.'%')
                ->lockForUpdate()
                ->pluck('codigo')
                ->map(fn (string $codigo): int => (int) substr($codigo, strlen(self::PREFIJO)))
                ->max();

            return sprintf('%s%0'.self::DIGITOS.'d', self::PREFIJO, ($max ?? 0) + 1);
        });
    }

    /**
     * Si el texto tiene la forma del consecutivo. Compras teclea productos al
     * vuelo con el código que trae a la mano, y uno que se parezca a `ART-00013`
     * chocaría con el siguiente que genere el almacén.
     */
    public static function esCodigoGenerado(?string $codigo): bool
    {
        return $codigo !== null && preg_match('/^ART-\d+$/i', $codigo) === 1;
    }
}
