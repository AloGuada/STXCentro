<?php

/**
 * Cuadrillas (MO). centro_costo_id resuelto. insumo_id = null (prepsim no lo siembra).
 *
 * Generado por database/seeders/Cotiz/_generar.php (porta prepsim).
 * NO editar a mano.
 */
return [
    ['codigo' => 'HABILITADO', 'nombre' => 'Habilitado', 'centro_costo_id' => 12, 'rendimiento' => 0.211, 'formula_costo' => '1.8 * 1.4 * 1.25 * 1.2 * 1.3 * 0.75', 'descripcion' => 'base 1.8 $/hr × FSR 1.4 × IND 1.25 × PREST 1.2 × UTIL 1.3 × efic 0.75'],
    ['codigo' => 'ARMADO_SOLDADURA', 'nombre' => 'Armado y Soldadura', 'centro_costo_id' => 13, 'rendimiento' => 0.732, 'formula_costo' => '2.3 * 1.4 * 1.25 * 1.2 * 1.3 * 0.60', 'descripcion' => 'base 2.3 $/hr × FSR 1.4 × IND 1.25 × PREST 1.2 × UTIL 1.3 × efic 0.60'],
    ['codigo' => 'SUPV_FAB', 'nombre' => 'Supervisión Fabricación', 'centro_costo_id' => 15, 'rendimiento' => 0.056, 'formula_costo' => '1.2 * 1.4 * 1.25 * 1.2 * 1.3 * 1.0', 'descripcion' => 'base 1.2 $/hr × FSR 1.4 × IND 1.25 × PREST 1.2 × UTIL 1.3 × efic 1.0'],
    ['codigo' => 'MONTAJE', 'nombre' => 'M.O. Contratista Montaje', 'centro_costo_id' => 17, 'rendimiento' => null, 'formula_costo' => null, 'descripcion' => 'Captura manual $/kg en tarjeta — pendiente formular'],
    ['codigo' => 'SUPV_MONTAJE', 'nombre' => 'Supervisión Montaje', 'centro_costo_id' => 22, 'rendimiento' => null, 'formula_costo' => null, 'descripcion' => 'Captura manual $/kg en tarjeta — pendiente formular'],
];
