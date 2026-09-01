<?php

namespace Database\Seeders\Alm;

/**
 * Padrón de activos de INS (central): 7 artículos, 67 piezas, $361,734.04.
 *
 * 2 de las 67 piezas llegaron sin número de serie. Entran con un folio
 * provisional SIN-SERIE-#### y la observación que lo dice: sin serie no hay resguardo,
 * y dejarlas fuera habría abierto el almacén con el padrón incompleto.
 *
 * 1 pieza(s) traían una serie ya usada por otra del mismo artículo. Se les puso
 * un sufijo y la observación correspondiente: dos piezas no pueden ser la misma.
 */
class InsActivosSeeder extends CargaInicialActivosSeeder
{
    protected function almacen(): string
    {
        return 'INS';
    }

    /**
     * @return list<array{descripcion: string, unidad: string, area: string|null, abc: string, ubicacion: string|null, piezas: list<array<string, mixed>>}>
     */
    protected function articulos(): array
    {
        return [
            [
                'descripcion' => 'ESMERILADORA ANGULAR DE 9"',
                'unidad' => 'PZA',
                'area' => 'Herramienta',
                'abc' => 'C',
                'ubicacion' => 'INSUMOS',
                'piezas' => [
                    ['no_serie' => 'E092', 'costo' => 3508.62, 'marca' => 'Bosh', 'modelo' => null, 'id_mantenimiento' => null, 'estatus' => 'disponible', 'condicion' => 'Buen estado', 'observaciones' => null],
                    ['no_serie' => 'E017', 'costo' => 3508.62, 'marca' => 'DeWalt', 'modelo' => null, 'id_mantenimiento' => null, 'estatus' => 'disponible', 'condicion' => 'Buen estado', 'observaciones' => null],
                    ['no_serie' => 'E035', 'costo' => 3508.62, 'marca' => 'Bosh', 'modelo' => null, 'id_mantenimiento' => null, 'estatus' => 'disponible', 'condicion' => 'Buen estado', 'observaciones' => null],
                    ['no_serie' => 'E076', 'costo' => 3508.62, 'marca' => 'Bosh', 'modelo' => null, 'id_mantenimiento' => null, 'estatus' => 'disponible', 'condicion' => 'Buen estado', 'observaciones' => null],
                    ['no_serie' => 'E112', 'costo' => 3508.62, 'marca' => 'Bosh', 'modelo' => null, 'id_mantenimiento' => null, 'estatus' => 'disponible', 'condicion' => 'Buen estado', 'observaciones' => null],
                    ['no_serie' => 'E075', 'costo' => 3508.62, 'marca' => 'Bosh', 'modelo' => null, 'id_mantenimiento' => null, 'estatus' => 'disponible', 'condicion' => 'Buen estado', 'observaciones' => null],
                    ['no_serie' => 'E115', 'costo' => 3508.62, 'marca' => 'Bosh', 'modelo' => null, 'id_mantenimiento' => null, 'estatus' => 'disponible', 'condicion' => 'Buen estado', 'observaciones' => null],
                    ['no_serie' => 'E080', 'costo' => 3508.62, 'marca' => 'Bosh', 'modelo' => null, 'id_mantenimiento' => null, 'estatus' => 'disponible', 'condicion' => 'Buen estado', 'observaciones' => null],
                    ['no_serie' => 'E150', 'costo' => 3508.62, 'marca' => 'Bosh', 'modelo' => null, 'id_mantenimiento' => null, 'estatus' => 'disponible', 'condicion' => 'Buen estado', 'observaciones' => null],
                    ['no_serie' => 'E088', 'costo' => 3508.62, 'marca' => 'Bosh', 'modelo' => null, 'id_mantenimiento' => null, 'estatus' => 'disponible', 'condicion' => 'Buen estado', 'observaciones' => null],
                    ['no_serie' => 'E121', 'costo' => 3508.62, 'marca' => 'DeWalt', 'modelo' => null, 'id_mantenimiento' => null, 'estatus' => 'disponible', 'condicion' => 'Buen estado', 'observaciones' => null],
                    ['no_serie' => 'E094', 'costo' => 3508.62, 'marca' => 'Bosh', 'modelo' => null, 'id_mantenimiento' => null, 'estatus' => 'disponible', 'condicion' => 'Buen estado', 'observaciones' => null],
                    ['no_serie' => 'E140', 'costo' => 3508.62, 'marca' => 'Bosh', 'modelo' => null, 'id_mantenimiento' => null, 'estatus' => 'disponible', 'condicion' => 'Buen estado', 'observaciones' => null],
                    ['no_serie' => 'E092-2', 'costo' => 3508.62, 'marca' => 'Bosh', 'modelo' => null, 'id_mantenimiento' => null, 'estatus' => 'disponible', 'condicion' => 'Buen estado', 'observaciones' => 'La serie E092 venía repetida en el layout; se desambiguó.'],
                ],
            ],
            [
                'descripcion' => 'MINI ESMERIL DE 4 1/2',
                'unidad' => 'PZA',
                'area' => 'Herramienta',
                'abc' => 'C',
                'ubicacion' => 'INSUMOS',
                'piezas' => [
                    ['no_serie' => 'M309', 'costo' => 1534, 'marca' => 'Bosh', 'modelo' => null, 'id_mantenimiento' => null, 'estatus' => 'disponible', 'condicion' => 'Buen estado', 'observaciones' => null],
                ],
            ],
            [
                'descripcion' => 'ROTO MARTILLO O TALADRO PERCUTOR',
                'unidad' => 'PZA',
                'area' => 'Herramienta',
                'abc' => 'C',
                'ubicacion' => 'INSUMOS',
                'piezas' => [
                    ['no_serie' => 'T61', 'costo' => 2932.2, 'marca' => 'Milwaukee', 'modelo' => null, 'id_mantenimiento' => null, 'estatus' => 'disponible', 'condicion' => 'Buen estado', 'observaciones' => null],
                    ['no_serie' => 'T65', 'costo' => 2932.2, 'marca' => 'Milwaukee', 'modelo' => null, 'id_mantenimiento' => null, 'estatus' => 'disponible', 'condicion' => 'Buen estado', 'observaciones' => null],
                    ['no_serie' => 'T67', 'costo' => 2932.2, 'marca' => 'Milwaukee', 'modelo' => null, 'id_mantenimiento' => null, 'estatus' => 'disponible', 'condicion' => 'Buen estado', 'observaciones' => null],
                    ['no_serie' => 'T68', 'costo' => 2932.2, 'marca' => 'Milwaukee', 'modelo' => null, 'id_mantenimiento' => null, 'estatus' => 'disponible', 'condicion' => 'Buen estado', 'observaciones' => null],
                    ['no_serie' => 'T78', 'costo' => 2932.2, 'marca' => 'Milwaukee', 'modelo' => null, 'id_mantenimiento' => null, 'estatus' => 'disponible', 'condicion' => 'Buen estado', 'observaciones' => null],
                    ['no_serie' => 'T79', 'costo' => 2932.2, 'marca' => 'Milwaukee', 'modelo' => null, 'id_mantenimiento' => null, 'estatus' => 'disponible', 'condicion' => 'Buen estado', 'observaciones' => null],
                    ['no_serie' => 'T80', 'costo' => 2932.2, 'marca' => 'Milwaukee', 'modelo' => null, 'id_mantenimiento' => null, 'estatus' => 'disponible', 'condicion' => 'Buen estado', 'observaciones' => null],
                    ['no_serie' => 'T81', 'costo' => 2932.2, 'marca' => 'Milwaukee', 'modelo' => null, 'id_mantenimiento' => null, 'estatus' => 'disponible', 'condicion' => 'Buen estado', 'observaciones' => null],
                    ['no_serie' => 'T82', 'costo' => 2932.2, 'marca' => 'Milwaukee', 'modelo' => null, 'id_mantenimiento' => null, 'estatus' => 'disponible', 'condicion' => 'Buen estado', 'observaciones' => null],
                    ['no_serie' => 'T66', 'costo' => 2932.2, 'marca' => 'Milwaukee', 'modelo' => null, 'id_mantenimiento' => null, 'estatus' => 'disponible', 'condicion' => 'Buen estado', 'observaciones' => null],
                    ['no_serie' => 'T69', 'costo' => 2932.2, 'marca' => 'Milwaukee', 'modelo' => null, 'id_mantenimiento' => null, 'estatus' => 'disponible', 'condicion' => 'Buen estado', 'observaciones' => null],
                    ['no_serie' => 'T64', 'costo' => 2932.2, 'marca' => 'Milwaukee', 'modelo' => null, 'id_mantenimiento' => null, 'estatus' => 'disponible', 'condicion' => 'Buen estado', 'observaciones' => null],
                    ['no_serie' => 'S/N', 'costo' => 2932.2, 'marca' => 'Milwaukee', 'modelo' => null, 'id_mantenimiento' => null, 'estatus' => 'disponible', 'condicion' => 'Buen estado', 'observaciones' => null],
                    ['no_serie' => 'T72', 'costo' => 2932.2, 'marca' => 'Bosh', 'modelo' => null, 'id_mantenimiento' => null, 'estatus' => 'disponible', 'condicion' => 'Buen estado', 'observaciones' => null],
                    ['no_serie' => 'T73', 'costo' => 2932.2, 'marca' => 'Bosh', 'modelo' => null, 'id_mantenimiento' => null, 'estatus' => 'disponible', 'condicion' => 'Buen estado', 'observaciones' => null],
                    ['no_serie' => 'T74', 'costo' => 2932.2, 'marca' => 'Bosh', 'modelo' => null, 'id_mantenimiento' => null, 'estatus' => 'disponible', 'condicion' => 'Buen estado', 'observaciones' => null],
                    ['no_serie' => 'T84', 'costo' => 2932.2, 'marca' => 'Bosh', 'modelo' => null, 'id_mantenimiento' => null, 'estatus' => 'disponible', 'condicion' => 'Buen estado', 'observaciones' => null],
                    ['no_serie' => 'T87', 'costo' => 2932.2, 'marca' => 'Bosh', 'modelo' => null, 'id_mantenimiento' => null, 'estatus' => 'disponible', 'condicion' => 'Buen estado', 'observaciones' => null],
                    ['no_serie' => 'T77', 'costo' => 2932.2, 'marca' => 'Bosh', 'modelo' => null, 'id_mantenimiento' => null, 'estatus' => 'disponible', 'condicion' => 'Buen estado', 'observaciones' => null],
                    ['no_serie' => 'T59', 'costo' => 2932.2, 'marca' => 'Bosh', 'modelo' => null, 'id_mantenimiento' => null, 'estatus' => 'disponible', 'condicion' => 'Buen estado', 'observaciones' => null],
                    ['no_serie' => 'T85', 'costo' => 2932.2, 'marca' => 'Bosh', 'modelo' => null, 'id_mantenimiento' => null, 'estatus' => 'disponible', 'condicion' => 'Buen estado', 'observaciones' => null],
                ],
            ],
            [
                'descripcion' => 'RECTIFICADOR O ESMERIL RECTO',
                'unidad' => 'PZA',
                'area' => 'Herramienta',
                'abc' => 'C',
                'ubicacion' => 'INSUMOS',
                'piezas' => [
                    ['no_serie' => 'R058', 'costo' => 2448, 'marca' => 'Milwaukee', 'modelo' => null, 'id_mantenimiento' => null, 'estatus' => 'disponible', 'condicion' => 'Buen estado', 'observaciones' => null],
                    ['no_serie' => 'R039', 'costo' => 2448, 'marca' => 'Milwaukee', 'modelo' => null, 'id_mantenimiento' => null, 'estatus' => 'disponible', 'condicion' => 'Buen estado', 'observaciones' => null],
                ],
            ],
            [
                'descripcion' => 'TALADRO MAGNETICO',
                'unidad' => 'PZA',
                'area' => 'Herramienta',
                'abc' => 'C',
                'ubicacion' => 'INSUMOS',
                'piezas' => [
                    ['no_serie' => 'MG001', 'costo' => 14550, 'marca' => 'Bosh', 'modelo' => null, 'id_mantenimiento' => null, 'estatus' => 'disponible', 'condicion' => 'Buen estado', 'observaciones' => null],
                    ['no_serie' => 'MG002', 'costo' => 14550, 'marca' => 'Bosh', 'modelo' => null, 'id_mantenimiento' => null, 'estatus' => 'disponible', 'condicion' => 'Buen estado', 'observaciones' => null],
                    ['no_serie' => 'MG005', 'costo' => 14550, 'marca' => 'Bosh', 'modelo' => null, 'id_mantenimiento' => null, 'estatus' => 'disponible', 'condicion' => 'Buen estado', 'observaciones' => null],
                    ['no_serie' => 'MG007', 'costo' => 14550, 'marca' => 'Bosh', 'modelo' => null, 'id_mantenimiento' => null, 'estatus' => 'disponible', 'condicion' => 'Buen estado', 'observaciones' => null],
                    ['no_serie' => 'MG009', 'costo' => 14550, 'marca' => 'Bosh', 'modelo' => null, 'id_mantenimiento' => null, 'estatus' => 'disponible', 'condicion' => 'Buen estado', 'observaciones' => null],
                    ['no_serie' => 'MG011', 'costo' => 14550, 'marca' => null, 'modelo' => null, 'id_mantenimiento' => null, 'estatus' => 'disponible', 'condicion' => 'Buen estado', 'observaciones' => null],
                    ['no_serie' => 'MG013', 'costo' => 14550, 'marca' => null, 'modelo' => null, 'id_mantenimiento' => null, 'estatus' => 'disponible', 'condicion' => 'Buen estado', 'observaciones' => null],
                    ['no_serie' => 'MG014', 'costo' => 14550, 'marca' => null, 'modelo' => null, 'id_mantenimiento' => null, 'estatus' => 'disponible', 'condicion' => 'Buen estado', 'observaciones' => null],
                    ['no_serie' => 'MG015', 'costo' => 14550, 'marca' => null, 'modelo' => null, 'id_mantenimiento' => null, 'estatus' => 'disponible', 'condicion' => 'Buen estado', 'observaciones' => null],
                    ['no_serie' => 'MG016', 'costo' => 14550, 'marca' => 'Bosh', 'modelo' => null, 'id_mantenimiento' => null, 'estatus' => 'disponible', 'condicion' => 'Buen estado', 'observaciones' => null],
                ],
            ],
            [
                'descripcion' => 'MAQUINAS DE SOLDAR INVERTER FINEARC300',
                'unidad' => 'PZA',
                'area' => 'Herramienta',
                'abc' => 'C',
                'ubicacion' => 'INSUMOS',
                'piezas' => [
                    ['no_serie' => 'S01', 'costo' => 5237, 'marca' => 'MARAGA', 'modelo' => null, 'id_mantenimiento' => null, 'estatus' => 'disponible', 'condicion' => 'Buen estado', 'observaciones' => null],
                    ['no_serie' => 'S03', 'costo' => 5237, 'marca' => 'MARAGA', 'modelo' => null, 'id_mantenimiento' => null, 'estatus' => 'disponible', 'condicion' => 'Buen estado', 'observaciones' => null],
                    ['no_serie' => 'S06', 'costo' => 5237, 'marca' => 'MARAGA', 'modelo' => null, 'id_mantenimiento' => null, 'estatus' => 'disponible', 'condicion' => 'Buen estado', 'observaciones' => null],
                    ['no_serie' => 'S07', 'costo' => 5237, 'marca' => 'MARAGA', 'modelo' => null, 'id_mantenimiento' => null, 'estatus' => 'disponible', 'condicion' => 'Buen estado', 'observaciones' => null],
                    ['no_serie' => 'S11', 'costo' => 5237, 'marca' => 'MARAGA', 'modelo' => null, 'id_mantenimiento' => null, 'estatus' => 'disponible', 'condicion' => 'Buen estado', 'observaciones' => null],
                    ['no_serie' => 'S12', 'costo' => 5237, 'marca' => 'MARAGA', 'modelo' => null, 'id_mantenimiento' => null, 'estatus' => 'disponible', 'condicion' => 'Buen estado', 'observaciones' => null],
                    ['no_serie' => 'S14', 'costo' => 5237, 'marca' => 'MARAGA', 'modelo' => null, 'id_mantenimiento' => null, 'estatus' => 'disponible', 'condicion' => 'Buen estado', 'observaciones' => null],
                    ['no_serie' => 'S15', 'costo' => 5237, 'marca' => 'MARAGA', 'modelo' => null, 'id_mantenimiento' => null, 'estatus' => 'disponible', 'condicion' => 'Buen estado', 'observaciones' => null],
                    ['no_serie' => 'S16', 'costo' => 5237, 'marca' => 'MARAGA', 'modelo' => null, 'id_mantenimiento' => null, 'estatus' => 'disponible', 'condicion' => 'Buen estado', 'observaciones' => null],
                    ['no_serie' => 'S17', 'costo' => 5237, 'marca' => 'MARAGA', 'modelo' => null, 'id_mantenimiento' => null, 'estatus' => 'disponible', 'condicion' => 'Buen estado', 'observaciones' => null],
                    ['no_serie' => 'S18', 'costo' => 5237, 'marca' => 'MARAGA', 'modelo' => null, 'id_mantenimiento' => null, 'estatus' => 'disponible', 'condicion' => 'Buen estado', 'observaciones' => null],
                    ['no_serie' => 'S19', 'costo' => 5237, 'marca' => 'MARAGA', 'modelo' => null, 'id_mantenimiento' => null, 'estatus' => 'disponible', 'condicion' => 'Buen estado', 'observaciones' => null],
                    ['no_serie' => 'S20', 'costo' => 5237, 'marca' => 'MARAGA', 'modelo' => null, 'id_mantenimiento' => null, 'estatus' => 'disponible', 'condicion' => 'Buen estado', 'observaciones' => null],
                    ['no_serie' => 'S21', 'costo' => 5237, 'marca' => 'MARAGA', 'modelo' => null, 'id_mantenimiento' => null, 'estatus' => 'disponible', 'condicion' => 'Buen estado', 'observaciones' => null],
                    ['no_serie' => 'S22', 'costo' => 5237, 'marca' => 'MARAGA', 'modelo' => null, 'id_mantenimiento' => null, 'estatus' => 'disponible', 'condicion' => 'Buen estado', 'observaciones' => null],
                    ['no_serie' => 'S23', 'costo' => 5237, 'marca' => 'MARAGA', 'modelo' => null, 'id_mantenimiento' => null, 'estatus' => 'disponible', 'condicion' => 'Buen estado', 'observaciones' => null],
                    ['no_serie' => 'S24', 'costo' => 5237, 'marca' => 'MARAGA', 'modelo' => null, 'id_mantenimiento' => null, 'estatus' => 'disponible', 'condicion' => 'Buen estado', 'observaciones' => null],
                ],
            ],
            [
                'descripcion' => 'GATO HIDRAULICO 50 TON',
                'unidad' => 'PZA',
                'area' => 'Herramienta',
                'abc' => 'C',
                'ubicacion' => 'INSUMOS',
                'piezas' => [
                    ['no_serie' => 'SIN-SERIE-0064', 'costo' => 5039.08, 'marca' => 'MIKELS', 'modelo' => null, 'id_mantenimiento' => null, 'estatus' => 'disponible', 'condicion' => 'Buen estado', 'observaciones' => 'Sin número de serie en el layout; folio provisional.'],
                    ['no_serie' => 'SIN-SERIE-0065', 'costo' => 5039.08, 'marca' => 'MIKELS', 'modelo' => null, 'id_mantenimiento' => null, 'estatus' => 'disponible', 'condicion' => 'Buen estado', 'observaciones' => 'Sin número de serie en el layout; folio provisional.'],
                ],
            ],
        ];
    }
}
