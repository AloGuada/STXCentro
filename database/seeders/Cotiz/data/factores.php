<?php

/**
 * Factores. insumo_id se resuelve en el seeder por insumo_descripcion (lookup al insumo sembrado).
 *
 * Generado por database/seeders/Cotiz/_generar.php (porta prepsim).
 * NO editar a mano.
 */
return [
    ['codigo' => 'OXIGENO_PLANTA', 'nombre' => 'Oxígeno cilindro Planta', 'insumo_descripcion' => 'Oxigeno cilindro de 9 m3 (Planta)', 'formula' => 'total.tarjeta.kg * 0.000428 * 1.25 * 0.8', 'descripcion' => 'Cilindros O2 = kg fabricados × 0.000428 × ajuste × ratio_planta (0.8)', 'categoria_tarjeta_id' => 2],
    ['codigo' => 'BUTANO_PLANTA', 'nombre' => 'Butano Planta', 'insumo_descripcion' => 'Butano (Planta)', 'formula' => 'tarjeta.factor[cod=OXIGENO_PLANTA] * 0.2', 'descripcion' => 'Butano = 20% del oxígeno', 'categoria_tarjeta_id' => 2],
    ['codigo' => 'SOLDADURA_MICRO', 'nombre' => 'Soldadura microalambre Planta', 'insumo_descripcion' => 'Soldadura microlalambre .035 "', 'formula' => 'total.tarjeta.kg * 0.02 * 0.8', 'descripcion' => 'Kg microalambre por kg fabricado × ratio_planta', 'categoria_tarjeta_id' => 2],
    ['codigo' => 'GAS_MEZCLA', 'nombre' => 'Gas mezcla', 'insumo_descripcion' => 'Gas mezcla ( Cil 10.5 M³)', 'formula' => 'tarjeta.factor[cod=SOLDADURA_MICRO] * 0.48 / 10.5 * 1.25 * 1.75', 'descripcion' => 'Cilindros gas mezcla por kg de soldadura', 'categoria_tarjeta_id' => 2],
    ['codigo' => 'PLASMA_CXS', 'nombre' => 'Consumibles Plasma CXS', 'insumo_descripcion' => 'Consumibles de Corte-Plasma CXS', 'formula' => 'total.tarjeta.kg_real[corte=cnx]', 'descripcion' => 'kg con tipo_corte CNX del Análisis de kilos reales. Importe = kg × precio_unitario del insumo asociado.', 'categoria_tarjeta_id' => 2],
    ['codigo' => 'PLASMA_TIRAS', 'nombre' => 'Consumibles Plasma TIRAS', 'insumo_descripcion' => 'Consumibles de Corte-Plasma TIRAS', 'formula' => 'total.tarjeta.kg_real[corte=tiras]', 'descripcion' => 'kg con tipo_corte TIRAS del Análisis de kilos reales. Importe = kg × precio_unitario del insumo asociado.', 'categoria_tarjeta_id' => 2],
    ['codigo' => 'PLASMA_RAZ', 'nombre' => 'Perfiles RAZ-ROBOT', 'insumo_descripcion' => 'Consumibles de Corte-Perfiles RAZ-ROBOT', 'formula' => 'total.tarjeta.kg_real[corte=raz]', 'descripcion' => 'kg con tipo_corte RAZ del Análisis de kilos reales. Importe = kg × precio_unitario del insumo asociado.', 'categoria_tarjeta_id' => 2],
    ['codigo' => 'OXIGENO_OBRA', 'nombre' => 'Oxígeno cilindro Obra', 'insumo_descripcion' => 'Oxigeno cilindro de 9 m3 (Obra)', 'formula' => 'total.tarjeta.kg * 0.000428 * 1.25 * 0.2', 'descripcion' => 'Cilindros O2 obra = kg fabricados × 0.000428 × ajuste × ratio_obra (0.2)', 'categoria_tarjeta_id' => 3],
    ['codigo' => 'BUTANO_OBRA', 'nombre' => 'Butano Obra', 'insumo_descripcion' => 'Butano (Obra)', 'formula' => 'tarjeta.factor[cod=OXIGENO_OBRA] * 0.2', 'descripcion' => null, 'categoria_tarjeta_id' => 3],
    ['codigo' => 'SOLDADURA_E7018', 'nombre' => 'Soldadura E7018', 'insumo_descripcion' => 'Soldadura E7018', 'formula' => 'tarjeta.factor[cod=SOLDADURA_MICRO] * 0.15', 'descripcion' => 'E7018 = 15% del microalambre', 'categoria_tarjeta_id' => 3],
    ['codigo' => 'SOLDADURA_E6013', 'nombre' => 'Soldadura E6013', 'insumo_descripcion' => 'Soldadura E6013', 'formula' => '0', 'descripcion' => 'Deshabilitado por defecto (×0)', 'categoria_tarjeta_id' => 3],
    ['codigo' => 'PINTURA_GRIS_PLANTA', 'nombre' => 'Pintura primario gris Planta', 'insumo_descripcion' => 'Pintura anticorrosiva (primario color gris) Planta', 'formula' => 'total.tarjeta.area / 3.46 * 1.5', 'descripcion' => 'Rendimiento 3.46 m²/L × 1.5 manos', 'categoria_tarjeta_id' => 4],
    ['codigo' => 'THINER_PLANTA', 'nombre' => 'Thiner Planta', 'insumo_descripcion' => 'Thiner (Planta)', 'formula' => 'tarjeta.factor[cod=PINTURA_GRIS_PLANTA] * 0.25', 'descripcion' => 'Thiner = 25% de la pintura', 'categoria_tarjeta_id' => 4],
    ['codigo' => 'PINTURA_GRIS_OBRA', 'nombre' => 'Pintura primario gris Obra', 'insumo_descripcion' => 'Pintura anticorrosiva (primario color gris) Obra', 'formula' => 'ceil(tarjeta.factor[cod=PINTURA_GRIS_PLANTA] * 100) / 100 * 0.2', 'descripcion' => 'Touch-up obra = 20% del de planta (con redondeo a 2 decimales)', 'categoria_tarjeta_id' => 4],
    ['codigo' => 'THINER_OBRA', 'nombre' => 'Thiner Obra', 'insumo_descripcion' => 'Thiner (Obra)', 'formula' => 'tarjeta.factor[cod=PINTURA_GRIS_OBRA] * 0.25', 'descripcion' => null, 'categoria_tarjeta_id' => 4],
    ['codigo' => 'DRY_FOG_PLANTA', 'nombre' => 'Pintura Dry Fog Planta', 'insumo_descripcion' => 'Pintura Dry Fog ( color Blanco) Planta', 'formula' => '0', 'descripcion' => 'Deshabilitado por defecto', 'categoria_tarjeta_id' => 4],
    ['codigo' => 'SOLVENTE_PLANTA', 'nombre' => 'Solvente base agua Planta', 'insumo_descripcion' => 'Solvente base agua (Planta)', 'formula' => 'tarjeta.factor[cod=DRY_FOG_PLANTA] * 0.5', 'descripcion' => null, 'categoria_tarjeta_id' => 4],
    ['codigo' => 'DRY_FOG_OBRA', 'nombre' => 'Pintura Dry Fog Obra', 'insumo_descripcion' => 'Pintura Dry Fog ( color Blanco) Obra', 'formula' => 'ceil(tarjeta.factor[cod=DRY_FOG_PLANTA] * 100) / 100 * 0.2', 'descripcion' => null, 'categoria_tarjeta_id' => 4],
    ['codigo' => 'SOLVENTE_OBRA', 'nombre' => 'Solvente base agua Obra', 'insumo_descripcion' => 'Solvente base agua (Obra)', 'formula' => 'tarjeta.factor[cod=DRY_FOG_OBRA] * 0.5', 'descripcion' => null, 'categoria_tarjeta_id' => 4],
];
