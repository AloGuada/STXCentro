<?php

namespace Database\Seeders\Alm;

/**
 * Almacén CONST (MBP): 16 artículos, $156,710.18.
 *
 * El layout llegó sin la columna de unidad; cada renglón dice qué se asumió.
 */
class ConstMbpSeeder extends CargaInicialSeeder
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
     * @return list<array{descripcion: string, unidad: string, area: string|null, abc: string, stock_minimo: float|null, cantidad: float, costo: float|null, nota: string|null, ubicacion?: string|null, codigo_barras?: string|null, idsteelex?: string|null, verifica?: bool}>
     */
    protected function articulos(): array
    {
        return [
            ['descripcion' => 'CABLE DE USO RUDO 2X12', 'unidad' => 'MTS', 'area' => 'Herramienta', 'abc' => 'A', 'stock_minimo' => null, 'cantidad' => 260.1, 'costo' => 26, 'nota' => 'Unidad ausente en el layout; se asumió MTS.', 'ubicacion' => 'OBRA', 'idsteelex' => null, 'verifica' => false],
            ['descripcion' => 'CABLE DE USO RUDO 2X8', 'unidad' => 'MTS', 'area' => 'Herramienta', 'abc' => 'A', 'stock_minimo' => null, 'cantidad' => 30, 'costo' => 113, 'nota' => 'Unidad ausente en el layout; se asumió MTS.', 'ubicacion' => 'OBRA', 'idsteelex' => null, 'verifica' => false],
            ['descripcion' => 'CABLE PORTAELECTRODO 1/0', 'unidad' => 'MTS', 'area' => 'Herramienta', 'abc' => 'A', 'stock_minimo' => null, 'cantidad' => 34.72, 'costo' => 166, 'nota' => 'Unidad ausente en el layout; se asumió MTS.', 'ubicacion' => 'OBRA', 'idsteelex' => null, 'verifica' => false],
            ['descripcion' => 'ESCALERA DE TIJERA', 'unidad' => 'PZA', 'area' => 'Herramienta', 'abc' => 'A', 'stock_minimo' => null, 'cantidad' => 1, 'costo' => 1900, 'nota' => 'Unidad ausente en el layout; se asumió PZA.', 'ubicacion' => 'OBRA', 'idsteelex' => null, 'verifica' => false],
            ['descripcion' => 'ESCALERA TELESCOPICA', 'unidad' => 'PZA', 'area' => 'Herramienta', 'abc' => 'A', 'stock_minimo' => null, 'cantidad' => 1, 'costo' => 2150, 'nota' => 'Unidad ausente en el layout; se asumió PZA.', 'ubicacion' => 'OBRA', 'idsteelex' => null, 'verifica' => false],
            ['descripcion' => 'ESCALERA MULTIPOSICION', 'unidad' => 'PZA', 'area' => 'Herramienta', 'abc' => 'A', 'stock_minimo' => null, 'cantidad' => 1, 'costo' => 2150, 'nota' => 'Unidad ausente en el layout; se asumió PZA.', 'ubicacion' => 'OBRA', 'idsteelex' => null, 'verifica' => false],
            ['descripcion' => 'ESLINGA DE 4 MTS', 'unidad' => 'PZA', 'area' => 'Herramienta', 'abc' => 'A', 'stock_minimo' => null, 'cantidad' => 1, 'costo' => 1889.64, 'nota' => 'Unidad ausente en el layout; se asumió PZA.', 'ubicacion' => 'OBRA', 'idsteelex' => null, 'verifica' => false],
            ['descripcion' => 'CINCEL DE CORTE FRIO', 'unidad' => 'PZA', 'area' => 'Herramienta', 'abc' => 'A', 'stock_minimo' => null, 'cantidad' => 4, 'costo' => 71.04, 'nota' => 'Unidad ausente en el layout; se asumió PZA.', 'ubicacion' => 'OBRA', 'idsteelex' => null, 'verifica' => false],
            ['descripcion' => 'REFLECTORES PARA EXTERIOR', 'unidad' => 'PZA', 'area' => 'Herramienta', 'abc' => 'A', 'stock_minimo' => null, 'cantidad' => 3, 'costo' => 1126.9, 'nota' => 'Unidad ausente en el layout; se asumió PZA.', 'ubicacion' => 'OBRA', 'idsteelex' => null, 'verifica' => false],
            ['descripcion' => 'CRUCETA DE ANDAMIO', 'unidad' => 'PZA', 'area' => 'Herramienta', 'abc' => 'A', 'stock_minimo' => null, 'cantidad' => 22, 'costo' => 1500, 'nota' => 'Unidad ausente en el layout; se asumió PZA.', 'ubicacion' => 'OBRA', 'idsteelex' => null, 'verifica' => false],
            ['descripcion' => 'MARCO DE ANDAMIO', 'unidad' => 'PZA', 'area' => 'Herramienta', 'abc' => 'A', 'stock_minimo' => null, 'cantidad' => 24, 'costo' => 2500, 'nota' => 'Unidad ausente en el layout; se asumió PZA.', 'ubicacion' => 'OBRA', 'idsteelex' => null, 'verifica' => false],
            ['descripcion' => 'PLATAFORMA DE ANDAMIO', 'unidad' => 'PZA', 'area' => 'Herramienta', 'abc' => 'A', 'stock_minimo' => null, 'cantidad' => 6, 'costo' => 1500, 'nota' => 'Unidad ausente en el layout; se asumió PZA.', 'ubicacion' => 'OBRA', 'idsteelex' => null, 'verifica' => false],
            ['descripcion' => 'RUEDA DE ANDAMIO', 'unidad' => 'PZA', 'area' => 'Herramienta', 'abc' => 'A', 'stock_minimo' => null, 'cantidad' => 16, 'costo' => 500, 'nota' => 'Unidad ausente en el layout; se asumió PZA.', 'ubicacion' => 'OBRA', 'idsteelex' => null, 'verifica' => false],
            ['descripcion' => 'ARNES DE CUERPO COMPLETO', 'unidad' => 'PZA', 'area' => 'Herramienta', 'abc' => 'A', 'stock_minimo' => null, 'cantidad' => 20, 'costo' => 392.24, 'nota' => 'Unidad ausente en el layout; se asumió PZA.', 'ubicacion' => 'OBRA', 'idsteelex' => null, 'verifica' => false],
            ['descripcion' => 'LINEA DE VIDA DOBLE GANCHO GRANDE C', 'unidad' => 'PZA', 'area' => 'Herramienta', 'abc' => 'A', 'stock_minimo' => null, 'cantidad' => 17, 'costo' => 650, 'nota' => 'Unidad ausente en el layout; se asumió PZA.', 'ubicacion' => 'OBRA', 'idsteelex' => null, 'verifica' => false],
            ['descripcion' => 'CARETAS FACIALES', 'unidad' => 'PZA', 'area' => 'Herramienta', 'abc' => 'A', 'stock_minimo' => null, 'cantidad' => 1, 'costo' => 144.76, 'nota' => 'Unidad ausente en el layout; se asumió PZA.', 'ubicacion' => 'OBRA', 'idsteelex' => null, 'verifica' => false],
        ];
    }
}
