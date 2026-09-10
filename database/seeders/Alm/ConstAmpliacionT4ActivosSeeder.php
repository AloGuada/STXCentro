<?php

namespace Database\Seeders\Alm;

/**
 * Padrón de activos de CONST (AMPLIACION T4): 17 artículos, 64 piezas, $297,471.82.
 *
 * 64 de las 64 piezas llegaron sin número de serie. Entran con un folio
 * provisional SIN-SERIE-#### y la observación que lo dice: sin serie no hay resguardo,
 * y dejarlas fuera habría abierto el almacén con el padrón incompleto.
 */
class ConstAmpliacionT4ActivosSeeder extends CargaInicialActivosSeeder
{
    protected function almacen(): string
    {
        return 'CONST';
    }

    protected function obra(): ?string
    {
        return 'AMPLIACION T4';
    }

    /**
     * @return list<array{descripcion: string, unidad: string, area: string|null, abc: string, ubicacion: string|null, piezas: list<array<string, mixed>>}>
     */
    protected function articulos(): array
    {
        return [
            [
                'descripcion' => 'ARTORCHA ARCO AIRE',
                'unidad' => 'PZA',
                'area' => 'Herramienta',
                'abc' => 'C',
                'ubicacion' => 'OBRA',
                'piezas' => [
                    ['no_serie' => 'SIN-SERIE-0085', 'costo' => 4000, 'marca' => null, 'modelo' => null, 'id_mantenimiento' => null, 'estatus' => 'prestado', 'condicion' => null, 'observaciones' => 'Sin número de serie en el layout; folio provisional.'],
                    ['no_serie' => 'SIN-SERIE-0086', 'costo' => 4000, 'marca' => null, 'modelo' => null, 'id_mantenimiento' => null, 'estatus' => 'prestado', 'condicion' => null, 'observaciones' => 'Sin número de serie en el layout; folio provisional.'],
                ],
            ],
            [
                'descripcion' => 'ESMERILADORA ANGULAR DE 9"',
                'unidad' => 'PZA',
                'area' => 'Herramienta',
                'abc' => 'C',
                'ubicacion' => 'OBRA',
                'piezas' => [
                    ['no_serie' => 'SIN-SERIE-0087', 'costo' => 3508.62, 'marca' => null, 'modelo' => null, 'id_mantenimiento' => null, 'estatus' => 'prestado', 'condicion' => null, 'observaciones' => 'Sin número de serie en el layout; folio provisional.'],
                    ['no_serie' => 'SIN-SERIE-0088', 'costo' => 3508.62, 'marca' => null, 'modelo' => null, 'id_mantenimiento' => null, 'estatus' => 'prestado', 'condicion' => null, 'observaciones' => 'Sin número de serie en el layout; folio provisional.'],
                    ['no_serie' => 'SIN-SERIE-0089', 'costo' => 3508.62, 'marca' => null, 'modelo' => null, 'id_mantenimiento' => null, 'estatus' => 'prestado', 'condicion' => null, 'observaciones' => 'Sin número de serie en el layout; folio provisional.'],
                    ['no_serie' => 'SIN-SERIE-0090', 'costo' => 3508.62, 'marca' => null, 'modelo' => null, 'id_mantenimiento' => null, 'estatus' => 'prestado', 'condicion' => null, 'observaciones' => 'Sin número de serie en el layout; folio provisional.'],
                    ['no_serie' => 'SIN-SERIE-0091', 'costo' => 3508.62, 'marca' => null, 'modelo' => null, 'id_mantenimiento' => null, 'estatus' => 'prestado', 'condicion' => null, 'observaciones' => 'Sin número de serie en el layout; folio provisional.'],
                    ['no_serie' => 'SIN-SERIE-0092', 'costo' => 3508.62, 'marca' => null, 'modelo' => null, 'id_mantenimiento' => null, 'estatus' => 'prestado', 'condicion' => null, 'observaciones' => 'Sin número de serie en el layout; folio provisional.'],
                    ['no_serie' => 'SIN-SERIE-0093', 'costo' => 3508.62, 'marca' => null, 'modelo' => null, 'id_mantenimiento' => null, 'estatus' => 'prestado', 'condicion' => null, 'observaciones' => 'Sin número de serie en el layout; folio provisional.'],
                ],
            ],
            [
                'descripcion' => 'MINI ESMERIL DE 4 1/2',
                'unidad' => 'PZA',
                'area' => 'Herramienta',
                'abc' => 'C',
                'ubicacion' => 'OBRA',
                'piezas' => [
                    ['no_serie' => 'SIN-SERIE-0094', 'costo' => 1534, 'marca' => null, 'modelo' => null, 'id_mantenimiento' => null, 'estatus' => 'prestado', 'condicion' => null, 'observaciones' => 'Sin número de serie en el layout; folio provisional.'],
                    ['no_serie' => 'SIN-SERIE-0095', 'costo' => 1534, 'marca' => null, 'modelo' => null, 'id_mantenimiento' => null, 'estatus' => 'prestado', 'condicion' => null, 'observaciones' => 'Sin número de serie en el layout; folio provisional.'],
                    ['no_serie' => 'SIN-SERIE-0096', 'costo' => 1534, 'marca' => null, 'modelo' => null, 'id_mantenimiento' => null, 'estatus' => 'prestado', 'condicion' => null, 'observaciones' => 'Sin número de serie en el layout; folio provisional.'],
                    ['no_serie' => 'SIN-SERIE-0097', 'costo' => 1534, 'marca' => null, 'modelo' => null, 'id_mantenimiento' => null, 'estatus' => 'prestado', 'condicion' => null, 'observaciones' => 'Sin número de serie en el layout; folio provisional.'],
                ],
            ],
            [
                'descripcion' => 'CORTADORA DE BANCO',
                'unidad' => 'PZA',
                'area' => 'Herramienta',
                'abc' => 'C',
                'ubicacion' => 'OBRA',
                'piezas' => [
                    ['no_serie' => 'SIN-SERIE-0098', 'costo' => 3684.96, 'marca' => null, 'modelo' => null, 'id_mantenimiento' => null, 'estatus' => 'prestado', 'condicion' => null, 'observaciones' => 'Sin número de serie en el layout; folio provisional.'],
                    ['no_serie' => 'SIN-SERIE-0099', 'costo' => 3684.96, 'marca' => null, 'modelo' => null, 'id_mantenimiento' => null, 'estatus' => 'prestado', 'condicion' => null, 'observaciones' => 'Sin número de serie en el layout; folio provisional.'],
                ],
            ],
            [
                'descripcion' => 'RECTIFICADOR O ESMERIL RECTO',
                'unidad' => 'PZA',
                'area' => 'Herramienta',
                'abc' => 'C',
                'ubicacion' => 'OBRA',
                'piezas' => [
                    ['no_serie' => 'SIN-SERIE-0100', 'costo' => 2448, 'marca' => null, 'modelo' => null, 'id_mantenimiento' => null, 'estatus' => 'prestado', 'condicion' => null, 'observaciones' => 'Sin número de serie en el layout; folio provisional.'],
                    ['no_serie' => 'SIN-SERIE-0101', 'costo' => 2448, 'marca' => null, 'modelo' => null, 'id_mantenimiento' => null, 'estatus' => 'prestado', 'condicion' => null, 'observaciones' => 'Sin número de serie en el layout; folio provisional.'],
                    ['no_serie' => 'SIN-SERIE-0102', 'costo' => 2448, 'marca' => null, 'modelo' => null, 'id_mantenimiento' => null, 'estatus' => 'prestado', 'condicion' => null, 'observaciones' => 'Sin número de serie en el layout; folio provisional.'],
                    ['no_serie' => 'SIN-SERIE-0103', 'costo' => 2448, 'marca' => null, 'modelo' => null, 'id_mantenimiento' => null, 'estatus' => 'prestado', 'condicion' => null, 'observaciones' => 'Sin número de serie en el layout; folio provisional.'],
                    ['no_serie' => 'SIN-SERIE-0104', 'costo' => 2448, 'marca' => null, 'modelo' => null, 'id_mantenimiento' => null, 'estatus' => 'prestado', 'condicion' => null, 'observaciones' => 'Sin número de serie en el layout; folio provisional.'],
                    ['no_serie' => 'SIN-SERIE-0105', 'costo' => 2448, 'marca' => null, 'modelo' => null, 'id_mantenimiento' => null, 'estatus' => 'prestado', 'condicion' => null, 'observaciones' => 'Sin número de serie en el layout; folio provisional.'],
                ],
            ],
            [
                'descripcion' => 'HORNO PORTATIL P/SOLDADURA',
                'unidad' => 'PZA',
                'area' => 'Herramienta',
                'abc' => 'C',
                'ubicacion' => 'OBRA',
                'piezas' => [
                    ['no_serie' => 'SIN-SERIE-0106', 'costo' => 1970, 'marca' => null, 'modelo' => null, 'id_mantenimiento' => null, 'estatus' => 'prestado', 'condicion' => null, 'observaciones' => 'Sin número de serie en el layout; folio provisional.'],
                    ['no_serie' => 'SIN-SERIE-0107', 'costo' => 1970, 'marca' => null, 'modelo' => null, 'id_mantenimiento' => null, 'estatus' => 'prestado', 'condicion' => null, 'observaciones' => 'Sin número de serie en el layout; folio provisional.'],
                    ['no_serie' => 'SIN-SERIE-0108', 'costo' => 1970, 'marca' => null, 'modelo' => null, 'id_mantenimiento' => null, 'estatus' => 'prestado', 'condicion' => null, 'observaciones' => 'Sin número de serie en el layout; folio provisional.'],
                    ['no_serie' => 'SIN-SERIE-0109', 'costo' => 1970, 'marca' => null, 'modelo' => null, 'id_mantenimiento' => null, 'estatus' => 'prestado', 'condicion' => null, 'observaciones' => 'Sin número de serie en el layout; folio provisional.'],
                    ['no_serie' => 'SIN-SERIE-0110', 'costo' => 1970, 'marca' => null, 'modelo' => null, 'id_mantenimiento' => null, 'estatus' => 'prestado', 'condicion' => null, 'observaciones' => 'Sin número de serie en el layout; folio provisional.'],
                    ['no_serie' => 'SIN-SERIE-0111', 'costo' => 1970, 'marca' => null, 'modelo' => null, 'id_mantenimiento' => null, 'estatus' => 'prestado', 'condicion' => null, 'observaciones' => 'Sin número de serie en el layout; folio provisional.'],
                    ['no_serie' => 'SIN-SERIE-0112', 'costo' => 1970, 'marca' => null, 'modelo' => null, 'id_mantenimiento' => null, 'estatus' => 'prestado', 'condicion' => null, 'observaciones' => 'Sin número de serie en el layout; folio provisional.'],
                    ['no_serie' => 'SIN-SERIE-0113', 'costo' => 1970, 'marca' => null, 'modelo' => null, 'id_mantenimiento' => null, 'estatus' => 'prestado', 'condicion' => null, 'observaciones' => 'Sin número de serie en el layout; folio provisional.'],
                ],
            ],
            [
                'descripcion' => 'MAQUINAS DE SOLDAR INVERTER 220V 200 AMP',
                'unidad' => 'PZA',
                'area' => 'Herramienta',
                'abc' => 'C',
                'ubicacion' => 'OBRA',
                'piezas' => [
                    ['no_serie' => 'SIN-SERIE-0114', 'costo' => 5237, 'marca' => null, 'modelo' => null, 'id_mantenimiento' => null, 'estatus' => 'prestado', 'condicion' => null, 'observaciones' => 'Sin número de serie en el layout; folio provisional.'],
                    ['no_serie' => 'SIN-SERIE-0115', 'costo' => 5237, 'marca' => null, 'modelo' => null, 'id_mantenimiento' => null, 'estatus' => 'prestado', 'condicion' => null, 'observaciones' => 'Sin número de serie en el layout; folio provisional.'],
                    ['no_serie' => 'SIN-SERIE-0116', 'costo' => 5237, 'marca' => null, 'modelo' => null, 'id_mantenimiento' => null, 'estatus' => 'prestado', 'condicion' => null, 'observaciones' => 'Sin número de serie en el layout; folio provisional.'],
                    ['no_serie' => 'SIN-SERIE-0117', 'costo' => 5237, 'marca' => null, 'modelo' => null, 'id_mantenimiento' => null, 'estatus' => 'prestado', 'condicion' => null, 'observaciones' => 'Sin número de serie en el layout; folio provisional.'],
                    ['no_serie' => 'SIN-SERIE-0118', 'costo' => 5237, 'marca' => null, 'modelo' => null, 'id_mantenimiento' => null, 'estatus' => 'prestado', 'condicion' => null, 'observaciones' => 'Sin número de serie en el layout; folio provisional.'],
                    ['no_serie' => 'SIN-SERIE-0119', 'costo' => 5237, 'marca' => null, 'modelo' => null, 'id_mantenimiento' => null, 'estatus' => 'prestado', 'condicion' => null, 'observaciones' => 'Sin número de serie en el layout; folio provisional.'],
                    ['no_serie' => 'SIN-SERIE-0120', 'costo' => 5237, 'marca' => null, 'modelo' => null, 'id_mantenimiento' => null, 'estatus' => 'prestado', 'condicion' => null, 'observaciones' => 'Sin número de serie en el layout; folio provisional.'],
                    ['no_serie' => 'SIN-SERIE-0121', 'costo' => 5237, 'marca' => null, 'modelo' => null, 'id_mantenimiento' => null, 'estatus' => 'prestado', 'condicion' => null, 'observaciones' => 'Sin número de serie en el layout; folio provisional.'],
                    ['no_serie' => 'SIN-SERIE-0122', 'costo' => 5237, 'marca' => null, 'modelo' => null, 'id_mantenimiento' => null, 'estatus' => 'prestado', 'condicion' => null, 'observaciones' => 'Sin número de serie en el layout; folio provisional.'],
                    ['no_serie' => 'SIN-SERIE-0123', 'costo' => 5237, 'marca' => null, 'modelo' => null, 'id_mantenimiento' => null, 'estatus' => 'prestado', 'condicion' => null, 'observaciones' => 'Sin número de serie en el layout; folio provisional.'],
                    ['no_serie' => 'SIN-SERIE-0124', 'costo' => 5237, 'marca' => null, 'modelo' => null, 'id_mantenimiento' => null, 'estatus' => 'prestado', 'condicion' => null, 'observaciones' => 'Sin número de serie en el layout; folio provisional.'],
                ],
            ],
            [
                'descripcion' => 'MAQUINA DE SOLDAR MI2-300INFRA',
                'unidad' => 'PZA',
                'area' => 'Herramienta',
                'abc' => 'C',
                'ubicacion' => 'OBRA',
                'piezas' => [
                    ['no_serie' => 'SIN-SERIE-0125', 'costo' => 21716.64, 'marca' => null, 'modelo' => null, 'id_mantenimiento' => null, 'estatus' => 'prestado', 'condicion' => null, 'observaciones' => 'Sin número de serie en el layout; folio provisional.'],
                    ['no_serie' => 'SIN-SERIE-0126', 'costo' => 21716.64, 'marca' => null, 'modelo' => null, 'id_mantenimiento' => null, 'estatus' => 'prestado', 'condicion' => null, 'observaciones' => 'Sin número de serie en el layout; folio provisional.'],
                    ['no_serie' => 'SIN-SERIE-0127', 'costo' => 21716.64, 'marca' => null, 'modelo' => null, 'id_mantenimiento' => null, 'estatus' => 'prestado', 'condicion' => null, 'observaciones' => 'Sin número de serie en el layout; folio provisional.'],
                    ['no_serie' => 'SIN-SERIE-0128', 'costo' => 21716.64, 'marca' => null, 'modelo' => null, 'id_mantenimiento' => null, 'estatus' => 'prestado', 'condicion' => null, 'observaciones' => 'Sin número de serie en el layout; folio provisional.'],
                ],
            ],
            [
                'descripcion' => 'NIVEL LASER',
                'unidad' => 'PZA',
                'area' => 'Herramienta',
                'abc' => 'C',
                'ubicacion' => 'OBRA',
                'piezas' => [
                    ['no_serie' => 'SIN-SERIE-0129', 'costo' => 2500, 'marca' => null, 'modelo' => null, 'id_mantenimiento' => null, 'estatus' => 'prestado', 'condicion' => null, 'observaciones' => 'Sin número de serie en el layout; folio provisional.'],
                    ['no_serie' => 'SIN-SERIE-0130', 'costo' => 2500, 'marca' => null, 'modelo' => null, 'id_mantenimiento' => null, 'estatus' => 'prestado', 'condicion' => null, 'observaciones' => 'Sin número de serie en el layout; folio provisional.'],
                ],
            ],
            [
                'descripcion' => 'PIJADORA O ATORNILLADOR',
                'unidad' => 'PZA',
                'area' => 'Herramienta',
                'abc' => 'C',
                'ubicacion' => 'OBRA',
                'piezas' => [
                    ['no_serie' => 'SIN-SERIE-0131', 'costo' => 2378, 'marca' => null, 'modelo' => null, 'id_mantenimiento' => null, 'estatus' => 'prestado', 'condicion' => null, 'observaciones' => 'Sin número de serie en el layout; folio provisional.'],
                    ['no_serie' => 'SIN-SERIE-0132', 'costo' => 2378, 'marca' => null, 'modelo' => null, 'id_mantenimiento' => null, 'estatus' => 'prestado', 'condicion' => null, 'observaciones' => 'Sin número de serie en el layout; folio provisional.'],
                    ['no_serie' => 'SIN-SERIE-0133', 'costo' => 2378, 'marca' => null, 'modelo' => null, 'id_mantenimiento' => null, 'estatus' => 'prestado', 'condicion' => null, 'observaciones' => 'Sin número de serie en el layout; folio provisional.'],
                ],
            ],
            [
                'descripcion' => 'TALADRO MAGNETICO',
                'unidad' => 'PZA',
                'area' => 'Herramienta',
                'abc' => 'C',
                'ubicacion' => 'OBRA',
                'piezas' => [
                    ['no_serie' => 'SIN-SERIE-0134', 'costo' => 14550, 'marca' => null, 'modelo' => null, 'id_mantenimiento' => null, 'estatus' => 'prestado', 'condicion' => null, 'observaciones' => 'Sin número de serie en el layout; folio provisional.'],
                ],
            ],
            [
                'descripcion' => 'ROTO MARTILLO O TALADRO PERCUTOR',
                'unidad' => 'PZA',
                'area' => 'Herramienta',
                'abc' => 'C',
                'ubicacion' => 'OBRA',
                'piezas' => [
                    ['no_serie' => 'SIN-SERIE-0135', 'costo' => 2932.2, 'marca' => null, 'modelo' => null, 'id_mantenimiento' => null, 'estatus' => 'prestado', 'condicion' => null, 'observaciones' => 'Sin número de serie en el layout; folio provisional.'],
                    ['no_serie' => 'SIN-SERIE-0136', 'costo' => 2932.2, 'marca' => null, 'modelo' => null, 'id_mantenimiento' => null, 'estatus' => 'prestado', 'condicion' => null, 'observaciones' => 'Sin número de serie en el layout; folio provisional.'],
                    ['no_serie' => 'SIN-SERIE-0137', 'costo' => 2932.2, 'marca' => null, 'modelo' => null, 'id_mantenimiento' => null, 'estatus' => 'prestado', 'condicion' => null, 'observaciones' => 'Sin número de serie en el layout; folio provisional.'],
                    ['no_serie' => 'SIN-SERIE-0138', 'costo' => 2932.2, 'marca' => null, 'modelo' => null, 'id_mantenimiento' => null, 'estatus' => 'prestado', 'condicion' => null, 'observaciones' => 'Sin número de serie en el layout; folio provisional.'],
                    ['no_serie' => 'SIN-SERIE-0139', 'costo' => 2932.2, 'marca' => null, 'modelo' => null, 'id_mantenimiento' => null, 'estatus' => 'prestado', 'condicion' => null, 'observaciones' => 'Sin número de serie en el layout; folio provisional.'],
                ],
            ],
            [
                'descripcion' => 'PISTOLA DE IMPACTO O DE TORQUE',
                'unidad' => 'PZA',
                'area' => 'Herramienta',
                'abc' => 'C',
                'ubicacion' => 'OBRA',
                'piezas' => [
                    ['no_serie' => 'SIN-SERIE-0140', 'costo' => 2384, 'marca' => null, 'modelo' => null, 'id_mantenimiento' => null, 'estatus' => 'prestado', 'condicion' => null, 'observaciones' => 'Sin número de serie en el layout; folio provisional.'],
                    ['no_serie' => 'SIN-SERIE-0141', 'costo' => 2384, 'marca' => null, 'modelo' => null, 'id_mantenimiento' => null, 'estatus' => 'prestado', 'condicion' => null, 'observaciones' => 'Sin número de serie en el layout; folio provisional.'],
                    ['no_serie' => 'SIN-SERIE-0142', 'costo' => 2384, 'marca' => null, 'modelo' => null, 'id_mantenimiento' => null, 'estatus' => 'prestado', 'condicion' => null, 'observaciones' => 'Sin número de serie en el layout; folio provisional.'],
                    ['no_serie' => 'SIN-SERIE-0143', 'costo' => 2384, 'marca' => null, 'modelo' => null, 'id_mantenimiento' => null, 'estatus' => 'prestado', 'condicion' => null, 'observaciones' => 'Sin número de serie en el layout; folio provisional.'],
                    ['no_serie' => 'SIN-SERIE-0144', 'costo' => 2384, 'marca' => null, 'modelo' => null, 'id_mantenimiento' => null, 'estatus' => 'prestado', 'condicion' => null, 'observaciones' => 'Sin número de serie en el layout; folio provisional.'],
                ],
            ],
            [
                'descripcion' => 'TORQUIMETRO',
                'unidad' => 'PZA',
                'area' => 'Herramienta',
                'abc' => 'C',
                'ubicacion' => 'OBRA',
                'piezas' => [
                    ['no_serie' => 'SIN-SERIE-0145', 'costo' => 2384, 'marca' => null, 'modelo' => null, 'id_mantenimiento' => null, 'estatus' => 'prestado', 'condicion' => null, 'observaciones' => 'Sin número de serie en el layout; folio provisional.'],
                ],
            ],
            [
                'descripcion' => 'MULTIPLICADOR DE TORQUE',
                'unidad' => 'PZA',
                'area' => 'Herramienta',
                'abc' => 'C',
                'ubicacion' => 'OBRA',
                'piezas' => [
                    ['no_serie' => 'SIN-SERIE-0146', 'costo' => 2000, 'marca' => null, 'modelo' => null, 'id_mantenimiento' => null, 'estatus' => 'prestado', 'condicion' => null, 'observaciones' => 'Sin número de serie en el layout; folio provisional.'],
                ],
            ],
            [
                'descripcion' => 'MEDIDOR DE ESPESOR DE PINTURA POSITECTOR',
                'unidad' => 'PZA',
                'area' => 'Herramienta',
                'abc' => 'C',
                'ubicacion' => 'OBRA',
                'piezas' => [
                    ['no_serie' => 'SIN-SERIE-0147', 'costo' => 17000, 'marca' => null, 'modelo' => null, 'id_mantenimiento' => null, 'estatus' => 'prestado', 'condicion' => null, 'observaciones' => 'Sin número de serie en el layout; folio provisional.'],
                ],
            ],
            [
                'descripcion' => 'LIJADORA ORBITAL',
                'unidad' => 'PZA',
                'area' => 'Herramienta',
                'abc' => 'C',
                'ubicacion' => 'OBRA',
                'piezas' => [
                    ['no_serie' => 'SIN-SERIE-0148', 'costo' => 1835, 'marca' => null, 'modelo' => null, 'id_mantenimiento' => null, 'estatus' => 'prestado', 'condicion' => null, 'observaciones' => 'Sin número de serie en el layout; folio provisional.'],
                ],
            ],
        ];
    }
}
