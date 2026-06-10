<?php

/**
 * Filas del Resumen de proyecto (coeficientes default del Excel).
 *
 * Generado por database/seeders/Cotiz/_generar.php (porta prepsim).
 * NO editar a mano.
 */
return [
    ['descripcion' => 'MATERIALES', 'bloque' => 'MO_FAB', 'tipo_formula' => 'materiales', 'coef_default' => null, 'referencia_extra' => null, 'orden' => 10],
    ['descripcion' => 'HABILITADO', 'bloque' => 'MO_FAB', 'tipo_formula' => 'mo_fab_subgrupo', 'coef_default' => 0.2113, 'referencia_extra' => null, 'orden' => 20],
    ['descripcion' => 'ARMADO Y SOLDADO', 'bloque' => 'MO_FAB', 'tipo_formula' => 'mo_fab_subgrupo', 'coef_default' => 0.7324, 'referencia_extra' => null, 'orden' => 30],
    ['descripcion' => 'SUPERVISION DE FAB.', 'bloque' => 'MO_FAB', 'tipo_formula' => 'mo_fab_subgrupo', 'coef_default' => 0.0563, 'referencia_extra' => null, 'orden' => 40],
    ['descripcion' => 'PINTURA', 'bloque' => 'MO_FAB', 'tipo_formula' => 'por_m2_pintura', 'coef_default' => 15, 'referencia_extra' => null, 'orden' => 50],
    ['descripcion' => 'ING. DETALLE', 'bloque' => 'MO_FAB', 'tipo_formula' => 'por_kg', 'coef_default' => 0.8, 'referencia_extra' => null, 'orden' => 60],
    ['descripcion' => 'CIMENTACION', 'bloque' => 'MO_FAB', 'tipo_formula' => 'por_kg', 'coef_default' => 0, 'referencia_extra' => null, 'orden' => 70],
    ['descripcion' => 'MANTENIMIENTO', 'bloque' => 'MO_FAB', 'tipo_formula' => 'por_kg', 'coef_default' => 0.17, 'referencia_extra' => null, 'orden' => 80],
    ['descripcion' => 'MAQUILADO', 'bloque' => 'MO_FAB', 'tipo_formula' => 'por_kg', 'coef_default' => 0.04, 'referencia_extra' => null, 'orden' => 90],
    ['descripcion' => 'ALMACEN', 'bloque' => 'MO_FAB', 'tipo_formula' => 'por_kg', 'coef_default' => 0.05, 'referencia_extra' => null, 'orden' => 100],
    ['descripcion' => 'LOGISTICA', 'bloque' => 'MO_FAB', 'tipo_formula' => 'por_kg', 'coef_default' => 0, 'referencia_extra' => null, 'orden' => 110],
    ['descripcion' => 'CALIDAD', 'bloque' => 'MO_FAB', 'tipo_formula' => 'por_kg', 'coef_default' => 0.15, 'referencia_extra' => null, 'orden' => 120],
    ['descripcion' => 'IMPERMEABILIZACION KIWI', 'bloque' => 'MO_FAB', 'tipo_formula' => 'por_kg', 'coef_default' => 0, 'referencia_extra' => null, 'orden' => 130],
    ['descripcion' => 'M.O. MONTAJE', 'bloque' => 'MO_MONTAJE', 'tipo_formula' => 'por_m2_pintura', 'coef_default' => 2, 'referencia_extra' => null, 'orden' => 200],
    ['descripcion' => 'M.O. PINTURA EN OBRA', 'bloque' => 'MO_MONTAJE', 'tipo_formula' => 'por_m2_pintura', 'coef_default' => 3, 'referencia_extra' => null, 'orden' => 210],
    ['descripcion' => 'M.O. DESCARGAS', 'bloque' => 'MO_MONTAJE', 'tipo_formula' => 'por_kg', 'coef_default' => 0.13, 'referencia_extra' => null, 'orden' => 220],
    ['descripcion' => 'M.O. SUPERV. DE MONTAJE', 'bloque' => 'MO_MONTAJE', 'tipo_formula' => 'por_kg', 'coef_default' => 0.21, 'referencia_extra' => null, 'orden' => 230],
    ['descripcion' => 'M.O. GASTOS ADMON', 'bloque' => 'MO_MONTAJE', 'tipo_formula' => 'por_kg', 'coef_default' => 0.31, 'referencia_extra' => null, 'orden' => 240],
    ['descripcion' => 'M.O. GASTOS DE VENTA', 'bloque' => 'MO_MONTAJE', 'tipo_formula' => 'por_kg', 'coef_default' => 0.39, 'referencia_extra' => null, 'orden' => 250],
    ['descripcion' => 'M.O. IMPUESTOS', 'bloque' => 'MO_MONTAJE', 'tipo_formula' => 'por_kg', 'coef_default' => 0.87, 'referencia_extra' => null, 'orden' => 260],
    ['descripcion' => 'GASTOS', 'bloque' => 'MO_MONTAJE', 'tipo_formula' => 'por_kg', 'coef_default' => 0.88, 'referencia_extra' => null, 'orden' => 270],
    ['descripcion' => 'CONSUMIBLES DE PLANTA', 'bloque' => 'MO_MONTAJE', 'tipo_formula' => 'por_kg', 'coef_default' => 0.37, 'referencia_extra' => null, 'orden' => 280],
    ['descripcion' => 'GRUAS MANIOBRAS', 'bloque' => 'MO_MONTAJE', 'tipo_formula' => 'por_kg', 'coef_default' => 0.34, 'referencia_extra' => null, 'orden' => 290],
    ['descripcion' => 'SUBTOTAL', 'bloque' => 'MO_MONTAJE', 'tipo_formula' => 'subtotal', 'coef_default' => null, 'referencia_extra' => null, 'orden' => 299],
    ['descripcion' => 'FLETE', 'bloque' => 'EXTRAS', 'tipo_formula' => 'flete_kg_prorrateado', 'coef_default' => 1, 'referencia_extra' => 'EXPL_MO_F54', 'orden' => 310],
    ['descripcion' => 'VIATICOS', 'bloque' => 'EXTRAS', 'tipo_formula' => 'viatico_m2_prorrateado', 'coef_default' => 1, 'referencia_extra' => 'EXPL_MO_F19', 'orden' => 320],
    ['descripcion' => 'GRUAS', 'bloque' => 'EXTRAS', 'tipo_formula' => 'flete_kg_prorrateado', 'coef_default' => 1, 'referencia_extra' => 'EXPL_MO_F63', 'orden' => 330],
    ['descripcion' => 'LABORATORIO', 'bloque' => 'EXTRAS', 'tipo_formula' => 'flete_kg_prorrateado', 'coef_default' => 1, 'referencia_extra' => 'EXPL_MO_F75', 'orden' => 340],
    ['descripcion' => 'TOPOGRAFIA', 'bloque' => 'EXTRAS', 'tipo_formula' => 'flete_kg_prorrateado', 'coef_default' => 1, 'referencia_extra' => 'EXPL_MO_F80', 'orden' => 350],
    ['descripcion' => 'VARIOS', 'bloque' => 'EXTRAS', 'tipo_formula' => 'flete_kg_prorrateado', 'coef_default' => 1, 'referencia_extra' => 'EXPL_MO_F37', 'orden' => 360],
    ['descripcion' => 'ENERGIA PARA MONTAJE', 'bloque' => 'EXTRAS', 'tipo_formula' => 'flete_kg_prorrateado', 'coef_default' => 1, 'referencia_extra' => 'EXPL_MO_F33', 'orden' => 370],
    ['descripcion' => 'FIANZAS', 'bloque' => 'EXTRAS', 'tipo_formula' => 'por_kg', 'coef_default' => 0.44, 'referencia_extra' => null, 'orden' => 380],
    ['descripcion' => 'COSTO DIRECTO', 'bloque' => 'TOTALES', 'tipo_formula' => 'subtotal', 'coef_default' => null, 'referencia_extra' => null, 'orden' => 400],
    ['descripcion' => 'C. MARGINAL', 'bloque' => 'TOTALES', 'tipo_formula' => 'margen', 'coef_default' => 0.2048, 'referencia_extra' => null, 'orden' => 410],
    ['descripcion' => 'TOTAL', 'bloque' => 'TOTALES', 'tipo_formula' => 'total', 'coef_default' => null, 'referencia_extra' => null, 'orden' => 420],
];
