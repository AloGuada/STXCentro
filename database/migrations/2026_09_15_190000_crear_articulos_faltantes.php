<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Todo lleva kardex (decisión 2026-09-15): todo item tiene su cara de Almacén.
 *
 * Los productos que Compras dio de alta y nunca pisaron una bodega —servicios,
 * gases, históricos— no tenían artículo, y por eso la recepción tenía que
 * preguntar si movía existencia. Aquí se les crea el artículo copiando la
 * identidad del item y los datos de bodega que el producto venía arrastrando.
 * En producción son ~300 renglones; ningún `DELETE` ni `UPDATE`.
 */
return new class extends Migration
{
    public function up(): void
    {
        $faltantes = DB::table('costos_productos as p')
            ->join('items as i', 'i.id', '=', 'p.item_id')
            ->whereNotExists(fn ($q) => $q->selectRaw('1')->from('alm_articulos as a')->whereColumn('a.item_id', 'p.item_id'))
            ->orderBy('p.id')
            ->get([
                'p.id as producto_id', 'p.item_id', 'i.codigo', 'i.descripcion', 'i.unidad',
                'p.codigo_barras', 'p.idsteelex', 'p.area_id', 'p.tipo', 'p.se_controla_por_pieza',
                'p.requiere_verificacion', 'p.stock_minimo', 'p.clasificacion_abc', 'p.imagen',
                'p.activo', 'p.creado_por',
            ]);

        $ahora = now();

        // El código del artículo es único. Si el del item ya lo ocupa otro
        // artículo (un sobrante de la fusión que conservó el suyo), la cara
        // nace sin código, igual que hizo la migración de `items`.
        $ocupados = DB::table('alm_articulos')->whereNotNull('codigo')->pluck('codigo')->flip();

        foreach ($faltantes->chunk(200) as $bloque) {
            DB::table('alm_articulos')->insert($bloque->map(fn ($p): array => [
                'item_id' => $p->item_id,
                'producto_id' => $p->producto_id,
                'codigo' => $p->codigo !== null && $ocupados->has($p->codigo) ? null : $p->codigo,
                'descripcion' => $p->descripcion,
                'unidad' => $p->unidad,
                'codigo_barras' => $p->codigo_barras ?? $p->codigo,
                'idsteelex' => $p->idsteelex,
                'area_id' => $p->area_id,
                'tipo' => $p->tipo,
                'se_controla_por_pieza' => $p->se_controla_por_pieza,
                'requiere_verificacion' => $p->requiere_verificacion,
                'stock_minimo' => $p->stock_minimo,
                'clasificacion_abc' => $p->clasificacion_abc,
                'imagen' => $p->imagen,
                'activo' => $p->activo,
                'creado_por' => $p->creado_por,
                'created_at' => $ahora,
                'updated_at' => $ahora,
            ])->all());
        }

        $sinArticulo = DB::table('costos_productos as p')
            ->whereNotExists(fn ($q) => $q->selectRaw('1')->from('alm_articulos as a')->whereColumn('a.item_id', 'p.item_id'))
            ->count();

        if ($sinArticulo > 0) {
            throw new RuntimeException("{$sinArticulo} productos siguen sin artículo después del relleno.");
        }
    }

    public function down(): void {}
};
