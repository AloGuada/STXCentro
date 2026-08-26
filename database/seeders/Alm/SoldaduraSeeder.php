<?php

namespace Database\Seeders\Alm;

/**
 * Almacén de Soldadura (SOL): 11 artículos, $287,204.20.
 *
 * El layout limpio de los dos primeros que entregaron: todo en kilos, a costo
 * por kilo y sin obra asignada. Los dos renglones en cero se dan de alta igual,
 * porque el artículo existe aunque hoy no haya nada.
 */
class SoldaduraSeeder extends CargaInicialSeeder
{
    protected function almacen(): string
    {
        return 'SOL';
    }

    /**
     * @return list<array{descripcion: string, unidad: string, area: string|null, abc: string, stock_minimo: float|null, cantidad: float, costo: float|null, nota: string|null}>
     */
    protected function articulos(): array
    {
        return [
            ['descripcion' => 'SOLDADURA DE ALAMBRE 0.35', 'unidad' => 'KG', 'area' => 'Soldadura', 'abc' => 'B', 'stock_minimo' => 200, 'cantidad' => 105, 'costo' => 49.64, 'nota' => null],
            ['descripcion' => 'SOLDADURA DE ALAMBRE 0.45', 'unidad' => 'KG', 'area' => 'Soldadura', 'abc' => 'A', 'stock_minimo' => 500, 'cantidad' => 1380, 'costo' => 49.64, 'nota' => null],
            ['descripcion' => 'SOLDADURA DE ALAMBRE 0.52', 'unidad' => 'KG', 'area' => 'Soldadura', 'abc' => 'A', 'stock_minimo' => 500, 'cantidad' => 390, 'costo' => 49.64, 'nota' => null],
            ['descripcion' => 'SOLDADURA ARCO SUMERGIDO SAW 13K 3/16', 'unidad' => 'KG', 'area' => 'Soldadura', 'abc' => 'A', 'stock_minimo' => 500, 'cantidad' => 1380, 'costo' => 50.93, 'nota' => null],
            ['descripcion' => 'ELECTRODO PARA SOLDAR 7018 DE 1/8', 'unidad' => 'KG', 'area' => 'Soldadura', 'abc' => 'B', 'stock_minimo' => 100, 'cantidad' => 100, 'costo' => 54.84, 'nota' => null],
            ['descripcion' => 'ELECTRODO PARA SOLDAR 6013 DE 5/32', 'unidad' => 'KG', 'area' => 'Soldadura', 'abc' => 'B', 'stock_minimo' => 100, 'cantidad' => 120, 'costo' => 72.39, 'nota' => null],
            ['descripcion' => 'FUNDENTE PARA SOLDADURA', 'unidad' => 'KG', 'area' => 'Soldadura', 'abc' => 'A', 'stock_minimo' => 500, 'cantidad' => 900, 'costo' => 57.55, 'nota' => null],
            ['descripcion' => 'GRANALLA DE ACERO', 'unidad' => 'KG', 'area' => 'Soldadura', 'abc' => 'A', 'stock_minimo' => 500, 'cantidad' => 0, 'costo' => 25.7631, 'nota' => null],
            ['descripcion' => 'SOLDADURA ARCO SUMERGIDO SAW 13K 1/8', 'unidad' => 'KG', 'area' => 'Soldadura', 'abc' => 'A', 'stock_minimo' => 500, 'cantidad' => 0, 'costo' => 50.93, 'nota' => null],
            ['descripcion' => 'ELECTRODO PARA SOLDAR 6010 DE 1/8', 'unidad' => 'KG', 'area' => 'Soldadura', 'abc' => 'B', 'stock_minimo' => 100, 'cantidad' => 40, 'costo' => 71.89, 'nota' => null],
            ['descripcion' => 'SOLDADURA ARCO SUMERGIDO SAW 13K 3/32', 'unidad' => 'KG', 'area' => 'Soldadura', 'abc' => 'A', 'stock_minimo' => 500, 'cantidad' => 1080, 'costo' => 50.93, 'nota' => null],
        ];
    }
}
