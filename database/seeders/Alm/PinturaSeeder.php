<?php

namespace Database\Seeders\Alm;

/**
 * Almacén de Pintura (PIN). 39 artículos, ~$1.03 M.
 *
 * Pintura contó a granel el primario y el thinner pero anotó el precio del
 * tambor de 200 litros, así que va con el prorrateo encendido.
 *
 * La columna OBRA de este layout viene con la obra a la que está asignado cada
 * bote, no con el almacén donde vive. No mueve saldo —la existencia es
 * almacén+artículo— pero el importador la escribe en la observación del renglón
 * del ajuste para no perderla.
 */
class PinturaSeeder extends CargaInicialSeeder
{
    protected function archivo(): string
    {
        return 'LAYOUT-ALMACEN-PINTURA.xlsx';
    }

    protected function prorratearEnvase(): bool
    {
        return true;
    }
}
