<?php

namespace Database\Seeders\Alm;

/**
 * Almacén CONST (TRES GUERRAS): 15 artículos, $84,875.01.
 *
 * El layout llegó sin la columna de unidad; cada renglón dice qué se asumió.
 */
class ConstTresGuerrasSeeder extends CargaInicialSeeder
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
     * @return list<array{descripcion: string, unidad: string, area: string|null, abc: string, stock_minimo: float|null, cantidad: float, costo: float|null, nota: string|null, ubicacion?: string|null, codigo_barras?: string|null, idsteelex?: string|null, verifica?: bool}>
     */
    protected function articulos(): array
    {
        return [
            ['descripcion' => 'CABLE DE USO RUDO 3X10', 'unidad' => 'MTS', 'area' => 'Herramienta', 'abc' => 'A', 'stock_minimo' => null, 'cantidad' => 68.6, 'costo' => 113, 'nota' => 'Unidad ausente en el layout; se asumió MTS.', 'ubicacion' => 'OBRA', 'idsteelex' => null, 'verifica' => false],
            ['descripcion' => 'CABLE DE USO RUDO 3X8', 'unidad' => 'MTS', 'area' => 'Herramienta', 'abc' => 'A', 'stock_minimo' => null, 'cantidad' => 102, 'costo' => 113, 'nota' => 'Unidad ausente en el layout; se asumió MTS.', 'ubicacion' => 'OBRA', 'idsteelex' => null, 'verifica' => false],
            ['descripcion' => 'CARRITOS DE OXICORTE', 'unidad' => 'PZA', 'area' => 'Herramienta', 'abc' => 'A', 'stock_minimo' => null, 'cantidad' => 2, 'costo' => 2475, 'nota' => 'Unidad ausente en el layout; se asumió PZA.', 'ubicacion' => 'OBRA', 'idsteelex' => null, 'verifica' => false],
            ['descripcion' => 'EQUIPO DE OXICORTE COMPLETO', 'unidad' => 'PZA', 'area' => 'Herramienta', 'abc' => 'A', 'stock_minimo' => null, 'cantidad' => 1, 'costo' => 4800, 'nota' => 'Unidad ausente en el layout; se asumió PZA.', 'ubicacion' => 'OBRA', 'idsteelex' => null, 'verifica' => false],
            ['descripcion' => 'TANQUES DE GAS 20 KG', 'unidad' => 'PZA', 'area' => 'Herramienta', 'abc' => 'A', 'stock_minimo' => null, 'cantidad' => 2, 'costo' => 1720, 'nota' => 'Unidad ausente en el layout; se asumió PZA.', 'ubicacion' => 'OBRA', 'idsteelex' => null, 'verifica' => false],
            ['descripcion' => 'TANQUES OXIGENO 9 M3', 'unidad' => 'PZA', 'area' => 'Herramienta', 'abc' => 'A', 'stock_minimo' => null, 'cantidad' => 3, 'costo' => 8000, 'nota' => 'Unidad ausente en el layout; se asumió PZA.', 'ubicacion' => 'OBRA', 'idsteelex' => null, 'verifica' => false],
            ['descripcion' => 'DADOS DE IMPACTO', 'unidad' => 'PZA', 'area' => 'Herramienta', 'abc' => 'A', 'stock_minimo' => null, 'cantidad' => 4, 'costo' => 451.71, 'nota' => 'Unidad ausente en el layout; se asumió PZA.', 'ubicacion' => 'OBRA', 'idsteelex' => null, 'verifica' => false],
            ['descripcion' => 'TABLERO ELECTRICO', 'unidad' => 'PZA', 'area' => 'Herramienta', 'abc' => 'A', 'stock_minimo' => null, 'cantidad' => 1, 'costo' => 3000, 'nota' => 'Unidad ausente en el layout; se asumió PZA.', 'ubicacion' => 'OBRA', 'idsteelex' => null, 'verifica' => false],
            ['descripcion' => 'ARNES DE CUERPO COMPLETO', 'unidad' => 'PZA', 'area' => 'Herramienta', 'abc' => 'A', 'stock_minimo' => null, 'cantidad' => 14, 'costo' => 392.24, 'nota' => 'Unidad ausente en el layout; se asumió PZA.', 'ubicacion' => 'OBRA', 'idsteelex' => null, 'verifica' => false],
            ['descripcion' => 'LINEA DE VIDA DOBLE GANCHO GRANDE C', 'unidad' => 'PZA', 'area' => 'Herramienta', 'abc' => 'A', 'stock_minimo' => null, 'cantidad' => 14, 'costo' => 650, 'nota' => 'Unidad ausente en el layout; se asumió PZA.', 'ubicacion' => 'OBRA', 'idsteelex' => null, 'verifica' => false],
            ['descripcion' => 'POSTES DE SEÑALAMIENTO TIPO VELA', 'unidad' => 'PZA', 'area' => 'Herramienta', 'abc' => 'A', 'stock_minimo' => null, 'cantidad' => 7, 'costo' => 286, 'nota' => 'Unidad ausente en el layout; se asumió PZA.', 'ubicacion' => 'OBRA', 'idsteelex' => null, 'verifica' => false],
            ['descripcion' => 'EXTINTORES PQS 4.5 KG', 'unidad' => 'PZA', 'area' => 'Herramienta', 'abc' => 'A', 'stock_minimo' => null, 'cantidad' => 4, 'costo' => 737.55, 'nota' => 'Unidad ausente en el layout; se asumió PZA.', 'ubicacion' => 'OBRA', 'idsteelex' => null, 'verifica' => false],
            ['descripcion' => 'CABLE DE ACERO FLEXIBLE', 'unidad' => 'MTS', 'area' => 'Herramienta', 'abc' => 'A', 'stock_minimo' => null, 'cantidad' => 80, 'costo' => 17.24, 'nota' => 'Unidad ausente en el layout; se asumió MTS.', 'ubicacion' => 'OBRA', 'idsteelex' => null, 'verifica' => false],
            ['descripcion' => 'MESA', 'unidad' => 'PZA', 'area' => 'Herramienta', 'abc' => 'A', 'stock_minimo' => null, 'cantidad' => 1, 'costo' => 1477.61, 'nota' => 'Unidad ausente en el layout; se asumió PZA.', 'ubicacion' => 'OBRA', 'idsteelex' => null, 'verifica' => false],
            ['descripcion' => 'SILLAS PEGABLES', 'unidad' => 'PZA', 'area' => 'Herramienta', 'abc' => 'A', 'stock_minimo' => null, 'cantidad' => 3, 'costo' => 400, 'nota' => 'Unidad ausente en el layout; se asumió PZA.', 'ubicacion' => 'OBRA', 'idsteelex' => null, 'verifica' => false],
        ];
    }
}
