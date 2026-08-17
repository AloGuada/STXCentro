<?php

namespace App\Services\Cob;

use App\Exceptions\Cob\CatalogoSbcVacioException;

/**
 * Resuelve los parámetros del IMSS (SBC diario, costo del DOF por m² y prima de
 * riesgo) para un año dado. Reemplaza el `getSBCForYear` hardcodeado del
 * bosquejo: los valores viven en `cob_icsoe_sbc_anios`.
 *
 * Resolución: año exacto → el año capturado más alto que sea menor (floor) →
 * el año mínimo del catálogo. Así un año futuro sin capturar hereda el último
 * valor conocido en vez de quedarse en cero.
 *
 * La clase es pura: recibe las filas ya cargadas, no consulta la base de datos.
 */
class SbcResolver
{
    /** @var array<int, array{sbc: float, costo_m2: float, prima_riesgo: float}> */
    private array $porAnio = [];

    /** @var array<int, int> */
    private array $anios = [];

    /**
     * @param  iterable<int, array{anio: int|string, sbc: float|int|string, costo_m2?: float|int|string|null, prima_riesgo?: float|int|string|null}>  $filas
     */
    public function __construct(iterable $filas)
    {
        foreach ($filas as $fila) {
            $this->porAnio[(int) $fila['anio']] = [
                'sbc' => (float) $fila['sbc'],
                'costo_m2' => (float) ($fila['costo_m2'] ?? 0),
                'prima_riesgo' => (float) ($fila['prima_riesgo'] ?? 0),
            ];
        }

        ksort($this->porAnio);
        $this->anios = array_keys($this->porAnio);
    }

    public function sbc(int $anio): float
    {
        return $this->fila($anio)['sbc'];
    }

    public function costoM2(int $anio): float
    {
        return $this->fila($anio)['costo_m2'];
    }

    public function primaRiesgo(int $anio): float
    {
        return $this->fila($anio)['prima_riesgo'];
    }

    public function estaVacio(): bool
    {
        return $this->anios === [];
    }

    /**
     * @return array{sbc: float, costo_m2: float, prima_riesgo: float}
     */
    private function fila(int $anio): array
    {
        if ($this->estaVacio()) {
            throw new CatalogoSbcVacioException;
        }

        if (isset($this->porAnio[$anio])) {
            return $this->porAnio[$anio];
        }

        $elegido = $this->anios[0];

        foreach ($this->anios as $candidato) {
            if ($candidato > $anio) {
                break;
            }

            $elegido = $candidato;
        }

        return $this->porAnio[$elegido];
    }
}
