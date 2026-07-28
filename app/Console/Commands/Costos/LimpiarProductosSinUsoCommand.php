<?php

namespace App\Console\Commands\Costos;

use App\Models\Costos\Producto;
use Carbon\CarbonInterface;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Collection;

/**
 * Limpia productos del catálogo que quedaron "sin uso": nunca se les cotizó un
 * precio (sin filas en costos_producto_precios), nunca se compraron (sin
 * referencias en costos_ordenes_compra_detalle) y llevan más de N días creados.
 *
 * Esto absorbe los duplicados que deja capturar el mismo insumo nuevo en varias
 * partidas de una requisición: las copias que nunca se cotizaron ni compraron se
 * borran. Las partidas de requisición que las referenciaban quedan con
 * producto_id = null por la FK (nullOnDelete), pero conservan su descripción,
 * código y unidad propias, así que la requisición no pierde información.
 *
 * Por defecto corre en dry-run (solo reporta). Requiere --force para borrar.
 */
class LimpiarProductosSinUsoCommand extends Command
{
    protected $signature = 'costos:limpiar-productos
        {--dias=7 : Antigüedad mínima en días desde la creación}
        {--force : Ejecuta el borrado; sin esta bandera solo muestra el dry-run}';

    protected $description = 'Borra productos del catálogo sin precio, no comprados y con más de N días de creados (limpia duplicados)';

    /** Antigüedad mínima por defecto (días). */
    public const DIAS = 7;

    public function handle(): int
    {
        $dias = (int) $this->option('dias') ?: self::DIAS;
        $limite = now()->subDays($dias);

        $productos = $this->productosSinUso($limite);

        if ($productos->isEmpty()) {
            $this->info("No hay productos sin uso con más de {$dias} días de creados.");

            return self::SUCCESS;
        }

        $this->table(
            ['id', 'Descripción', 'Creado', 'Partidas req. que lo referencian'],
            $productos->map(fn (Producto $p): array => [
                $p->id,
                $p->descripcion,
                $p->created_at?->toDateString() ?? '-',
                (int) $p->requisicion_detalles_count,
            ])->all(),
        );

        $total = $productos->count();

        if (! $this->option('force')) {
            $this->warn("DRY-RUN: no se borró nada. Se eliminarían {$total} producto(s) sin uso.");
            $this->line('Vuelve a ejecutar con --force para borrar definitivamente.');

            return self::SUCCESS;
        }

        Producto::whereIn('id', $productos->pluck('id'))->delete();

        $this->info("Eliminados {$total} producto(s) sin uso.");

        return self::SUCCESS;
    }

    /**
     * Productos sin precio, no comprados y más viejos que $limite.
     *
     * @return Collection<int, Producto>
     */
    private function productosSinUso(CarbonInterface $limite): Collection
    {
        return Producto::query()
            ->where('created_at', '<=', $limite)
            ->whereDoesntHave('precios')
            ->whereNotIn('id', function ($q): void {
                $q->select('producto_id')
                    ->from('costos_ordenes_compra_detalle')
                    ->whereNotNull('producto_id');
            })
            ->withCount('requisicionDetalles')
            ->orderBy('id')
            ->get();
    }
}
