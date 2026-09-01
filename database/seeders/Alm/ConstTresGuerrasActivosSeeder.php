<?php

namespace Database\Seeders\Alm;

/**
 * Padrón de activos de CONST (TRES GUERRAS): 5 artículos, 8 piezas, $179,159.00.
 *
 * 8 de las 8 piezas llegaron sin número de serie. Entran con un folio
 * provisional SIN-SERIE-#### y la observación que lo dice: sin serie no hay resguardo,
 * y dejarlas fuera habría abierto el almacén con el padrón incompleto.
 */
class ConstTresGuerrasActivosSeeder extends CargaInicialActivosSeeder
{
    protected function almacen(): string
    {
        return 'CONST';
    }

    protected function obra(): ?string
    {
        return 'TRES GUERRAS';
    }

    /**
     * @return list<array{descripcion: string, unidad: string, area: string|null, abc: string, ubicacion: string|null, piezas: list<array<string, mixed>>}>
     */
    protected function articulos(): array
    {
        return [
            [
                'descripcion' => 'COMPRESOR INDUSTRIAL',
                'unidad' => 'PZA',
                'area' => 'Herramienta',
                'abc' => 'C',
                'ubicacion' => 'OBRA',
                'piezas' => [
                    ['no_serie' => 'SIN-SERIE-0077', 'costo' => 36315, 'marca' => null, 'modelo' => null, 'id_mantenimiento' => null, 'estatus' => 'prestado', 'condicion' => null, 'observaciones' => 'Sin número de serie en el layout; folio provisional.'],
                ],
            ],
            [
                'descripcion' => 'RECTIFICADOR O ESMERIL RECTO',
                'unidad' => 'PZA',
                'area' => 'Herramienta',
                'abc' => 'C',
                'ubicacion' => 'OBRA',
                'piezas' => [
                    ['no_serie' => 'SIN-SERIE-0078', 'costo' => 2448, 'marca' => null, 'modelo' => null, 'id_mantenimiento' => null, 'estatus' => 'prestado', 'condicion' => null, 'observaciones' => 'Sin número de serie en el layout; folio provisional.'],
                    ['no_serie' => 'SIN-SERIE-0079', 'costo' => 2448, 'marca' => null, 'modelo' => null, 'id_mantenimiento' => null, 'estatus' => 'prestado', 'condicion' => null, 'observaciones' => 'Sin número de serie en el layout; folio provisional.'],
                    ['no_serie' => 'SIN-SERIE-0080', 'costo' => 2448, 'marca' => null, 'modelo' => null, 'id_mantenimiento' => null, 'estatus' => 'prestado', 'condicion' => null, 'observaciones' => 'Sin número de serie en el layout; folio provisional.'],
                ],
            ],
            [
                'descripcion' => 'PISTOLA O LLAVE DE IMPACTO NEUMATICA',
                'unidad' => 'PZA',
                'area' => 'Herramienta',
                'abc' => 'C',
                'ubicacion' => 'OBRA',
                'piezas' => [
                    ['no_serie' => 'SIN-SERIE-0081', 'costo' => 3500, 'marca' => null, 'modelo' => null, 'id_mantenimiento' => null, 'estatus' => 'prestado', 'condicion' => null, 'observaciones' => 'Sin número de serie en el layout; folio provisional.'],
                ],
            ],
            [
                'descripcion' => 'PISTOLA CONTROLBOLT',
                'unidad' => 'PZA',
                'area' => 'Herramienta',
                'abc' => 'C',
                'ubicacion' => 'OBRA',
                'piezas' => [
                    ['no_serie' => 'SIN-SERIE-0082', 'costo' => 65000, 'marca' => null, 'modelo' => null, 'id_mantenimiento' => null, 'estatus' => 'prestado', 'condicion' => null, 'observaciones' => 'Sin número de serie en el layout; folio provisional.'],
                    ['no_serie' => 'SIN-SERIE-0083', 'costo' => 65000, 'marca' => null, 'modelo' => null, 'id_mantenimiento' => null, 'estatus' => 'prestado', 'condicion' => null, 'observaciones' => 'Sin número de serie en el layout; folio provisional.'],
                ],
            ],
            [
                'descripcion' => 'MULTIPLICADOR DE TORQUE',
                'unidad' => 'PZA',
                'area' => 'Herramienta',
                'abc' => 'C',
                'ubicacion' => 'OBRA',
                'piezas' => [
                    ['no_serie' => 'SIN-SERIE-0084', 'costo' => 2000, 'marca' => null, 'modelo' => null, 'id_mantenimiento' => null, 'estatus' => 'prestado', 'condicion' => null, 'observaciones' => 'Sin número de serie en el layout; folio provisional.'],
                ],
            ],
        ];
    }
}
