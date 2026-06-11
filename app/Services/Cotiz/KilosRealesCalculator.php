<?php

namespace App\Services\Cotiz;

/**
 * Kg reales por tipo de corte de una tarjeta. Port de `calcularKgPorTipoCorte`
 * de `src/lib/tarjetaTotales.ts` (M032/M034).
 *
 * Reglas: las filas FIJAS (porcentual NULL) suman directo a su tipo_corte y son la base.
 * Las filas PORCENTUALES aportan kilos virtuales = porcentual × Σ(fijas) a su tipo_corte.
 */
class KilosRealesCalculator
{
    private const TIPOS = ['TIRAS', 'RAZ', 'KG', 'CNX'];

    /**
     * @param  list<array{categoria_id: int, tipo_corte: string, porcentual: float|int|string|null}>  $categorias
     * @param  list<array{categoria_id: int, kilos: float|int|string}>  $celdas
     * @return array{TIRAS: float, RAZ: float, KG: float, CNX: float}
     */
    public function porTipoCorte(array $categorias, array $celdas): array
    {
        $resultado = ['TIRAS' => 0.0, 'RAZ' => 0.0, 'KG' => 0.0, 'CNX' => 0.0];

        $tipoPorCat = [];
        $porcentualPorCat = [];
        foreach ($categorias as $categoria) {
            $tipoPorCat[$categoria['categoria_id']] = $categoria['tipo_corte'];
            if ($categoria['porcentual'] !== null) {
                $porcentualPorCat[$categoria['categoria_id']] = (float) $categoria['porcentual'];
            }
        }

        // Σ de filas FIJAS (porcentual NULL): alimentan su tipo_corte y son la base de los %.
        $sumaFijas = 0.0;
        foreach ($celdas as $celda) {
            $catId = $celda['categoria_id'];
            if (array_key_exists($catId, $porcentualPorCat)) {
                continue; // por seguridad: una categoría porcentual no debería tener celdas
            }
            $tipo = $tipoPorCat[$catId] ?? null;
            if (in_array($tipo, self::TIPOS, true)) {
                $resultado[$tipo] += (float) $celda['kilos'];
                $sumaFijas += (float) $celda['kilos'];
            }
        }

        // Virtuales: porcentual × Σ(fijas) al tipo_corte de cada categoría porcentual.
        foreach ($porcentualPorCat as $catId => $porcentual) {
            $tipo = $tipoPorCat[$catId] ?? null;
            if (in_array($tipo, self::TIPOS, true)) {
                $resultado[$tipo] += $porcentual * $sumaFijas;
            }
        }

        return $resultado;
    }

    /**
     * Suma total de los cuatro tipos de corte.
     *
     * @param  array{TIRAS: float, RAZ: float, KG: float, CNX: float}  $porTipoCorte
     */
    public function total(array $porTipoCorte): float
    {
        return $porTipoCorte['TIRAS'] + $porTipoCorte['RAZ'] + $porTipoCorte['KG'] + $porTipoCorte['CNX'];
    }
}
