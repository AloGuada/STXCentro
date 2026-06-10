<?php

/**
 * Catalogo de fletes y viaticos (plantilla reusable).
 *
 * Generado por database/seeders/Cotiz/_generar.php (porta prepsim).
 * NO editar a mano.
 */
return [
    ['grupo' => 'VIATICOS', 'orden' => 1, 'concepto' => 'Comida', 'unidad' => 'lote', 'p_unit_default' => 240, 'clave' => 'COMIDA', 'formula_cantidad' => 'dias * personas', 'formula_p_unit' => null],
    ['grupo' => 'VIATICOS', 'orden' => 2, 'concepto' => 'Habitación', 'unidad' => 'Mes', 'p_unit_default' => 15000, 'clave' => null, 'formula_cantidad' => 'meses', 'formula_p_unit' => 'roundup(personas/5, 0) * 5000'],
    ['grupo' => 'VIATICOS', 'orden' => 3, 'concepto' => 'Agua', 'unidad' => 'Semana', 'p_unit_default' => 45.6, 'clave' => null, 'formula_cantidad' => 'dias', 'formula_p_unit' => '0.2 * 19 * personas'],
    ['grupo' => 'VIATICOS', 'orden' => 4, 'concepto' => 'Subida y bajada de personal', 'unidad' => 'Pers', 'p_unit_default' => 8000, 'clave' => null, 'formula_cantidad' => 'personas', 'formula_p_unit' => 'roundup(semanas/4, 0) * 2000'],
    ['grupo' => 'VIATICOS', 'orden' => 5, 'concepto' => 'Transportación a obra', 'unidad' => 'Semana', 'p_unit_default' => 7000, 'clave' => null, 'formula_cantidad' => 'semanas', 'formula_p_unit' => 'roundup(personas/8, 0) * 3500'],
    ['grupo' => 'SUPERV_MONTAJE', 'orden' => 1, 'concepto' => 'Combustible', 'unidad' => 'Semana', 'p_unit_default' => 3500, 'clave' => 'COMBUSTIBLE', 'formula_cantidad' => 'semanas', 'formula_p_unit' => null],
    ['grupo' => 'SUPERV_MONTAJE', 'orden' => 2, 'concepto' => 'Mantenimiento vehículo', 'unidad' => 'lote', 'p_unit_default' => 2800, 'clave' => null, 'formula_cantidad' => '1', 'formula_p_unit' => 'importe_COMBUSTIBLE * 0.1'],
    ['grupo' => 'SUPERV_MONTAJE', 'orden' => 3, 'concepto' => 'Viáticos hospedaje', 'unidad' => 'Mes', 'p_unit_default' => 5000, 'clave' => null, 'formula_cantidad' => 'meses', 'formula_p_unit' => 'roundup(roundup(personas/25, 0) / 8, 0) * 5000'],
    ['grupo' => 'SUPERV_MONTAJE', 'orden' => 4, 'concepto' => 'Viáticos alimentos', 'unidad' => 'día', 'p_unit_default' => 240, 'clave' => null, 'formula_cantidad' => 'dias', 'formula_p_unit' => 'roundup(personas/25, 0) * 240'],
    ['grupo' => 'SUPERV_MONTAJE', 'orden' => 5, 'concepto' => 'Subida y bajada supervisión', 'unidad' => 'Pers', 'p_unit_default' => 8000, 'clave' => null, 'formula_cantidad' => 'roundup(personas/25, 0)', 'formula_p_unit' => '(semanas/4) * 2000'],
    ['grupo' => 'ENERGIA', 'orden' => 1, 'concepto' => 'Energía eléctrica para soldadura y torqueo', 'unidad' => 'MES', 'p_unit_default' => 50000, 'clave' => null, 'formula_cantidad' => 'roundup(semanas_fase_TORQUE/4.2, 1)', 'formula_p_unit' => null],
    ['grupo' => 'ENERGIA', 'orden' => 2, 'concepto' => 'Energía eléctrica para perneadora', 'unidad' => 'MES', 'p_unit_default' => 50000, 'clave' => null, 'formula_cantidad' => 'roundup(semanas_fase_LOSACERO/4.2, 2)', 'formula_p_unit' => null],
    ['grupo' => 'VARIOS', 'orden' => 1, 'concepto' => 'Equipo de seguridad (arneses, casco, chaleco, botas, guantes, lentes, peto, protector)', 'unidad' => 'PERS', 'p_unit_default' => 1935.23, 'clave' => null, 'formula_cantidad' => 'personas', 'formula_p_unit' => null],
    ['grupo' => 'VARIOS', 'orden' => 2, 'concepto' => 'Arneses con amortiguador y doble línea de vida', 'unidad' => 'PERS', 'p_unit_default' => 2300, 'clave' => null, 'formula_cantidad' => 'personas', 'formula_p_unit' => null],
    ['grupo' => 'VARIOS', 'orden' => 3, 'concepto' => 'Líneas de vida (cable acero 3/8" galv.)', 'unidad' => 'ML', 'p_unit_default' => 47.5, 'clave' => null, 'formula_cantidad' => null, 'formula_p_unit' => null],
    ['grupo' => 'VARIOS', 'orden' => 4, 'concepto' => 'Ganchos para líneas de vida + perros', 'unidad' => 'PZAS', 'p_unit_default' => 250.1, 'clave' => 'GANCHOS', 'formula_cantidad' => 'personas', 'formula_p_unit' => null],
    ['grupo' => 'VARIOS', 'orden' => 5, 'concepto' => 'Malla de protección + cable acerado 3/16"', 'unidad' => 'ML', 'p_unit_default' => 48.03, 'clave' => 'MALLA', 'formula_cantidad' => null, 'formula_p_unit' => null],
    ['grupo' => 'VARIOS', 'orden' => 6, 'concepto' => 'Cinta de advertencia roja/amarilla', 'unidad' => 'ML', 'p_unit_default' => 0.35, 'clave' => null, 'formula_cantidad' => 'cantidad_MALLA', 'formula_p_unit' => null],
    ['grupo' => 'VARIOS', 'orden' => 7, 'concepto' => 'Postes para líneas de vida (PTR 2x2x3/16" L=1m @ 10m)', 'unidad' => 'PZAS', 'p_unit_default' => 100, 'clave' => null, 'formula_cantidad' => 'cantidad_GANCHOS / 2', 'formula_p_unit' => null],
    ['grupo' => 'VARIOS', 'orden' => 8, 'concepto' => 'Retiro de residuos peligrosos', 'unidad' => 'LOTE', 'p_unit_default' => 15000, 'clave' => null, 'formula_cantidad' => null, 'formula_p_unit' => null],
    ['grupo' => 'VARIOS', 'orden' => 9, 'concepto' => 'Conos para grúas', 'unidad' => 'PZAS', 'p_unit_default' => 245, 'clave' => null, 'formula_cantidad' => null, 'formula_p_unit' => null],
    ['grupo' => 'VARIOS', 'orden' => 10, 'concepto' => 'Velador y seguridad', 'unidad' => 'MES', 'p_unit_default' => 15000, 'clave' => null, 'formula_cantidad' => 'meses', 'formula_p_unit' => null],
    ['grupo' => 'VARIOS', 'orden' => 11, 'concepto' => 'Renta de baños', 'unidad' => 'MES', 'p_unit_default' => 3500, 'clave' => null, 'formula_cantidad' => 'meses', 'formula_p_unit' => 'roundup(personas/20, 0) * 3500'],
    ['grupo' => 'VARIOS', 'orden' => 12, 'concepto' => 'Renta de andamios', 'unidad' => 'MES', 'p_unit_default' => 3000, 'clave' => null, 'formula_cantidad' => 'meses', 'formula_p_unit' => '3000 * grupos'],
    ['grupo' => 'VARIOS', 'orden' => 13, 'concepto' => 'Herramienta menor para montaje', 'unidad' => '%', 'p_unit_default' => 0, 'clave' => 'HERRAMIENTA', 'formula_cantidad' => '0.08', 'formula_p_unit' => 'mo_montaje'],
    ['grupo' => 'FLETES', 'orden' => 1, 'concepto' => 'Fletera de línea L=15.00 m', 'unidad' => 'lote', 'p_unit_default' => 12500, 'clave' => 'FLETERA_15', 'formula_cantidad' => null, 'formula_p_unit' => null],
    ['grupo' => 'FLETES', 'orden' => 2, 'concepto' => 'Polín y fleje para fletes (L=15)', 'unidad' => 'lote', 'p_unit_default' => 420, 'clave' => null, 'formula_cantidad' => 'cantidad_FLETERA_15', 'formula_p_unit' => null],
    ['grupo' => 'FLETES', 'orden' => 3, 'concepto' => 'Fletera de línea L=12.00 m', 'unidad' => 'lote', 'p_unit_default' => 8500, 'clave' => 'FLETERA_12', 'formula_cantidad' => 'camiones_ESTRUCTURA', 'formula_p_unit' => null],
    ['grupo' => 'FLETES', 'orden' => 4, 'concepto' => 'Polín y fleje para fletes (L=12)', 'unidad' => 'lote', 'p_unit_default' => 420, 'clave' => null, 'formula_cantidad' => 'cantidad_FLETERA_12', 'formula_p_unit' => null],
    ['grupo' => 'FLETES', 'orden' => 5, 'concepto' => 'Flete Steelex L=12 (tornillería) — 2% kg / 18 ton', 'unidad' => 'lote', 'p_unit_default' => 8500, 'clave' => null, 'formula_cantidad' => 'kg_obra * 0.02 / 18000', 'formula_p_unit' => 'p_unit_FLETERA_12'],
    ['grupo' => 'FLETES', 'orden' => 6, 'concepto' => 'Flete Steelex L=12 (herramientas) — 10% herr. menor', 'unidad' => 'lote', 'p_unit_default' => 8500, 'clave' => null, 'formula_cantidad' => 'importe_HERRAMIENTA * 0.1 / 8500', 'formula_p_unit' => null],
    ['grupo' => 'FLETES', 'orden' => 7, 'concepto' => 'Flete Steelex (remates)', 'unidad' => 'lote', 'p_unit_default' => 8500, 'clave' => null, 'formula_cantidad' => 'roundup(camiones_REMATES, 0)', 'formula_p_unit' => 'p_unit_FLETERA_12'],
    ['grupo' => 'GRUAS', 'orden' => 1, 'concepto' => 'Grúa externa de 30 tons.', 'unidad' => 'MES', 'p_unit_default' => 110000, 'clave' => null, 'formula_cantidad' => null, 'formula_p_unit' => null],
    ['grupo' => 'GRUAS', 'orden' => 2, 'concepto' => 'Grúa para montaje (1 grupos)', 'unidad' => 'MES', 'p_unit_default' => 170000, 'clave' => null, 'formula_cantidad' => 'roundup((semanas_fase_COLUMNAS_TRABES + semanas_fase_ARMADURAS)/4.2, 1)', 'formula_p_unit' => null],
    ['grupo' => 'GRUAS', 'orden' => 3, 'concepto' => 'Grúa para montaje polín (Steelex)', 'unidad' => 'MES', 'p_unit_default' => 170000, 'clave' => null, 'formula_cantidad' => 'roundup(semanas_fase_POLINERIA/4.2, 1)', 'formula_p_unit' => null],
    ['grupo' => 'GRUAS', 'orden' => 4, 'concepto' => 'Grúa para laminado', 'unidad' => 'MES', 'p_unit_default' => 170000, 'clave' => null, 'formula_cantidad' => 'roundup((dias_fase_LAMIN_CUBIERTA + dias_fase_LAMIN_MUROS)/20/2, 2)', 'formula_p_unit' => null],
    ['grupo' => 'GRUAS', 'orden' => 5, 'concepto' => 'Grúa-2 para montaje (descargas)', 'unidad' => 'DIAS', 'p_unit_default' => 6800, 'clave' => null, 'formula_cantidad' => null, 'formula_p_unit' => null],
    ['grupo' => 'PLATAFORMAS', 'orden' => 1, 'concepto' => 'Plataformas de elevación (1 por grupo)', 'unidad' => 'MES', 'p_unit_default' => 50000, 'clave' => null, 'formula_cantidad' => null, 'formula_p_unit' => '50000 * grupos'],
    ['grupo' => 'PLATAFORMAS', 'orden' => 2, 'concepto' => 'Renta de Gennie para ventiladores', 'unidad' => 'MES', 'p_unit_default' => 50000, 'clave' => null, 'formula_cantidad' => null, 'formula_p_unit' => null],
    ['grupo' => 'PLATAFORMAS', 'orden' => 3, 'concepto' => 'Renta de Gennie para louvers', 'unidad' => 'MES', 'p_unit_default' => 60000, 'clave' => null, 'formula_cantidad' => 'roundup(semanas_fase_LOUVERS/4.2, 1)', 'formula_p_unit' => null],
    ['grupo' => 'LABORATORIO', 'orden' => 1, 'concepto' => 'Pruebas de laboratorio (líquidos) — Est. Ppal', 'unidad' => 'PRUEBA', 'p_unit_default' => 350, 'clave' => null, 'formula_cantidad' => 'roundup((piezas_fase_COLUMNAS_TRABES + piezas_fase_ARMADURAS) * 0.2, 0)', 'formula_p_unit' => null],
    ['grupo' => 'LABORATORIO', 'orden' => 2, 'concepto' => 'Pruebas de laboratorio (líquidos) — Est. Sec.', 'unidad' => 'PRUEBA', 'p_unit_default' => 450, 'clave' => null, 'formula_cantidad' => null, 'formula_p_unit' => null],
    ['grupo' => 'LABORATORIO', 'orden' => 3, 'concepto' => 'Pruebas de laboratorio (líquidos) — TM Riel', 'unidad' => 'PRUEBA', 'p_unit_default' => 450, 'clave' => null, 'formula_cantidad' => null, 'formula_p_unit' => null],
    ['grupo' => 'TOPOGRAFIA', 'orden' => 1, 'concepto' => 'Topógrafo en obra-nave', 'unidad' => 'SEMANA', 'p_unit_default' => 18000, 'clave' => null, 'formula_cantidad' => 'roundup(semanas/2, 1) * 0.5', 'formula_p_unit' => null],
];
