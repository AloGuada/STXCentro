<?php

namespace Database\Seeders\Alm;

/**
 * Almacén de Soldadura (SOL). 11 artículos, ~$287 mil.
 *
 * Todo en KG y a costo por kilo, sin obra asignada: es el layout limpio de los
 * dos primeros que entregaron.
 */
class SoldaduraSeeder extends CargaInicialSeeder
{
    protected function archivo(): string
    {
        return 'LAYOUT-ALMACEN-SOLDADURA.xlsx';
    }
}
