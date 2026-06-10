<?php

namespace Database\Seeders\Cotiz;

use App\Models\Cotiz\CategoriaTarjeta;
use App\Models\Cotiz\CentroCosto;
use App\Models\Cotiz\KilosRealesCategoria;
use App\Models\Cotiz\Merma;
use App\Models\Cotiz\PersonalCategoria;
use App\Models\Cotiz\PinturaFormula;
use App\Models\Cotiz\ResumenBloqueColor;
use App\Models\Cotiz\ResumenFila;
use App\Models\Cotiz\Unidad;
use Illuminate\Database\Seeder;

/**
 * Catálogos base del módulo Cotización (sin dependencias entre sí).
 * Idempotente: upsert por clave única. Preserva ids de unidades, centros de
 * costos y categorías de tarjeta porque insumos y factores los referencian.
 */
class CatalogosBaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->seedConId(Unidad::class, 'unidades', ['id', 'descripcion'], ['descripcion']);

        $this->seedConId(CentroCosto::class, 'centros_costos', ['id', 'cod_coste', 'concepto'], ['cod_coste', 'concepto']);

        $this->seedConId(CategoriaTarjeta::class, 'categorias_tarjeta', ['id', 'descripcion', 'orden'], ['descripcion', 'orden']);

        $this->upsertPorClave(Merma::class, 'mermas', 'descripcion', ['descripcion', 'formula']);

        $this->upsertPorClave(KilosRealesCategoria::class, 'kilos_reales_categorias', 'descripcion', ['descripcion', 'tipo_corte', 'orden']);

        // resumen_filas no tiene índice único en descripcion → updateOrCreate.
        $resumenFilas = require __DIR__.'/data/resumen_filas.php';
        foreach ($resumenFilas as $fila) {
            ResumenFila::query()->updateOrCreate(['descripcion' => $fila['descripcion']], $fila);
        }

        $this->upsertPorClave(PinturaFormula::class, 'pintura_formulas', 'clave', ['clave', 'nombre', 'formula', 'orden']);

        $this->upsertPorClave(ResumenBloqueColor::class, 'resumen_bloque_colores', 'bloque', ['bloque', 'color']);

        $this->upsertPorClave(PersonalCategoria::class, 'personal_categorias', 'codigo', ['codigo', 'nombre', 'sueldo_semanal', 'orden']);
    }

    /**
     * Upsert preservando el id explícito de las filas del archivo de datos.
     *
     * @param  class-string<\Illuminate\Database\Eloquent\Model>  $model
     * @param  list<string>  $columnas
     * @param  list<string>  $actualizables
     */
    private function seedConId(string $model, string $archivo, array $columnas, array $actualizables): void
    {
        $filas = require __DIR__."/data/{$archivo}.php";
        $filas = $this->soloColumnas($filas, $columnas);

        $model::query()->upsert($filas, ['id'], $actualizables);
    }

    /**
     * Upsert por una clave única de negocio (sin id explícito).
     *
     * @param  class-string<\Illuminate\Database\Eloquent\Model>  $model
     * @param  list<string>  $columnas
     */
    private function upsertPorClave(string $model, string $archivo, string $clave, array $columnas): void
    {
        $filas = require __DIR__."/data/{$archivo}.php";
        $filas = $this->soloColumnas($filas, $columnas);

        $model::query()->upsert($filas, [$clave], $columnas);
    }

    /**
     * @param  list<array<string, mixed>>  $filas
     * @param  list<string>  $columnas
     * @return list<array<string, mixed>>
     */
    private function soloColumnas(array $filas, array $columnas): array
    {
        return array_map(
            fn (array $fila): array => array_intersect_key($fila, array_flip($columnas)),
            $filas,
        );
    }
}
