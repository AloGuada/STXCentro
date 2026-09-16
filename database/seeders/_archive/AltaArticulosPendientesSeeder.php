<?php

namespace Database\Seeders;

use App\Enums\Alm\ProductoTipo;
use App\Models\Alm\Articulo;
use App\Models\Costos\Producto;
use App\Services\Alm\GeneradorCodigoArticulo;
use App\Services\Catalogo\CatalogoMaestro;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Alta de una sola corrida (2026-09-16) de los materiales que se compraron
 * tecleados antes de que la requisición exigiera producto, y que por eso sus
 * recepciones no llegaron al kardex. Va por el mismo camino que Almacén >
 * Artículos: item con código nuevo, producto y artículo en una transacción.
 * Idempotente: lo que ya exista con ese nombre se reutiliza.
 *
 * Después de correrlo: `costos:asignar-producto-a-partidas --force` y
 * `alm:reponer-entradas --force`. Corrido en producción el 2026-09-16.
 */
class AltaArticulosPendientesSeeder extends Seeder
{
    /**
     * @var list<array{0: string, 1: string}> descripción, unidad
     */
    private const ARTICULOS = [
        ['MINI FELPAS', 'PZA'],
        ['DISCO DE CORTE DE 7" DE 1/16', 'PZA'],
        ['DISCO DE CORTE DE 4 1/2 DE 1/16" ULTRA FINO', 'PZA'],
        ['ESTOPA DE 1 KG', 'PZA'],
        ['MARCADOR AMARILLO TIPO CRAYON PARA PLASMA (INFRA)', 'PZA'],
        ['DIFUSORES', 'PZA'],
        ['GEL ANTIESCORIA', 'PZA'],
        ['CASCOS AZULES', 'PZA'],
        ['CHALECOS TIPO BRIGADISTA AZUL REY', 'PZA'],
        ['TRAPO', 'KG'],
        ['POLIN DE MADERA DE 3X3X1.25 MTS', 'PZA'],
    ];

    public function run(CatalogoMaestro $maestro, GeneradorCodigoArticulo $generador): void
    {
        foreach (self::ARTICULOS as [$descripcion, $unidad]) {
            DB::transaction(function () use ($maestro, $generador, $descripcion, $unidad): void {
                $item = $maestro->buscarOCrear($descripcion, $unidad);

                if ($item->codigo === null) {
                    $item->update(['codigo' => $generador->siguiente()]);
                }

                $producto = $item->producto()->first() ?? Producto::create([
                    'item_id' => $item->id,
                    'codigo' => $item->codigo,
                    'descripcion' => $item->descripcion,
                    'unidad' => $item->unidad,
                    'activo' => true,
                ]);

                if ($item->articulo()->exists()) {
                    $this->command?->line("  ya existía: {$item->codigo} {$item->descripcion}");

                    return;
                }

                Articulo::create([
                    'item_id' => $item->id,
                    'producto_id' => $producto->id,
                    'codigo' => $item->codigo,
                    'codigo_barras' => $item->codigo,
                    'descripcion' => $item->descripcion,
                    'unidad' => $item->unidad,
                    'tipo' => ProductoTipo::Insumo,
                    'se_controla_por_pieza' => false,
                    'requiere_verificacion' => false,
                    'activo' => true,
                ]);

                $this->command?->line("  alta: {$item->codigo} {$item->descripcion}");
            });
        }
    }
}
