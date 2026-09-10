<?php

namespace Database\Seeders\Alm;

/**
 * Almacén CONST (SIX PARKS CANCUN): 11 artículos, $68,073.27.
 *
 * El layout llegó sin la columna de unidad; cada renglón dice qué se asumió.
 */
class ConstSixParksCancunSeeder extends CargaInicialSeeder
{
    protected function almacen(): string
    {
        return 'CONST';
    }

    protected function obra(): ?string
    {
        return 'SIX PARKS CANCUN';
    }

    /**
     * @return list<array{descripcion: string, unidad: string, area: string|null, abc: string, stock_minimo: float|null, cantidad: float, costo: float|null, nota: string|null, ubicacion?: string|null, codigo_barras?: string|null, idsteelex?: string|null, verifica?: bool}>
     */
    protected function articulos(): array
    {
        return [
            ['descripcion' => 'CABLE DE ALUMINIO', 'unidad' => 'MTS', 'area' => 'Herramienta', 'abc' => 'A', 'stock_minimo' => null, 'cantidad' => 25, 'costo' => 26, 'nota' => 'Unidad ausente en el layout; se asumió MTS.', 'ubicacion' => 'OBRA', 'idsteelex' => null, 'verifica' => false],
            ['descripcion' => 'TANQUES OXIGENO 9 M3', 'unidad' => 'PZA', 'area' => 'Herramienta', 'abc' => 'A', 'stock_minimo' => null, 'cantidad' => 4, 'costo' => 8000, 'nota' => 'Unidad ausente en el layout; se asumió PZA.', 'ubicacion' => 'OBRA', 'idsteelex' => null, 'verifica' => false],
            ['descripcion' => 'ESCALERA TELESCOPICA', 'unidad' => 'PZA', 'area' => 'Herramienta', 'abc' => 'A', 'stock_minimo' => null, 'cantidad' => 2, 'costo' => 2150, 'nota' => 'Unidad ausente en el layout; se asumió PZA.', 'ubicacion' => 'OBRA', 'idsteelex' => null, 'verifica' => false],
            ['descripcion' => 'IMPRESORA', 'unidad' => 'PZA', 'area' => 'Herramienta', 'abc' => 'A', 'stock_minimo' => null, 'cantidad' => 1, 'costo' => 4050.86, 'nota' => 'Unidad ausente en el layout; se asumió PZA.', 'ubicacion' => 'OBRA', 'idsteelex' => null, 'verifica' => false],
            ['descripcion' => 'MESA', 'unidad' => 'PZA', 'area' => 'Herramienta', 'abc' => 'A', 'stock_minimo' => null, 'cantidad' => 1, 'costo' => 1477.61, 'nota' => 'Unidad ausente en el layout; se asumió PZA.', 'ubicacion' => 'OBRA', 'idsteelex' => null, 'verifica' => false],
            ['descripcion' => 'SILLAS PEGABLES', 'unidad' => 'PZA', 'area' => 'Herramienta', 'abc' => 'A', 'stock_minimo' => null, 'cantidad' => 2, 'costo' => 400, 'nota' => 'Unidad ausente en el layout; se asumió PZA.', 'ubicacion' => 'OBRA', 'idsteelex' => null, 'verifica' => false],
            ['descripcion' => 'TABLERO ELECTRICO', 'unidad' => 'PZA', 'area' => 'Herramienta', 'abc' => 'A', 'stock_minimo' => null, 'cantidad' => 1, 'costo' => 3000, 'nota' => 'Unidad ausente en el layout; se asumió PZA.', 'ubicacion' => 'OBRA', 'idsteelex' => null, 'verifica' => false],
            ['descripcion' => 'ARNES DE CUERPO COMPLETO', 'unidad' => 'PZA', 'area' => 'Herramienta', 'abc' => 'A', 'stock_minimo' => null, 'cantidad' => 20, 'costo' => 392.24, 'nota' => 'Unidad ausente en el layout; se asumió PZA.', 'ubicacion' => 'OBRA', 'idsteelex' => null, 'verifica' => false],
            ['descripcion' => 'LINEA DE VIDA DOBLE GANCHO GRANDE C', 'unidad' => 'PZA', 'area' => 'Herramienta', 'abc' => 'A', 'stock_minimo' => null, 'cantidad' => 20, 'costo' => 650, 'nota' => 'Unidad ausente en el layout; se asumió PZA.', 'ubicacion' => 'OBRA', 'idsteelex' => null, 'verifica' => false],
            ['descripcion' => 'LLAVE PERICA CROMADA DE 15"', 'unidad' => 'PZA', 'area' => 'Herramienta', 'abc' => 'A', 'stock_minimo' => null, 'cantidad' => 2, 'costo' => 420, 'nota' => 'Unidad ausente en el layout; se asumió PZA.', 'ubicacion' => 'OBRA', 'idsteelex' => null, 'verifica' => false],
            ['descripcion' => 'LLAVE PERICA CROMADA DE 8"', 'unidad' => 'PZA', 'area' => 'Herramienta', 'abc' => 'A', 'stock_minimo' => null, 'cantidad' => 1, 'costo' => 110, 'nota' => 'Unidad ausente en el layout; se asumió PZA.', 'ubicacion' => 'OBRA', 'idsteelex' => null, 'verifica' => false],
        ];
    }
}
