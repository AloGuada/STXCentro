<?php

namespace Database\Seeders\Cotiz;

use App\Models\Cotiz\Factor;
use App\Models\Cotiz\Insumo;
use Illuminate\Database\Seeder;
use RuntimeException;

/**
 * Factores predefinidos (19). insumo_id se resuelve por descripción contra los
 * insumos ya sembrados (no se usan ids crudos de prepsim).
 * Idempotente: upsert por codigo. Requiere que InsumoSeeder haya corrido antes.
 */
class FactorSeeder extends Seeder
{
    public function run(): void
    {
        $filas = require __DIR__.'/data/factores.php';

        $insumoIds = Insumo::query()->pluck('id', 'descripcion');

        $registros = array_map(function (array $fila) use ($insumoIds): array {
            $descripcion = $fila['insumo_descripcion'];

            if (! isset($insumoIds[$descripcion])) {
                throw new RuntimeException("Factor {$fila['codigo']}: insumo '{$descripcion}' no encontrado. Corre InsumoSeeder primero.");
            }

            return [
                'codigo' => $fila['codigo'],
                'nombre' => $fila['nombre'],
                'insumo_id' => $insumoIds[$descripcion],
                'formula' => $fila['formula'],
                'descripcion' => $fila['descripcion'],
                'categoria_tarjeta_id' => $fila['categoria_tarjeta_id'],
            ];
        }, $filas);

        Factor::query()->upsert(
            $registros,
            ['codigo'],
            ['nombre', 'insumo_id', 'formula', 'descripcion', 'categoria_tarjeta_id'],
        );
    }
}
