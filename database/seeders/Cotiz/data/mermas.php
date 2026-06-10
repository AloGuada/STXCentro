<?php

/**
 * Mermas (formulas de kg con merma).
 *
 * Generado por database/seeders/Cotiz/_generar.php (porta prepsim).
 * NO editar a mano.
 */
return [
    ['descripcion' => 'Sin merma', 'formula' => 'kilos_reales'],
    ['descripcion' => 'Simple 3%', 'formula' => 'kilos_reales * 1.03'],
    ['descripcion' => 'Placa cortada en tiras 6x20', 'formula' => 'ceil((t_ml_m2 / ancho * 1.03) / (floor(1.83 / ancho) * 6.084) * 100) / 100 * peso_default'],
];
