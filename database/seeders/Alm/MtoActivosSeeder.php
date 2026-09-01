<?php

namespace Database\Seeders\Alm;

/**
 * Padrón de activos de MTO (central): 3 artículos, 6 piezas, $17,101.60.
 */
class MtoActivosSeeder extends CargaInicialActivosSeeder
{
    protected function almacen(): string
    {
        return 'MTO';
    }

    /**
     * @return list<array{descripcion: string, unidad: string, area: string|null, abc: string, ubicacion: string|null, piezas: list<array<string, mixed>>}>
     */
    protected function articulos(): array
    {
        return [
            [
                'descripcion' => 'MINI ESMERIL DE 4 1/2',
                'unidad' => 'PZA',
                'area' => 'Herramienta',
                'abc' => 'C',
                'ubicacion' => 'MTTO',
                'piezas' => [
                    ['no_serie' => 'M236', 'costo' => 1534, 'marca' => 'Bosh', 'modelo' => null, 'id_mantenimiento' => null, 'estatus' => 'baja', 'condicion' => 'dañado sin reparación', 'observaciones' => null],
                    ['no_serie' => 'M247', 'costo' => 1534, 'marca' => 'Bosh', 'modelo' => null, 'id_mantenimiento' => null, 'estatus' => 'baja', 'condicion' => 'dañado sin reparación', 'observaciones' => null],
                ],
            ],
            [
                'descripcion' => 'ROTO MARTILLO O TALADRO PERCUTOR',
                'unidad' => 'PZA',
                'area' => 'Herramienta',
                'abc' => 'C',
                'ubicacion' => 'MTTO',
                'piezas' => [
                    ['no_serie' => 'T71', 'costo' => 2932.2, 'marca' => 'Milwaukee', 'modelo' => null, 'id_mantenimiento' => null, 'estatus' => 'baja', 'condicion' => 'dañado sin reparación', 'observaciones' => null],
                    ['no_serie' => 'T83', 'costo' => 2932.2, 'marca' => 'Milwaukee', 'modelo' => null, 'id_mantenimiento' => null, 'estatus' => 'baja', 'condicion' => 'dañado sin reparación', 'observaciones' => null],
                    ['no_serie' => 'T60', 'costo' => 2932.2, 'marca' => 'Bosh', 'modelo' => null, 'id_mantenimiento' => null, 'estatus' => 'baja', 'condicion' => 'dañado sin reparación', 'observaciones' => null],
                ],
            ],
            [
                'descripcion' => 'MAQUINAS DE SOLDAR INVERTER FINEARC300',
                'unidad' => 'PZA',
                'area' => 'Herramienta',
                'abc' => 'C',
                'ubicacion' => 'MTTO',
                'piezas' => [
                    ['no_serie' => 'S08', 'costo' => 5237, 'marca' => 'MARAGA', 'modelo' => null, 'id_mantenimiento' => null, 'estatus' => 'en_reparacion', 'condicion' => 'Buen estado', 'observaciones' => null],
                ],
            ],
        ];
    }
}
