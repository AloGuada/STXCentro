<?php

/**
 * Formulas de pintura (5 claves).
 *
 * Generado por database/seeders/Cotiz/_generar.php (porta prepsim).
 * NO editar a mano.
 */
return [
    ['clave' => 'no_pinta', 'nombre' => 'No pinta', 'formula' => '0', 'orden' => 10],
    ['clave' => 'placa', 'nombre' => 'Placa (×2)', 'formula' => 'kg * 2 / peso_lineal', 'orden' => 20],
    ['clave' => 'tira', 'nombre' => 'Tira / 4PLS (×1)', 'formula' => 'kg / peso_lineal', 'orden' => 30],
    ['clave' => 'hss', 'nombre' => 'HSS / APS (4·lado)', 'formula' => 'lado * 0.0254 * 4 * kg / peso_lineal', 'orden' => 40],
    ['clave' => 'ipr', 'nombre' => 'IPR / W (peralte+patín)', 'formula' => '(peralte * 2 + patin * 4) * kg / peso_lineal', 'orden' => 50],
];
