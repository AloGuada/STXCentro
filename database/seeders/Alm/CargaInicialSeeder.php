<?php

namespace Database\Seeders\Alm;

use App\Enums\Alm\AjusteMotivo;
use App\Enums\Alm\ProductoTipo;
use App\Models\Alm\Ajuste;
use App\Models\Alm\Almacen;
use App\Models\Alm\Area;
use App\Models\Costos\Producto;
use App\Models\Usuario;
use App\Services\Alm\GeneradorCodigoArticulo;
use App\Services\Alm\RegistradorAjuste;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Carga inicial de un almacén: el inventario con el que arrancó, tal como lo
 * entregó el área.
 *
 * Los renglones viven en cada subclase, no en un archivo aparte: así el repo
 * dice con qué números se abrió cada almacén y un diff enseña si alguien los
 * movió. Vienen ya cuadrados —costo por unidad y no por envase— porque
 * cuadrarlos fue trabajo de una vez, contra el layout que mandó el área.
 *
 * El área entra en null a propósito: el catálogo de áreas se capturó como el
 * área de quien recibe el insumo, no como la familia a la que pertenece, así
 * que ponerle "Pintura" a los 39 renglones de PIN sólo repetiría el almacén.
 * Se clasifica desde la UI cuando existan las familias reales; el área no
 * gobierna ningún flujo, sólo filtra el listado de artículos.
 *
 * El saldo entra por `RegistradorAjuste` con motivo `carga_inicial`: un ajuste
 * con folio y de ahí al kardex por el ledger. Nadie escribe la existencia a
 * mano, ni siquiera un seeder.
 */
abstract class CargaInicialSeeder extends Seeder
{
    /** Clave del almacén central que se está abriendo. */
    abstract protected function almacen(): string;

    /**
     * Un renglón por artículo. `cantidad` en cero da de alta el artículo y le
     * abre la existencia sin movimiento: contar cero también es información.
     *
     * @return list<array{descripcion: string, unidad: string, area: string|null, abc: string, stock_minimo: float|null, cantidad: float, costo: float|null, nota: string|null}>
     */
    abstract protected function articulos(): array;

    public function run(): void
    {
        $almacen = Almacen::query()
            ->where('clave', $this->almacen())
            ->whereNull('obra_id')
            ->first();

        if ($almacen === null) {
            $this->command?->error("No existe el almacén central {$this->almacen()}: dalo de alta antes de cargarle saldo.");

            return;
        }

        // Correrlo dos veces duplicaría el catálogo y duplicaría el saldo: son
        // artículos con código nuevo y un ajuste con folio nuevo, no un
        // `firstOrCreate`. Un ajuste de inventario es un hecho fechado, no un
        // estado al que se pueda converger.
        $abierto = Ajuste::query()
            ->where('almacen_id', $almacen->id)
            ->where('motivo', AjusteMotivo::CargaInicial->value)
            ->first();

        if ($abierto !== null) {
            $this->command?->warn("{$this->almacen()} ya tiene carga inicial ({$abierto->folio}); no se vuelve a cargar.");

            return;
        }

        $autoriza = Usuario::query()->role('super-admin')->orderBy('created_at')->first();

        if ($autoriza === null) {
            $this->command?->error('No hay ningún super-admin que pueda autorizar el ajuste de carga inicial.');

            return;
        }

        $folio = $this->cargar($almacen, $autoriza);

        $this->command?->info(sprintf(
            '%s: %d artículos, $%s (ajuste %s).',
            $this->almacen(),
            count($this->articulos()),
            number_format($this->valuacion(), 2),
            $folio,
        ));
    }

    private function cargar(Almacen $almacen, Usuario $autoriza): ?string
    {
        return DB::transaction(function () use ($almacen, $autoriza): ?string {
            $areas = Area::query()->pluck('id', 'descripcion');
            $generador = app(GeneradorCodigoArticulo::class);
            $renglones = [];

            foreach ($this->articulos() as $articulo) {
                if ($articulo['area'] !== null && ! $areas->has($articulo['area'])) {
                    throw new RuntimeException("El área \"{$articulo['area']}\" no está en el catálogo; corre primero el seeder de áreas.");
                }

                $codigo = $generador->siguiente();

                $producto = Producto::create([
                    'codigo' => $codigo,
                    // La etiqueta que se imprime es la nuestra: estos artículos
                    // nacen sin código de barras de fábrica.
                    'codigo_barras' => $codigo,
                    'descripcion' => $articulo['descripcion'],
                    'unidad' => $articulo['unidad'],
                    'area_id' => $articulo['area'] === null ? null : $areas[$articulo['area']],
                    'clasificacion_abc' => $articulo['abc'],
                    'stock_minimo' => $articulo['stock_minimo'],
                    'tipo' => ProductoTipo::Insumo,
                    'controla_inventario' => true,
                    'se_controla_por_pieza' => false,
                    'requiere_verificacion' => false,
                    'activo' => true,
                    'creado_por' => $autoriza->getKey(),
                ]);

                $renglones[] = [
                    'producto_id' => $producto->id,
                    'cantidad_contada' => $articulo['cantidad'],
                    'costo_unitario' => $articulo['costo'],
                    'observaciones' => $articulo['nota'],
                ];
            }

            return app(RegistradorAjuste::class)->registrar(
                cabecera: [
                    'almacen_id' => $almacen->id,
                    'motivo' => AjusteMotivo::CargaInicial->value,
                    'fecha' => now()->toDateString(),
                    'observaciones' => 'Carga inicial del almacén.',
                    'autorizado_por' => $autoriza->getKey(),
                ],
                renglones: $renglones,
                userId: $autoriza->getKey(),
            )->folio;
        });
    }

    private function valuacion(): float
    {
        return array_sum(array_map(
            fn (array $articulo): float => $articulo['cantidad'] * ($articulo['costo'] ?? 0),
            $this->articulos(),
        ));
    }
}
