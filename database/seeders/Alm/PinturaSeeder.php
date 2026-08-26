<?php

namespace Database\Seeders\Alm;

/**
 * Almacén de Pintura (PIN): 39 artículos, $1,034,070.75.
 *
 * Dos cosas que trae este layout y que quedaron cuadradas aquí:
 *
 * El primario y el thinner se contaron en litros pero se cotizaron por tambor
 * de 200: $11,400 y $5,200 son el precio del tambor, no del litro. Entran ya
 * divididos —$57 y $26— porque el promedio nacería multiplicado por 200 y cada
 * salida le cargaría eso de más a la obra. La cuenta queda en la nota del
 * renglón.
 *
 * La columna OBRA del layout venía con la obra a la que está asignado cada bote,
 * no con el almacén donde vive. No mueve saldo —la existencia es
 * almacén+artículo y no tiene esa dimensión— pero se guarda en la nota.
 *
 * El solvente para pintura amarilla llegó sin costo: arranca valuado en cero y
 * su primer consumo sale gratis hasta que entre una compra que mueva el
 * promedio.
 */
class PinturaSeeder extends CargaInicialSeeder
{
    protected function almacen(): string
    {
        return 'PIN';
    }

    /**
     * @return list<array{descripcion: string, unidad: string, area: string|null, abc: string, stock_minimo: float|null, cantidad: float, costo: float|null, nota: string|null}>
     */
    protected function articulos(): array
    {
        return [
            ['descripcion' => 'Pintura Sylpyl No. 14 Rojo oxido Base ( 18 Lts.)', 'unidad' => 'PZA', 'area' => 'Pintura', 'abc' => 'C', 'stock_minimo' => null, 'cantidad' => 20, 'costo' => 5599.3, 'nota' => 'Asignado a: Andenes Campeche.'],
            ['descripcion' => 'Reactor Sylpyl No. 14 Reactor ( 18 Lts.)', 'unidad' => 'PZA', 'area' => 'Pintura', 'abc' => 'C', 'stock_minimo' => null, 'cantidad' => 2, 'costo' => 3822, 'nota' => 'Asignado a: Andenes Campeche.'],
            ['descripcion' => 'Solvente Sylfyl No. 12 ( 20 Lts )', 'unidad' => 'PZA', 'area' => 'Pintura', 'abc' => 'C', 'stock_minimo' => null, 'cantidad' => 6, 'costo' => 1665.6, 'nota' => 'Asignado a: Andenes Campeche.'],
            ['descripcion' => 'Intermedio Gris Sylfyl 2600 ( 12 Lts.)', 'unidad' => 'PZA', 'area' => 'Pintura', 'abc' => 'C', 'stock_minimo' => null, 'cantidad' => 2, 'costo' => 5635.8, 'nota' => 'Asignado a: Azvindi.'],
            ['descripcion' => 'Reactivo Intermedio 2600C Sylfyl ( 4 Lts.)', 'unidad' => 'PZA', 'area' => 'Pintura', 'abc' => 'C', 'stock_minimo' => null, 'cantidad' => 3, 'costo' => 1023.24, 'nota' => 'Asignado a: Azvindi.'],
            ['descripcion' => 'Esmalte P2020 color gris plata Pazti ( 19 Lts.)', 'unidad' => 'PZA', 'area' => 'Pintura', 'abc' => 'C', 'stock_minimo' => null, 'cantidad' => 39, 'costo' => 1425, 'nota' => 'Asignado a: Bepensa Tulum.'],
            ['descripcion' => 'Adelgazador No. 2 Comex ( 20 Lts.)', 'unidad' => 'PZA', 'area' => 'Pintura', 'abc' => 'C', 'stock_minimo' => null, 'cantidad' => 6, 'costo' => 2671.55, 'nota' => 'Asignado a: Canopis.'],
            ['descripcion' => 'Polvo de Zinc Rp-4B Comex ( 6.5 Kgs.)', 'unidad' => 'PZA', 'area' => 'Pintura', 'abc' => 'C', 'stock_minimo' => null, 'cantidad' => 11, 'costo' => 1251.4, 'nota' => 'Asignado a: Canopis.'],
            ['descripcion' => 'Endurecedor RP-6 ( 18 Lts.)', 'unidad' => 'PZA', 'area' => 'Pintura', 'abc' => 'C', 'stock_minimo' => null, 'cantidad' => 3, 'costo' => 3932.78, 'nota' => 'Asignado a: CHEDRAUI TULUM.'],
            ['descripcion' => 'Sigmafast 278 bas  mio light ( 20 Lts.)', 'unidad' => 'PZA', 'area' => 'Pintura', 'abc' => 'C', 'stock_minimo' => null, 'cantidad' => 1, 'costo' => 4000, 'nota' => 'Asignado a: Cubierta Jardinada.'],
            ['descripcion' => 'Sigmafast 278 Hardener ( 5 Lts.)', 'unidad' => 'PZA', 'area' => 'Pintura', 'abc' => 'C', 'stock_minimo' => null, 'cantidad' => 4, 'costo' => 1265.93, 'nota' => 'Asignado a: Cubierta Jardinada.'],
            ['descripcion' => 'Polvo de Zinc RP-4B Polovo 053 Comex ( 6.5 Kgs.)', 'unidad' => 'PZA', 'area' => 'Pintura', 'abc' => 'C', 'stock_minimo' => null, 'cantidad' => 12, 'costo' => 1251.4, 'nota' => 'Asignado a: Luxury La Isla.'],
            ['descripcion' => 'Adelgazador No. 5 Comex ( 19 Lts.)', 'unidad' => 'PZA', 'area' => 'Pintura', 'abc' => 'C', 'stock_minimo' => null, 'cantidad' => 5, 'costo' => 2671.56, 'nota' => 'Asignado a: Luxury La Isla.'],
            ['descripcion' => 'Primario contraoxido Blanco Pázti ( 19 Lts.)', 'unidad' => 'PZA', 'area' => 'Pintura', 'abc' => 'C', 'stock_minimo' => null, 'cantidad' => 2, 'costo' => 1083, 'nota' => 'Asignado a: Nave RM Aluminio.'],
            ['descripcion' => 'Esmalte Rex Igualado basico/naranja ( 19 Lts.)', 'unidad' => 'PZA', 'area' => 'Pintura', 'abc' => 'C', 'stock_minimo' => null, 'cantidad' => 2, 'costo' => 1083, 'nota' => 'Asignado a: Planta.'],
            ['descripcion' => 'Primario anticorrosivo color gris Pazti ( 200 Lts.)', 'unidad' => 'LTS', 'area' => 'Pintura', 'abc' => 'A', 'stock_minimo' => 600, 'cantidad' => 1650, 'costo' => 57, 'nota' => 'Asignado a: Planta. Costo prorrateado del envase de 200 LTS: 11400 / 200.'],
            ['descripcion' => 'Thiner Estandar Pazti ( 200 Lts.)', 'unidad' => 'LTS', 'area' => 'Pintura', 'abc' => 'A', 'stock_minimo' => 400, 'cantidad' => 1200, 'costo' => 26, 'nota' => 'Asignado a: Planta. Costo prorrateado del envase de 200 LTS: 5200 / 200.'],
            ['descripcion' => 'Polvo de Zinc Dimetcote 9 D9VOC/D9H Powder Comex Playa ( 6.5 Kgs.)', 'unidad' => 'PZA', 'area' => 'Pintura', 'abc' => 'C', 'stock_minimo' => null, 'cantidad' => 67, 'costo' => 1292.46, 'nota' => 'Asignado a: Playa del Carmen.'],
            ['descripcion' => 'Kit poliuretano color antracita ( 20 Lts.)', 'unidad' => 'PZA', 'area' => 'Pintura', 'abc' => 'C', 'stock_minimo' => null, 'cantidad' => 2, 'costo' => 4345, 'nota' => 'Asignado a: Prime center.'],
            ['descripcion' => 'Dry Fall Negro ( 19 Lts.)', 'unidad' => 'PZA', 'area' => 'Pintura', 'abc' => 'C', 'stock_minimo' => null, 'cantidad' => 32, 'costo' => 1987, 'nota' => 'Asignado a: Prime center.'],
            ['descripcion' => 'Galvanizado en Frio ( 3.785 Lts.)', 'unidad' => 'PZA', 'area' => 'Pintura', 'abc' => 'B', 'stock_minimo' => null, 'cantidad' => 3, 'costo' => 5840, 'nota' => 'Asignado a: Reproductora.'],
            ['descripcion' => 'Adelgazador p/primario Alquidalico y hule Clorado ( 19 Lts.)', 'unidad' => 'PZA', 'area' => 'Pintura', 'abc' => 'C', 'stock_minimo' => null, 'cantidad' => 1, 'costo' => 1300, 'nota' => 'Asignado a: Sams Club.'],
            ['descripcion' => 'Xilol ( 1 Lts.)', 'unidad' => 'LTS', 'area' => 'Pintura', 'abc' => 'B', 'stock_minimo' => null, 'cantidad' => 20, 'costo' => 106.9, 'nota' => 'Asignado a: Talleres Cancun.'],
            ['descripcion' => 'Kit Epoxipasti gris RP6 ( 38 Lts.)', 'unidad' => 'PZA', 'area' => 'Pintura', 'abc' => 'C', 'stock_minimo' => null, 'cantidad' => 2, 'costo' => 6956.03, 'nota' => 'Asignado a: Tulum Chedraui.'],
            ['descripcion' => 'Sigmadur 550 Base White ( 17.6 Lts.)', 'unidad' => 'PZA', 'area' => 'Pintura', 'abc' => 'A', 'stock_minimo' => 15, 'cantidad' => 20, 'costo' => 4000, 'nota' => 'Asignado a: T4 Aeropuerto.'],
            ['descripcion' => 'Sigmadur 550 Hardener ( 2.4 Lts.)', 'unidad' => 'PZA', 'area' => 'Pintura', 'abc' => 'A', 'stock_minimo' => 15, 'cantidad' => 20, 'costo' => 2040.517, 'nota' => 'Asignado a: T4 Aeropuerto.'],
            ['descripcion' => 'Thinner 21-06 ( 20 Lts.)', 'unidad' => 'PZA', 'area' => 'Pintura', 'abc' => 'A', 'stock_minimo' => 5, 'cantidad' => 22, 'costo' => 2000, 'nota' => 'Asignado a: T4 Aeropuerto.'],
            ['descripcion' => 'Sigmafast 278 Base Grey Dal 7035 ( 12 Lts.)', 'unidad' => 'PZA', 'area' => 'Pintura', 'abc' => 'A', 'stock_minimo' => 15, 'cantidad' => 19, 'costo' => 2147.25, 'nota' => 'Asignado a: T4 Aeropuerto.'],
            ['descripcion' => 'Thinner 91-08 ( 20 Lts.)', 'unidad' => 'PZA', 'area' => 'Pintura', 'abc' => 'A', 'stock_minimo' => 5, 'cantidad' => 10, 'costo' => 2000, 'nota' => 'Asignado a: T4 Aeropuerto.'],
            ['descripcion' => 'Sigmafast 278 Hardener ( 4 Lts.)', 'unidad' => 'PZA', 'area' => 'Pintura', 'abc' => 'A', 'stock_minimo' => 15, 'cantidad' => 19, 'costo' => 750.175, 'nota' => 'Asignado a: T4 Aeropuerto.'],
            ['descripcion' => 'Steel Guard 119W Blanco ( 19 Lts.)', 'unidad' => 'PZA', 'area' => 'Pintura', 'abc' => 'A', 'stock_minimo' => 15, 'cantidad' => 35, 'costo' => 3625, 'nota' => 'Asignado a: T4 Aeropuerto.'],
            ['descripcion' => 'Desoxidante desengra dua L Etch ( 4 Lts.)', 'unidad' => 'PZA', 'area' => 'Pintura', 'abc' => 'B', 'stock_minimo' => null, 'cantidad' => 10, 'costo' => 539.99, 'nota' => 'Asignado a: Mendez.'],
            ['descripcion' => 'Dry Fall Blanco Mate ( 19 Lts.)', 'unidad' => 'PZA', 'area' => 'Pintura', 'abc' => 'B', 'stock_minimo' => null, 'cantidad' => 6, 'costo' => 2527.92, 'nota' => 'Asignado a: Parks Nave A.'],
            ['descripcion' => 'Adelgazador P/Dry Fall Blanco mate ( 20 Lts.)', 'unidad' => 'PZA', 'area' => 'Pintura', 'abc' => 'B', 'stock_minimo' => null, 'cantidad' => 2, 'costo' => 1053.4, 'nota' => 'Asignado a: Parks Nave A.'],
            ['descripcion' => 'U-21 Amarillo Ral 1028 ( 16 Lts.)', 'unidad' => 'PZA', 'area' => 'Pintura', 'abc' => 'A', 'stock_minimo' => null, 'cantidad' => 5, 'costo' => 3181.17, 'nota' => 'Asignado a: Gruas Steelex.'],
            ['descripcion' => 'U-21 Endurecedor ( 4 Lts.)', 'unidad' => 'PZA', 'area' => 'Pintura', 'abc' => 'A', 'stock_minimo' => null, 'cantidad' => 5, 'costo' => 1089.74, 'nota' => 'Asignado a: Gruas Steelex.'],
            ['descripcion' => 'Thiner 91-21 ( 20 Lts.)', 'unidad' => 'PZA', 'area' => 'Pintura', 'abc' => 'A', 'stock_minimo' => null, 'cantidad' => 2, 'costo' => 2120.5, 'nota' => 'Asignado a: Gruas Steelex.'],
            ['descripcion' => 'Pintura amarilla Pazti ( 20 Lts.)', 'unidad' => 'PZA', 'area' => 'Pintura', 'abc' => 'A', 'stock_minimo' => null, 'cantidad' => 4, 'costo' => 4293.1, 'nota' => 'Asignado a: Gruas Steelex.'],
            ['descripcion' => 'Solvente para pintura amarilla ( 20 Lts.)', 'unidad' => 'PZA', 'area' => 'Pintura', 'abc' => 'A', 'stock_minimo' => null, 'cantidad' => 4, 'costo' => null, 'nota' => 'Asignado a: Gruas Steelex.'],
        ];
    }
}
