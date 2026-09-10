<?php

namespace Database\Seeders\Alm;

/**
 * Padrón de activos de MON (central): 21 artículos, 112 piezas, $1,467,444.74.
 *
 * 63 de las 112 piezas llegaron sin número de serie. Entran con un folio
 * provisional SIN-SERIE-#### y la observación que lo dice: sin serie no hay resguardo,
 * y dejarlas fuera habría abierto el almacén con el padrón incompleto.
 */
class MonActivosSeeder extends CargaInicialActivosSeeder
{
    protected function almacen(): string
    {
        return 'MON';
    }

    /**
     * @return list<array{descripcion: string, unidad: string, area: string|null, abc: string, ubicacion: string|null, piezas: list<array<string, mixed>>}>
     */
    protected function articulos(): array
    {
        return [
            [
                'descripcion' => 'ESMERILADORA ANGULAR 9',
                'unidad' => 'PZA',
                'area' => 'Herramienta',
                'abc' => 'C',
                'ubicacion' => null,
                'piezas' => [
                    ['no_serie' => 'SIN-SERIE-0001', 'costo' => 3508.62, 'marca' => 'Milwakee', 'modelo' => null, 'id_mantenimiento' => null, 'estatus' => 'disponible', 'condicion' => 'Uso normal', 'observaciones' => 'Sin número de serie en el layout; folio provisional.'],
                    ['no_serie' => 'CE10', 'costo' => 3508.62, 'marca' => 'Truper', 'modelo' => null, 'id_mantenimiento' => null, 'estatus' => 'disponible', 'condicion' => 'Uso normal', 'observaciones' => null],
                    ['no_serie' => 'SIN-SERIE-0002', 'costo' => 3508.62, 'marca' => 'DeWalt', 'modelo' => null, 'id_mantenimiento' => null, 'estatus' => 'disponible', 'condicion' => 'Uso normal', 'observaciones' => 'Sin número de serie en el layout; folio provisional.'],
                    ['no_serie' => 'CE7', 'costo' => 3508.62, 'marca' => 'DeWalt', 'modelo' => null, 'id_mantenimiento' => null, 'estatus' => 'disponible', 'condicion' => 'Uso normal', 'observaciones' => null],
                    ['no_serie' => 'CE20', 'costo' => 3508.62, 'marca' => 'DeWalt', 'modelo' => null, 'id_mantenimiento' => null, 'estatus' => 'disponible', 'condicion' => 'Uso normal', 'observaciones' => null],
                    ['no_serie' => 'CE17', 'costo' => 3508.62, 'marca' => 'Makita', 'modelo' => null, 'id_mantenimiento' => null, 'estatus' => 'disponible', 'condicion' => 'Uso normal', 'observaciones' => null],
                    ['no_serie' => 'SIN-SERIE-0003', 'costo' => 3508.62, 'marca' => 'Makita', 'modelo' => null, 'id_mantenimiento' => null, 'estatus' => 'disponible', 'condicion' => 'Uso normal', 'observaciones' => 'Sin número de serie en el layout; folio provisional.'],
                    ['no_serie' => 'CE13', 'costo' => 3508.62, 'marca' => 'Makita', 'modelo' => null, 'id_mantenimiento' => null, 'estatus' => 'disponible', 'condicion' => 'Uso normal', 'observaciones' => null],
                    ['no_serie' => 'CE4', 'costo' => 3508.62, 'marca' => 'Makita', 'modelo' => null, 'id_mantenimiento' => null, 'estatus' => 'disponible', 'condicion' => 'Uso normal', 'observaciones' => null],
                    ['no_serie' => 'CE3', 'costo' => 3508.62, 'marca' => 'Bosch', 'modelo' => null, 'id_mantenimiento' => null, 'estatus' => 'disponible', 'condicion' => 'Uso normal', 'observaciones' => null],
                    ['no_serie' => 'CE15', 'costo' => 3508.62, 'marca' => 'Bosch', 'modelo' => null, 'id_mantenimiento' => null, 'estatus' => 'disponible', 'condicion' => 'Uso normal', 'observaciones' => null],
                    ['no_serie' => 'CE2', 'costo' => 3508.62, 'marca' => 'Bosch', 'modelo' => null, 'id_mantenimiento' => null, 'estatus' => 'disponible', 'condicion' => 'Uso normal', 'observaciones' => null],
                    ['no_serie' => 'CE19', 'costo' => 3508.62, 'marca' => 'Bosch', 'modelo' => null, 'id_mantenimiento' => null, 'estatus' => 'disponible', 'condicion' => 'Uso normal', 'observaciones' => null],
                    ['no_serie' => 'CE8', 'costo' => 3508.62, 'marca' => 'Bosch', 'modelo' => null, 'id_mantenimiento' => null, 'estatus' => 'disponible', 'condicion' => 'Uso normal', 'observaciones' => null],
                    ['no_serie' => 'CE18', 'costo' => 3508.62, 'marca' => 'Bosch', 'modelo' => null, 'id_mantenimiento' => null, 'estatus' => 'disponible', 'condicion' => 'Uso normal', 'observaciones' => null],
                    ['no_serie' => 'CE11', 'costo' => 3508.62, 'marca' => 'Bosch', 'modelo' => null, 'id_mantenimiento' => null, 'estatus' => 'disponible', 'condicion' => 'Uso normal', 'observaciones' => null],
                    ['no_serie' => 'CE16', 'costo' => 3508.62, 'marca' => 'Bosch', 'modelo' => null, 'id_mantenimiento' => null, 'estatus' => 'disponible', 'condicion' => 'Uso normal', 'observaciones' => null],
                    ['no_serie' => 'CE14', 'costo' => 3508.62, 'marca' => 'Bosch', 'modelo' => null, 'id_mantenimiento' => null, 'estatus' => 'disponible', 'condicion' => 'Uso normal', 'observaciones' => null],
                    ['no_serie' => 'SIN-SERIE-0004', 'costo' => 3508.62, 'marca' => 'Bosch', 'modelo' => null, 'id_mantenimiento' => null, 'estatus' => 'disponible', 'condicion' => 'Uso normal', 'observaciones' => 'Sin número de serie en el layout; folio provisional.'],
                    ['no_serie' => 'SIN-SERIE-0005', 'costo' => 3508.62, 'marca' => 'Bosch', 'modelo' => null, 'id_mantenimiento' => null, 'estatus' => 'disponible', 'condicion' => 'Uso normal', 'observaciones' => 'Sin número de serie en el layout; folio provisional.'],
                    ['no_serie' => 'SIN-SERIE-0006', 'costo' => 3508.62, 'marca' => 'Bosch', 'modelo' => null, 'id_mantenimiento' => null, 'estatus' => 'disponible', 'condicion' => 'Uso normal', 'observaciones' => 'Sin número de serie en el layout; folio provisional.'],
                    ['no_serie' => 'SIN-SERIE-0007', 'costo' => 3508.62, 'marca' => 'Bosch', 'modelo' => null, 'id_mantenimiento' => null, 'estatus' => 'disponible', 'condicion' => 'Uso normal', 'observaciones' => 'Sin número de serie en el layout; folio provisional.'],
                    ['no_serie' => 'SIN-SERIE-0008', 'costo' => 3508.62, 'marca' => 'Bosch', 'modelo' => null, 'id_mantenimiento' => null, 'estatus' => 'disponible', 'condicion' => 'Uso normal', 'observaciones' => 'Sin número de serie en el layout; folio provisional.'],
                ],
            ],
            [
                'descripcion' => 'RECTIFICADOR O ESMERIL RECTO',
                'unidad' => 'PZA',
                'area' => 'Herramienta',
                'abc' => 'C',
                'ubicacion' => null,
                'piezas' => [
                    ['no_serie' => 'SIN-SERIE-0009', 'costo' => 2448, 'marca' => 'DeWalt', 'modelo' => null, 'id_mantenimiento' => null, 'estatus' => 'disponible', 'condicion' => 'Uso normal', 'observaciones' => 'Sin número de serie en el layout; folio provisional.'],
                    ['no_serie' => 'CR15', 'costo' => 2448, 'marca' => 'DeWalt', 'modelo' => null, 'id_mantenimiento' => null, 'estatus' => 'disponible', 'condicion' => 'Uso normal', 'observaciones' => null],
                    ['no_serie' => 'CR16', 'costo' => 2448, 'marca' => 'DeWalt', 'modelo' => null, 'id_mantenimiento' => null, 'estatus' => 'disponible', 'condicion' => 'Uso normal', 'observaciones' => null],
                    ['no_serie' => 'CR12', 'costo' => 2448, 'marca' => 'DeWalt', 'modelo' => null, 'id_mantenimiento' => null, 'estatus' => 'disponible', 'condicion' => 'Uso normal', 'observaciones' => null],
                    ['no_serie' => 'CR4', 'costo' => 2448, 'marca' => 'DeWalt', 'modelo' => null, 'id_mantenimiento' => null, 'estatus' => 'disponible', 'condicion' => 'Uso normal', 'observaciones' => null],
                    ['no_serie' => 'CR14', 'costo' => 2448, 'marca' => 'DeWalt', 'modelo' => null, 'id_mantenimiento' => null, 'estatus' => 'disponible', 'condicion' => 'Uso normal', 'observaciones' => null],
                    ['no_serie' => 'CR7', 'costo' => 2448, 'marca' => 'DeWalt', 'modelo' => null, 'id_mantenimiento' => null, 'estatus' => 'disponible', 'condicion' => 'Uso normal', 'observaciones' => null],
                    ['no_serie' => 'CR2', 'costo' => 2448, 'marca' => 'DeWalt', 'modelo' => null, 'id_mantenimiento' => null, 'estatus' => 'disponible', 'condicion' => null, 'observaciones' => null],
                    ['no_serie' => 'CR8', 'costo' => 2448, 'marca' => 'Truper', 'modelo' => null, 'id_mantenimiento' => null, 'estatus' => 'disponible', 'condicion' => 'Uso normal', 'observaciones' => null],
                    ['no_serie' => 'SIN-SERIE-0010', 'costo' => 2448, 'marca' => 'Makita', 'modelo' => null, 'id_mantenimiento' => null, 'estatus' => 'disponible', 'condicion' => 'Uso normal', 'observaciones' => 'Sin número de serie en el layout; folio provisional.'],
                ],
            ],
            [
                'descripcion' => 'MINI ESMERIL DE 4 1/2',
                'unidad' => 'PZA',
                'area' => 'Herramienta',
                'abc' => 'C',
                'ubicacion' => null,
                'piezas' => [
                    ['no_serie' => 'CME14', 'costo' => 1534, 'marca' => 'DeWalt', 'modelo' => null, 'id_mantenimiento' => null, 'estatus' => 'disponible', 'condicion' => 'Uso normal', 'observaciones' => null],
                    ['no_serie' => 'SIN-SERIE-0011', 'costo' => 1534, 'marca' => 'DeWalt', 'modelo' => null, 'id_mantenimiento' => null, 'estatus' => 'disponible', 'condicion' => 'Uso normal', 'observaciones' => 'Sin número de serie en el layout; folio provisional.'],
                    ['no_serie' => 'SIN-SERIE-0012', 'costo' => 1534, 'marca' => 'DeWalt', 'modelo' => null, 'id_mantenimiento' => null, 'estatus' => 'disponible', 'condicion' => null, 'observaciones' => 'Sin número de serie en el layout; folio provisional.'],
                    ['no_serie' => 'CME7', 'costo' => 1534, 'marca' => 'Bosch', 'modelo' => null, 'id_mantenimiento' => null, 'estatus' => 'disponible', 'condicion' => 'Uso normal', 'observaciones' => null],
                    ['no_serie' => 'CME11', 'costo' => 1534, 'marca' => 'Bosch', 'modelo' => null, 'id_mantenimiento' => null, 'estatus' => 'disponible', 'condicion' => null, 'observaciones' => null],
                    ['no_serie' => 'CM23', 'costo' => 1534, 'marca' => 'Truper', 'modelo' => null, 'id_mantenimiento' => null, 'estatus' => 'disponible', 'condicion' => 'Uso normal', 'observaciones' => null],
                    ['no_serie' => 'CM252', 'costo' => 1534, 'marca' => 'Black Decker', 'modelo' => null, 'id_mantenimiento' => null, 'estatus' => 'disponible', 'condicion' => null, 'observaciones' => null],
                ],
            ],
            [
                'descripcion' => 'SIERRA SABLE',
                'unidad' => 'PZA',
                'area' => 'Herramienta',
                'abc' => 'C',
                'ubicacion' => null,
                'piezas' => [
                    ['no_serie' => 'SIN-SERIE-0013', 'costo' => 2252.59, 'marca' => 'Truper', 'modelo' => null, 'id_mantenimiento' => null, 'estatus' => 'disponible', 'condicion' => 'Uso normal', 'observaciones' => 'Sin número de serie en el layout; folio provisional.'],
                ],
            ],
            [
                'descripcion' => 'PIJADORA O ATORNILLADOR',
                'unidad' => 'PZA',
                'area' => 'Herramienta',
                'abc' => 'C',
                'ubicacion' => null,
                'piezas' => [
                    ['no_serie' => 'CP3', 'costo' => 2378, 'marca' => 'DeWalt', 'modelo' => null, 'id_mantenimiento' => null, 'estatus' => 'disponible', 'condicion' => 'Uso normal', 'observaciones' => null],
                    ['no_serie' => 'CP1', 'costo' => 2378, 'marca' => 'DeWalt', 'modelo' => null, 'id_mantenimiento' => null, 'estatus' => 'disponible', 'condicion' => null, 'observaciones' => null],
                    ['no_serie' => 'CP5', 'costo' => 2378, 'marca' => 'DeWalt', 'modelo' => null, 'id_mantenimiento' => null, 'estatus' => 'disponible', 'condicion' => null, 'observaciones' => null],
                    ['no_serie' => 'SIN-SERIE-0014', 'costo' => 2378, 'marca' => 'DeWalt', 'modelo' => null, 'id_mantenimiento' => null, 'estatus' => 'disponible', 'condicion' => 'Uso normal', 'observaciones' => 'Sin número de serie en el layout; folio provisional.'],
                ],
            ],
            [
                'descripcion' => 'CORTADORA DE BANCO',
                'unidad' => 'PZA',
                'area' => 'Herramienta',
                'abc' => 'C',
                'ubicacion' => null,
                'piezas' => [
                    ['no_serie' => 'SIN-SERIE-0015', 'costo' => 3684.96, 'marca' => 'Maraga', 'modelo' => null, 'id_mantenimiento' => null, 'estatus' => 'disponible', 'condicion' => 'Uso normal', 'observaciones' => 'Sin número de serie en el layout; folio provisional.'],
                    ['no_serie' => 'SIN-SERIE-0016', 'costo' => 3684.96, 'marca' => 'Bosch', 'modelo' => null, 'id_mantenimiento' => null, 'estatus' => 'disponible', 'condicion' => 'Uso normal', 'observaciones' => 'Sin número de serie en el layout; folio provisional.'],
                    ['no_serie' => 'SIN-SERIE-0017', 'costo' => 3684.96, 'marca' => 'Bosch', 'modelo' => null, 'id_mantenimiento' => null, 'estatus' => 'disponible', 'condicion' => 'Uso normal', 'observaciones' => 'Sin número de serie en el layout; folio provisional.'],
                    ['no_serie' => 'SIN-SERIE-0018', 'costo' => 3684.96, 'marca' => 'Bosch', 'modelo' => null, 'id_mantenimiento' => null, 'estatus' => 'disponible', 'condicion' => 'Uso normal', 'observaciones' => 'Sin número de serie en el layout; folio provisional.'],
                ],
            ],
            [
                'descripcion' => 'PISTOLA DE IMPACTO O DE TORQUE',
                'unidad' => 'PZA',
                'area' => 'Herramienta',
                'abc' => 'C',
                'ubicacion' => null,
                'piezas' => [
                    ['no_serie' => 'SIN-SERIE-0019', 'costo' => 2384, 'marca' => 'Milwakee', 'modelo' => null, 'id_mantenimiento' => null, 'estatus' => 'disponible', 'condicion' => 'Uso normal', 'observaciones' => 'Sin número de serie en el layout; folio provisional.'],
                ],
            ],
            [
                'descripcion' => 'PISTOLA EPOXICO (SIN PILA)',
                'unidad' => 'PZA',
                'area' => 'Herramienta',
                'abc' => 'C',
                'ubicacion' => null,
                'piezas' => [
                    ['no_serie' => '#1', 'costo' => 3200, 'marca' => 'Hilti', 'modelo' => null, 'id_mantenimiento' => null, 'estatus' => 'disponible', 'condicion' => 'Uso normal', 'observaciones' => null],
                    ['no_serie' => '#2', 'costo' => 3200, 'marca' => 'Hilti', 'modelo' => null, 'id_mantenimiento' => null, 'estatus' => 'disponible', 'condicion' => 'Uso normal', 'observaciones' => null],
                ],
            ],
            [
                'descripcion' => 'TALADRO MAGNETICO',
                'unidad' => 'PZA',
                'area' => 'Herramienta',
                'abc' => 'C',
                'ubicacion' => null,
                'piezas' => [
                    ['no_serie' => 'SIN-SERIE-0020', 'costo' => 14550, 'marca' => 'Milwakee', 'modelo' => null, 'id_mantenimiento' => null, 'estatus' => 'disponible', 'condicion' => 'Uso normal', 'observaciones' => 'Sin número de serie en el layout; folio provisional.'],
                ],
            ],
            [
                'descripcion' => 'SOPLETE MULTIFLAMA',
                'unidad' => 'PZA',
                'area' => 'Herramienta',
                'abc' => 'C',
                'ubicacion' => null,
                'piezas' => [
                    ['no_serie' => 'SIN-SERIE-0021', 'costo' => 1246.01, 'marca' => 'Infra', 'modelo' => null, 'id_mantenimiento' => null, 'estatus' => 'disponible', 'condicion' => 'Uso normal', 'observaciones' => 'Sin número de serie en el layout; folio provisional.'],
                ],
            ],
            [
                'descripcion' => 'HORNO PORTATIL P/SOLDADURA',
                'unidad' => 'PZA',
                'area' => 'Herramienta',
                'abc' => 'C',
                'ubicacion' => null,
                'piezas' => [
                    ['no_serie' => 'SIN-SERIE-0022', 'costo' => 1970, 'marca' => 'WELD 500', 'modelo' => null, 'id_mantenimiento' => null, 'estatus' => 'disponible', 'condicion' => 'Uso normal', 'observaciones' => 'Sin número de serie en el layout; folio provisional.'],
                    ['no_serie' => 'SIN-SERIE-0023', 'costo' => 1970, 'marca' => 'WELD 500', 'modelo' => null, 'id_mantenimiento' => null, 'estatus' => 'disponible', 'condicion' => 'Uso normal', 'observaciones' => 'Sin número de serie en el layout; folio provisional.'],
                    ['no_serie' => 'SIN-SERIE-0024', 'costo' => 1970, 'marca' => 'WELD 500', 'modelo' => null, 'id_mantenimiento' => null, 'estatus' => 'disponible', 'condicion' => 'Uso normal', 'observaciones' => 'Sin número de serie en el layout; folio provisional.'],
                    ['no_serie' => 'SIN-SERIE-0025', 'costo' => 1970, 'marca' => 'S/M', 'modelo' => null, 'id_mantenimiento' => null, 'estatus' => 'disponible', 'condicion' => 'Uso normal', 'observaciones' => 'Sin número de serie en el layout; folio provisional.'],
                ],
            ],
            [
                'descripcion' => 'ESTACION NIVELADORA',
                'unidad' => 'PZA',
                'area' => 'Herramienta',
                'abc' => 'C',
                'ubicacion' => null,
                'piezas' => [
                    ['no_serie' => 'SIN-SERIE-0026', 'costo' => 3500, 'marca' => 'S/M', 'modelo' => null, 'id_mantenimiento' => null, 'estatus' => 'disponible', 'condicion' => 'Uso normal', 'observaciones' => 'Sin número de serie en el layout; folio provisional.'],
                ],
            ],
            [
                'descripcion' => 'POLIPASTO 1 TONE',
                'unidad' => 'PZA',
                'area' => 'Herramienta',
                'abc' => 'C',
                'ubicacion' => null,
                'piezas' => [
                    ['no_serie' => 'SIN-SERIE-0027', 'costo' => 1767.24, 'marca' => 'Truper', 'modelo' => null, 'id_mantenimiento' => null, 'estatus' => 'disponible', 'condicion' => 'Uso normal', 'observaciones' => 'Sin número de serie en el layout; folio provisional.'],
                    ['no_serie' => 'SIN-SERIE-0028', 'costo' => 1767.24, 'marca' => 'Truper', 'modelo' => null, 'id_mantenimiento' => null, 'estatus' => 'disponible', 'condicion' => 'Uso normal', 'observaciones' => 'Sin número de serie en el layout; folio provisional.'],
                    ['no_serie' => 'SIN-SERIE-0029', 'costo' => 1767.24, 'marca' => 's/m', 'modelo' => null, 'id_mantenimiento' => null, 'estatus' => 'disponible', 'condicion' => 'Uso normal', 'observaciones' => 'Sin número de serie en el layout; folio provisional.'],
                ],
            ],
            [
                'descripcion' => 'POLIPASTO 2 TONE',
                'unidad' => 'PZA',
                'area' => 'Herramienta',
                'abc' => 'C',
                'ubicacion' => null,
                'piezas' => [
                    ['no_serie' => 'SIN-SERIE-0030', 'costo' => 2456.9, 'marca' => 'Truper', 'modelo' => null, 'id_mantenimiento' => null, 'estatus' => 'disponible', 'condicion' => 'Uso normal', 'observaciones' => 'Sin número de serie en el layout; folio provisional.'],
                    ['no_serie' => 'SIN-SERIE-0031', 'costo' => 2456.9, 'marca' => 'Truper', 'modelo' => null, 'id_mantenimiento' => null, 'estatus' => 'disponible', 'condicion' => 'Uso normal', 'observaciones' => 'Sin número de serie en el layout; folio provisional.'],
                ],
            ],
            [
                'descripcion' => 'POLIPASTO 3 TONE',
                'unidad' => 'PZA',
                'area' => 'Herramienta',
                'abc' => 'C',
                'ubicacion' => null,
                'piezas' => [
                    ['no_serie' => 'SIN-SERIE-0032', 'costo' => 3232.76, 'marca' => 'Truper', 'modelo' => null, 'id_mantenimiento' => null, 'estatus' => 'disponible', 'condicion' => 'Uso normal', 'observaciones' => 'Sin número de serie en el layout; folio provisional.'],
                ],
            ],
            [
                'descripcion' => 'POLIPASTO 5 TONE',
                'unidad' => 'PZA',
                'area' => 'Herramienta',
                'abc' => 'C',
                'ubicacion' => null,
                'piezas' => [
                    ['no_serie' => 'SIN-SERIE-0033', 'costo' => 4883.4, 'marca' => 'Truper', 'modelo' => null, 'id_mantenimiento' => null, 'estatus' => 'prestado', 'condicion' => 'Prestado a produccion', 'observaciones' => 'Sin número de serie en el layout; folio provisional.'],
                ],
            ],
            [
                'descripcion' => 'PISTOLA DE TORQUE INALAMBRICA',
                'unidad' => 'PZA',
                'area' => 'Herramienta',
                'abc' => 'C',
                'ubicacion' => null,
                'piezas' => [
                    ['no_serie' => 'SIN-SERIE-0034', 'costo' => 3700, 'marca' => 'Makita', 'modelo' => 'DTW301RJJ', 'id_mantenimiento' => null, 'estatus' => 'disponible', 'condicion' => 'Uso normal', 'observaciones' => 'Sin número de serie en el layout; folio provisional.'],
                    ['no_serie' => 'SIN-SERIE-0035', 'costo' => 3700, 'marca' => 'Makita', 'modelo' => 'DTW190', 'id_mantenimiento' => null, 'estatus' => 'disponible', 'condicion' => 'Uso normal', 'observaciones' => 'Sin número de serie en el layout; folio provisional.'],
                ],
            ],
            [
                'descripcion' => 'PISTOLA CONTROLBOLT',
                'unidad' => 'PZA',
                'area' => 'Herramienta',
                'abc' => 'C',
                'ubicacion' => null,
                'piezas' => [
                    ['no_serie' => 'SIN-SERIE-0036', 'costo' => 65000, 'marca' => 'Makita', 'modelo' => '4616', 'id_mantenimiento' => null, 'estatus' => 'disponible', 'condicion' => 'Uso normal', 'observaciones' => 'Sin número de serie en el layout; folio provisional.'],
                    ['no_serie' => 'SIN-SERIE-0037', 'costo' => 65000, 'marca' => 'Makita', 'modelo' => '1751', 'id_mantenimiento' => null, 'estatus' => 'disponible', 'condicion' => 'Uso normal', 'observaciones' => 'Sin número de serie en el layout; folio provisional.'],
                    ['no_serie' => 'SIN-SERIE-0038', 'costo' => 65000, 'marca' => 'Tone', 'modelo' => 'SG538637', 'id_mantenimiento' => null, 'estatus' => 'disponible', 'condicion' => 'Uso normal', 'observaciones' => 'Sin número de serie en el layout; folio provisional.'],
                    ['no_serie' => 'SIN-SERIE-0039', 'costo' => 65000, 'marca' => 'Tone', 'modelo' => 'GVC-301EZ', 'id_mantenimiento' => null, 'estatus' => 'disponible', 'condicion' => 'Uso normal', 'observaciones' => 'Sin número de serie en el layout; folio provisional.'],
                    ['no_serie' => 'SIN-SERIE-0040', 'costo' => 65000, 'marca' => 'Tone', 'modelo' => 'GVC-301EZ', 'id_mantenimiento' => null, 'estatus' => 'disponible', 'condicion' => 'Uso normal', 'observaciones' => 'Sin número de serie en el layout; folio provisional.'],
                    ['no_serie' => 'SIN-SERIE-0041', 'costo' => 65000, 'marca' => 'Tone', 'modelo' => 'GS-913Z', 'id_mantenimiento' => null, 'estatus' => 'disponible', 'condicion' => 'Uso normal', 'observaciones' => 'Sin número de serie en el layout; folio provisional.'],
                    ['no_serie' => 'SIN-SERIE-0042', 'costo' => 65000, 'marca' => 'Tone', 'modelo' => 'GVC-301EZ', 'id_mantenimiento' => null, 'estatus' => 'disponible', 'condicion' => 'Uso normal', 'observaciones' => 'Sin número de serie en el layout; folio provisional.'],
                    ['no_serie' => 'SIN-SERIE-0043', 'costo' => 65000, 'marca' => 'Tone', 'modelo' => 'GS111E', 'id_mantenimiento' => null, 'estatus' => 'disponible', 'condicion' => 'Uso normal', 'observaciones' => 'Sin número de serie en el layout; folio provisional.'],
                    ['no_serie' => 'SIN-SERIE-0044', 'costo' => 65000, 'marca' => 'Tone', 'modelo' => 'GS111EZ', 'id_mantenimiento' => null, 'estatus' => 'disponible', 'condicion' => 'Uso normal', 'observaciones' => 'Sin número de serie en el layout; folio provisional.'],
                ],
            ],
            [
                'descripcion' => 'MAQUINAS DE SOLDAR INVERTER 220V 200 AMP',
                'unidad' => 'PZA',
                'area' => 'Herramienta',
                'abc' => 'C',
                'ubicacion' => null,
                'piezas' => [
                    ['no_serie' => 'SIN-SERIE-0045', 'costo' => 5237, 'marca' => 'Maraga', 'modelo' => null, 'id_mantenimiento' => null, 'estatus' => 'disponible', 'condicion' => 'Uso normal', 'observaciones' => 'Sin número de serie en el layout; folio provisional.'],
                    ['no_serie' => 'SIN-SERIE-0046', 'costo' => 5237, 'marca' => 'Maraga', 'modelo' => null, 'id_mantenimiento' => null, 'estatus' => 'disponible', 'condicion' => 'Uso normal', 'observaciones' => 'Sin número de serie en el layout; folio provisional.'],
                    ['no_serie' => 'SIN-SERIE-0047', 'costo' => 5237, 'marca' => 'Maraga', 'modelo' => null, 'id_mantenimiento' => null, 'estatus' => 'disponible', 'condicion' => 'Uso normal', 'observaciones' => 'Sin número de serie en el layout; folio provisional.'],
                    ['no_serie' => 'SIN-SERIE-0048', 'costo' => 5237, 'marca' => 'Maraga', 'modelo' => null, 'id_mantenimiento' => null, 'estatus' => 'disponible', 'condicion' => 'Uso normal', 'observaciones' => 'Sin número de serie en el layout; folio provisional.'],
                    ['no_serie' => 'SIN-SERIE-0049', 'costo' => 5237, 'marca' => 'Maraga', 'modelo' => null, 'id_mantenimiento' => null, 'estatus' => 'disponible', 'condicion' => 'Uso normal', 'observaciones' => 'Sin número de serie en el layout; folio provisional.'],
                    ['no_serie' => 'SIN-SERIE-0050', 'costo' => 5237, 'marca' => 'Maraga', 'modelo' => null, 'id_mantenimiento' => null, 'estatus' => 'disponible', 'condicion' => 'Uso normal', 'observaciones' => 'Sin número de serie en el layout; folio provisional.'],
                    ['no_serie' => 'SIN-SERIE-0051', 'costo' => 5237, 'marca' => 'Maraga', 'modelo' => null, 'id_mantenimiento' => null, 'estatus' => 'disponible', 'condicion' => 'Uso normal', 'observaciones' => 'Sin número de serie en el layout; folio provisional.'],
                    ['no_serie' => 'SIN-SERIE-0052', 'costo' => 5237, 'marca' => 'Infra', 'modelo' => null, 'id_mantenimiento' => null, 'estatus' => 'disponible', 'condicion' => 'Uso normal', 'observaciones' => 'Sin número de serie en el layout; folio provisional.'],
                    ['no_serie' => 'SIN-SERIE-0053', 'costo' => 5237, 'marca' => 'Infra', 'modelo' => null, 'id_mantenimiento' => null, 'estatus' => 'disponible', 'condicion' => 'Uso normal', 'observaciones' => 'Sin número de serie en el layout; folio provisional.'],
                ],
            ],
            [
                'descripcion' => 'ENGARGOLADORA',
                'unidad' => 'PZA',
                'area' => 'Herramienta',
                'abc' => 'C',
                'ubicacion' => null,
                'piezas' => [
                    ['no_serie' => 'SIN-SERIE-0054', 'costo' => 55000, 'marca' => 'Milwakee', 'modelo' => null, 'id_mantenimiento' => null, 'estatus' => 'disponible', 'condicion' => 'Uso normal', 'observaciones' => 'Sin número de serie en el layout; folio provisional.'],
                    ['no_serie' => 'SIN-SERIE-0055', 'costo' => 55000, 'marca' => 'Milwakee', 'modelo' => null, 'id_mantenimiento' => null, 'estatus' => 'disponible', 'condicion' => 'Uso normal', 'observaciones' => 'Sin número de serie en el layout; folio provisional.'],
                ],
            ],
            [
                'descripcion' => 'MAQUINA DE SOLDAR MI2-300INFRA',
                'unidad' => 'PZA',
                'area' => 'Herramienta',
                'abc' => 'C',
                'ubicacion' => null,
                'piezas' => [
                    ['no_serie' => 'MS-14', 'costo' => 21716.64, 'marca' => 'Infra', 'modelo' => null, 'id_mantenimiento' => null, 'estatus' => 'disponible', 'condicion' => 'Uso normal', 'observaciones' => null],
                    ['no_serie' => 'MS-16', 'costo' => 21716.64, 'marca' => 'Infra', 'modelo' => null, 'id_mantenimiento' => null, 'estatus' => 'disponible', 'condicion' => 'Uso normal', 'observaciones' => null],
                    ['no_serie' => 'MS-05', 'costo' => 21716.64, 'marca' => 'Infra', 'modelo' => null, 'id_mantenimiento' => null, 'estatus' => 'disponible', 'condicion' => 'Uso normal', 'observaciones' => null],
                    ['no_serie' => 'MS-03', 'costo' => 21716.64, 'marca' => 'Infra', 'modelo' => null, 'id_mantenimiento' => null, 'estatus' => 'disponible', 'condicion' => 'Uso normal', 'observaciones' => null],
                    ['no_serie' => 'MS-32', 'costo' => 21716.64, 'marca' => 'Infra', 'modelo' => null, 'id_mantenimiento' => null, 'estatus' => 'disponible', 'condicion' => 'Uso normal', 'observaciones' => null],
                    ['no_serie' => 'SIN-SERIE-0056', 'costo' => 21716.64, 'marca' => 'Infra', 'modelo' => null, 'id_mantenimiento' => null, 'estatus' => 'disponible', 'condicion' => 'Uso normal', 'observaciones' => 'Sin número de serie en el layout; folio provisional.'],
                    ['no_serie' => 'MS-30', 'costo' => 21716.64, 'marca' => 'Infra', 'modelo' => null, 'id_mantenimiento' => null, 'estatus' => 'disponible', 'condicion' => 'Uso normal', 'observaciones' => null],
                    ['no_serie' => 'MS-23', 'costo' => 21716.64, 'marca' => 'Infra', 'modelo' => null, 'id_mantenimiento' => null, 'estatus' => 'disponible', 'condicion' => 'Uso normal', 'observaciones' => null],
                    ['no_serie' => 'SIN-SERIE-0057', 'costo' => 21716.64, 'marca' => 'Infra', 'modelo' => null, 'id_mantenimiento' => null, 'estatus' => 'disponible', 'condicion' => 'Uso normal', 'observaciones' => 'Sin número de serie en el layout; folio provisional.'],
                    ['no_serie' => 'MS-09', 'costo' => 21716.64, 'marca' => 'Infra', 'modelo' => null, 'id_mantenimiento' => null, 'estatus' => 'disponible', 'condicion' => 'Uso normal', 'observaciones' => null],
                    ['no_serie' => 'MS-31', 'costo' => 21716.64, 'marca' => 'Infra', 'modelo' => null, 'id_mantenimiento' => null, 'estatus' => 'disponible', 'condicion' => 'Uso normal', 'observaciones' => null],
                    ['no_serie' => 'MS-34', 'costo' => 21716.64, 'marca' => 'Infra', 'modelo' => null, 'id_mantenimiento' => null, 'estatus' => 'disponible', 'condicion' => 'Uso normal', 'observaciones' => null],
                    ['no_serie' => 'SIN-SERIE-0058', 'costo' => 21716.64, 'marca' => 'Infra', 'modelo' => null, 'id_mantenimiento' => null, 'estatus' => 'disponible', 'condicion' => 'Uso normal', 'observaciones' => 'Sin número de serie en el layout; folio provisional.'],
                    ['no_serie' => 'SIN-SERIE-0059', 'costo' => 21716.64, 'marca' => '#01', 'modelo' => null, 'id_mantenimiento' => null, 'estatus' => 'disponible', 'condicion' => 'Uso normal', 'observaciones' => 'Sin número de serie en el layout; folio provisional.'],
                    ['no_serie' => 'MS-04', 'costo' => 21716.64, 'marca' => 'Infra', 'modelo' => null, 'id_mantenimiento' => null, 'estatus' => 'disponible', 'condicion' => 'Uso normal', 'observaciones' => null],
                    ['no_serie' => 'SIN-SERIE-0060', 'costo' => 21716.64, 'marca' => 'Infra', 'modelo' => null, 'id_mantenimiento' => null, 'estatus' => 'disponible', 'condicion' => 'Uso normal', 'observaciones' => 'Sin número de serie en el layout; folio provisional.'],
                    ['no_serie' => 'MS-12', 'costo' => 21716.64, 'marca' => 'Infra', 'modelo' => null, 'id_mantenimiento' => null, 'estatus' => 'disponible', 'condicion' => 'Uso normal', 'observaciones' => null],
                    ['no_serie' => 'MS-13', 'costo' => 21716.64, 'marca' => 'Infra', 'modelo' => null, 'id_mantenimiento' => null, 'estatus' => 'disponible', 'condicion' => 'Uso normal', 'observaciones' => null],
                    ['no_serie' => 'SIN-SERIE-0061', 'costo' => 21716.64, 'marca' => 'Infra', 'modelo' => null, 'id_mantenimiento' => null, 'estatus' => 'disponible', 'condicion' => 'Uso normal', 'observaciones' => 'Sin número de serie en el layout; folio provisional.'],
                    ['no_serie' => 'MS-06', 'costo' => 21716.64, 'marca' => 'Infra', 'modelo' => null, 'id_mantenimiento' => null, 'estatus' => 'disponible', 'condicion' => 'Uso normal', 'observaciones' => null],
                    ['no_serie' => 'MS-28', 'costo' => 21716.64, 'marca' => 'Infra', 'modelo' => null, 'id_mantenimiento' => null, 'estatus' => 'disponible', 'condicion' => 'Uso normal', 'observaciones' => null],
                    ['no_serie' => 'MS-33', 'costo' => 21716.64, 'marca' => 'Infra', 'modelo' => null, 'id_mantenimiento' => null, 'estatus' => 'disponible', 'condicion' => 'Uso normal', 'observaciones' => null],
                    ['no_serie' => 'SIN-SERIE-0062', 'costo' => 21716.64, 'marca' => 'Infra', 'modelo' => null, 'id_mantenimiento' => null, 'estatus' => 'disponible', 'condicion' => 'Uso normal', 'observaciones' => 'Sin número de serie en el layout; folio provisional.'],
                    ['no_serie' => 'SIN-SERIE-0063', 'costo' => 21716.64, 'marca' => 'Infra', 'modelo' => null, 'id_mantenimiento' => null, 'estatus' => 'disponible', 'condicion' => 'Uso normal', 'observaciones' => 'Sin número de serie en el layout; folio provisional.'],
                ],
            ],
        ];
    }
}
