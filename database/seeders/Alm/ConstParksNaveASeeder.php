<?php

namespace Database\Seeders\Alm;

/**
 * Almacén CONST (PARKS NAVE A): 13 artículos, $53,585.79.
 *
 * El layout llegó sin la columna de unidad; cada renglón dice qué se asumió.
 */
class ConstParksNaveASeeder extends CargaInicialSeeder
{
    protected function almacen(): string
    {
        return 'CONST';
    }

    protected function obra(): ?string
    {
        return 'PARKS NAVE A';
    }

    /**
     * @return list<array{descripcion: string, unidad: string, area: string|null, abc: string, stock_minimo: float|null, cantidad: float, costo: float|null, nota: string|null, ubicacion?: string|null, codigo_barras?: string|null, idsteelex?: string|null, verifica?: bool}>
     */
    protected function articulos(): array
    {
        return [
            ['descripcion' => 'CABLE PORTAELECTRODO 1/0', 'unidad' => 'MTS', 'area' => 'Herramienta', 'abc' => 'A', 'stock_minimo' => null, 'cantidad' => 13.5, 'costo' => 166, 'nota' => 'Unidad ausente en el layout; se asumió MTS.', 'ubicacion' => 'OBRA', 'idsteelex' => null, 'verifica' => false],
            ['descripcion' => 'CABLE DE USO RUDO 2X12', 'unidad' => 'MTS', 'area' => 'Herramienta', 'abc' => 'A', 'stock_minimo' => null, 'cantidad' => 18.5, 'costo' => 26, 'nota' => 'Unidad ausente en el layout; se asumió MTS.', 'ubicacion' => 'OBRA', 'idsteelex' => null, 'verifica' => false],
            ['descripcion' => 'CABLE DE USO RUDO 2X8', 'unidad' => 'MTS', 'area' => 'Herramienta', 'abc' => 'A', 'stock_minimo' => null, 'cantidad' => 148, 'costo' => 113, 'nota' => 'Unidad ausente en el layout; se asumió MTS.', 'ubicacion' => 'OBRA', 'idsteelex' => null, 'verifica' => false],
            ['descripcion' => 'MANGUERA NEUMATICA', 'unidad' => 'MTS', 'area' => 'Herramienta', 'abc' => 'A', 'stock_minimo' => null, 'cantidad' => 200, 'costo' => 50.07, 'nota' => 'Unidad ausente en el layout; se asumió MTS.', 'ubicacion' => 'OBRA', 'idsteelex' => null, 'verifica' => false],
            ['descripcion' => 'LONAS INGNIFUGAS', 'unidad' => 'PZA', 'area' => 'Herramienta', 'abc' => 'A', 'stock_minimo' => null, 'cantidad' => 1, 'costo' => 1012.93, 'nota' => 'Unidad ausente en el layout; se asumió PZA.', 'ubicacion' => 'OBRA', 'idsteelex' => null, 'verifica' => false],
            ['descripcion' => 'DADOS DE IMPACTO', 'unidad' => 'PZA', 'area' => 'Herramienta', 'abc' => 'A', 'stock_minimo' => null, 'cantidad' => 2, 'costo' => 451.71, 'nota' => 'Unidad ausente en el layout; se asumió PZA.', 'ubicacion' => 'OBRA', 'idsteelex' => null, 'verifica' => false],
            ['descripcion' => 'REFLECTORES PARA EXTERIOR', 'unidad' => 'PZA', 'area' => 'Herramienta', 'abc' => 'A', 'stock_minimo' => null, 'cantidad' => 6, 'costo' => 1126.9, 'nota' => 'Unidad ausente en el layout; se asumió PZA.', 'ubicacion' => 'OBRA', 'idsteelex' => null, 'verifica' => false],
            ['descripcion' => 'TABLERO ELECTRICO', 'unidad' => 'PZA', 'area' => 'Herramienta', 'abc' => 'A', 'stock_minimo' => null, 'cantidad' => 1, 'costo' => 3000, 'nota' => 'Unidad ausente en el layout; se asumió PZA.', 'ubicacion' => 'OBRA', 'idsteelex' => null, 'verifica' => false],
            ['descripcion' => 'TANQUES DE GAS 20 KG', 'unidad' => 'PZA', 'area' => 'Herramienta', 'abc' => 'A', 'stock_minimo' => null, 'cantidad' => 1, 'costo' => 1720, 'nota' => 'Unidad ausente en el layout; se asumió PZA.', 'ubicacion' => 'OBRA', 'idsteelex' => null, 'verifica' => false],
            ['descripcion' => 'TANQUES OXIGENO 9 M3', 'unidad' => 'PZA', 'area' => 'Herramienta', 'abc' => 'A', 'stock_minimo' => null, 'cantidad' => 1, 'costo' => 8000, 'nota' => 'Unidad ausente en el layout; se asumió PZA.', 'ubicacion' => 'OBRA', 'idsteelex' => null, 'verifica' => false],
            ['descripcion' => 'CINCEL DE CORTE FRIO', 'unidad' => 'PZA', 'area' => 'Herramienta', 'abc' => 'A', 'stock_minimo' => null, 'cantidad' => 1, 'costo' => 71.04, 'nota' => 'Unidad ausente en el layout; se asumió PZA.', 'ubicacion' => 'OBRA', 'idsteelex' => null, 'verifica' => false],
            ['descripcion' => 'ESCUADRA DE METAL 12"', 'unidad' => 'PZA', 'area' => 'Herramienta', 'abc' => 'A', 'stock_minimo' => null, 'cantidad' => 1, 'costo' => 57, 'nota' => 'Unidad ausente en el layout; se asumió PZA.', 'ubicacion' => 'OBRA', 'idsteelex' => null, 'verifica' => false],
            ['descripcion' => 'LINEA DE VIDA DOBLE GANCHO GRANDE C', 'unidad' => 'PZA', 'area' => 'Herramienta', 'abc' => 'A', 'stock_minimo' => null, 'cantidad' => 4, 'costo' => 650, 'nota' => 'Unidad ausente en el layout; se asumió PZA.', 'ubicacion' => 'OBRA', 'idsteelex' => null, 'verifica' => false],
        ];
    }
}
