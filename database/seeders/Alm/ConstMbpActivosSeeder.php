<?php

namespace Database\Seeders\Alm;

/**
 * Padrón de activos de CONST (MBP): 1 artículos, 1 piezas, $5,237.00.
 *
 * 1 de las 1 piezas llegaron sin número de serie. Entran con un folio
 * provisional SIN-SERIE-#### y la observación que lo dice: sin serie no hay resguardo,
 * y dejarlas fuera habría abierto el almacén con el padrón incompleto.
 */
class ConstMbpActivosSeeder extends CargaInicialActivosSeeder
{
    protected function almacen(): string
    {
        return 'CONST';
    }

    protected function obra(): ?string
    {
        return 'MBP';
    }

    /**
     * @return list<array{descripcion: string, unidad: string, area: string|null, abc: string, ubicacion: string|null, piezas: list<array<string, mixed>>}>
     */
    protected function articulos(): array
    {
        return [
            [
                'descripcion' => 'MAQUINAS DE SOLDAR INVERTER 220V 200 AMP',
                'unidad' => 'PZA',
                'area' => 'Herramienta',
                'abc' => 'C',
                'ubicacion' => 'OBRA',
                'piezas' => [
                    ['no_serie' => 'SIN-SERIE-0066', 'costo' => 5237, 'marca' => null, 'modelo' => null, 'id_mantenimiento' => null, 'estatus' => 'prestado', 'condicion' => null, 'observaciones' => 'Sin número de serie en el layout; folio provisional.'],
                ],
            ],
        ];
    }
}
