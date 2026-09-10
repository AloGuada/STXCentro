<?php

namespace Database\Seeders\Alm;

/**
 * Padrón de activos de CONST (SIX PARKS CANCUN): 2 artículos, 2 piezas, $38,000.00.
 *
 * 2 de las 2 piezas llegaron sin número de serie. Entran con un folio
 * provisional SIN-SERIE-#### y la observación que lo dice: sin serie no hay resguardo,
 * y dejarlas fuera habría abierto el almacén con el padrón incompleto.
 */
class ConstSixParksCancunActivosSeeder extends CargaInicialActivosSeeder
{
    protected function almacen(): string
    {
        return 'CONST';
    }

    protected function obra(): ?string
    {
        return 'SIX PARKS CANCUN';
    }

    /**
     * @return list<array{descripcion: string, unidad: string, area: string|null, abc: string, ubicacion: string|null, piezas: list<array<string, mixed>>}>
     */
    protected function articulos(): array
    {
        return [
            [
                'descripcion' => 'ESTACION TOTAL TOPOGRAFICA',
                'unidad' => 'PZA',
                'area' => 'Herramienta',
                'abc' => 'C',
                'ubicacion' => 'OBRA',
                'piezas' => [
                    ['no_serie' => 'SIN-SERIE-0075', 'costo' => 36500, 'marca' => null, 'modelo' => null, 'id_mantenimiento' => null, 'estatus' => 'prestado', 'condicion' => null, 'observaciones' => 'Sin número de serie en el layout; folio provisional.'],
                ],
            ],
            [
                'descripcion' => 'TRIPIE',
                'unidad' => 'PZA',
                'area' => 'Herramienta',
                'abc' => 'C',
                'ubicacion' => 'OBRA',
                'piezas' => [
                    ['no_serie' => 'SIN-SERIE-0076', 'costo' => 1500, 'marca' => null, 'modelo' => null, 'id_mantenimiento' => null, 'estatus' => 'prestado', 'condicion' => null, 'observaciones' => 'Sin número de serie en el layout; folio provisional.'],
                ],
            ],
        ];
    }
}
