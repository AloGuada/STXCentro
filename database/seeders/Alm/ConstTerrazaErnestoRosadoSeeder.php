<?php

namespace Database\Seeders\Alm;

/**
 * Almacén CONST (TERRAZA ERNESTO ROSADO): 8 artículos, $38,485.88.
 *
 * El layout llegó sin la columna de unidad; cada renglón dice qué se asumió.
 */
class ConstTerrazaErnestoRosadoSeeder extends CargaInicialSeeder
{
    protected function almacen(): string
    {
        return 'CONST';
    }

    protected function obra(): ?string
    {
        return 'TERRAZA ERNESTO ROSADO';
    }

    /**
     * @return list<array{descripcion: string, unidad: string, area: string|null, abc: string, stock_minimo: float|null, cantidad: float, costo: float|null, nota: string|null, ubicacion?: string|null, codigo_barras?: string|null, idsteelex?: string|null, verifica?: bool}>
     */
    protected function articulos(): array
    {
        return [
            ['descripcion' => 'EQUIPO DE OXICORTE COMPLETO', 'unidad' => 'PZA', 'area' => 'Herramienta', 'abc' => 'A', 'stock_minimo' => null, 'cantidad' => 1, 'costo' => 4800, 'nota' => 'Unidad ausente en el layout; se asumió PZA.', 'ubicacion' => 'OBRA', 'idsteelex' => null, 'verifica' => false],
            ['descripcion' => 'TANQUES DE GAS 20 KG', 'unidad' => 'PZA', 'area' => 'Herramienta', 'abc' => 'A', 'stock_minimo' => null, 'cantidad' => 1, 'costo' => 1720, 'nota' => 'Unidad ausente en el layout; se asumió PZA.', 'ubicacion' => 'OBRA', 'idsteelex' => null, 'verifica' => false],
            ['descripcion' => 'TANQUES OXIGENO 9 M3', 'unidad' => 'PZA', 'area' => 'Herramienta', 'abc' => 'A', 'stock_minimo' => null, 'cantidad' => 1, 'costo' => 8000, 'nota' => 'Unidad ausente en el layout; se asumió PZA.', 'ubicacion' => 'OBRA', 'idsteelex' => null, 'verifica' => false],
            ['descripcion' => 'CARRITOS DE OXICORTE', 'unidad' => 'PZA', 'area' => 'Herramienta', 'abc' => 'A', 'stock_minimo' => null, 'cantidad' => 2, 'costo' => 2475, 'nota' => 'Unidad ausente en el layout; se asumió PZA.', 'ubicacion' => 'OBRA', 'idsteelex' => null, 'verifica' => false],
            ['descripcion' => 'MARCO DE ANDAMIO', 'unidad' => 'PZA', 'area' => 'Herramienta', 'abc' => 'A', 'stock_minimo' => null, 'cantidad' => 4, 'costo' => 2500, 'nota' => 'Unidad ausente en el layout; se asumió PZA.', 'ubicacion' => 'OBRA', 'idsteelex' => null, 'verifica' => false],
            ['descripcion' => 'CRUCETA DE ANDAMIO', 'unidad' => 'PZA', 'area' => 'Herramienta', 'abc' => 'A', 'stock_minimo' => null, 'cantidad' => 4, 'costo' => 1500, 'nota' => 'Unidad ausente en el layout; se asumió PZA.', 'ubicacion' => 'OBRA', 'idsteelex' => null, 'verifica' => false],
            ['descripcion' => 'PLATAFORMA DE ANDAMIO', 'unidad' => 'PZA', 'area' => 'Herramienta', 'abc' => 'A', 'stock_minimo' => null, 'cantidad' => 1, 'costo' => 1500, 'nota' => 'Unidad ausente en el layout; se asumió PZA.', 'ubicacion' => 'OBRA', 'idsteelex' => null, 'verifica' => false],
            ['descripcion' => 'MANOMETRO DE OXIGENO', 'unidad' => 'PZA', 'area' => 'Herramienta', 'abc' => 'A', 'stock_minimo' => null, 'cantidad' => 1, 'costo' => 1515.88, 'nota' => 'Unidad ausente en el layout; se asumió PZA.', 'ubicacion' => 'OBRA', 'idsteelex' => null, 'verifica' => false],
        ];
    }
}
