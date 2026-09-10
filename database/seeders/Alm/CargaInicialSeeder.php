<?php

namespace Database\Seeders\Alm;

use App\Enums\Alm\AjusteMotivo;
use App\Enums\Alm\ProductoTipo;
use App\Enums\Alm\UbicacionTipo;
use App\Models\Alm\Ajuste;
use App\Models\Alm\Almacen;
use App\Models\Alm\Area;
use App\Models\Alm\Articulo;
use App\Models\Alm\Existencia;
use App\Models\Alm\Ubicacion;
use App\Models\Usuario;
use App\Services\Alm\GeneradorCodigoArticulo;
use App\Services\Alm\RegistradorAjuste;
use App\Services\Catalogo\CatalogoMaestro;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Carga inicial de un almacén: el inventario con el que arrancó, tal como lo
 * entregó el área.
 *
 * **Entra por el catálogo maestro y no toca `costos_productos`.** Cada renglón
 * se busca por descripción en `items`: si el insumo ya existe —porque Compras
 * lo compra o porque otro almacén ya lo cargó— se reutiliza su identidad y su
 * código, y si ya tiene artículo se reutiliza el artículo y sólo entra el
 * saldo. Si no existe, nace un item y un artículo sin producto, que Compras
 * encontrará el día que lo compre.
 *
 * Eso es lo que hace que abrir un almacén no pueda lastimar a Compras ni
 * duplicar el catálogo. Antes cada almacén estrenaba un artículo por renglón
 * —seis "MINI ESMERIL" para seis almacenes— y emparejarlos era trabajo manual
 * que nadie hacía.
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
    /** Clave del almacén que se está abriendo. */
    abstract protected function almacen(): string;

    /**
     * Número de obra cuando el almacén es de obra. Null —lo normal— es el
     * almacén central, que es lo que en la pantalla se marca como "está en la
     * planta".
     */
    protected function obra(): ?string
    {
        return null;
    }

    /**
     * Un renglón por artículo. `cantidad` en cero da de alta el artículo y le
     * abre la existencia sin movimiento: contar cero también es información.
     *
     * Las llaves opcionales (`ubicacion`, `codigo_barras`, `idsteelex`,
     * `verifica`) se pueden omitir: los layouts viejos no las traían.
     *
     * @return list<array{descripcion: string, unidad: string, area: string|null, abc: string, stock_minimo: float|null, cantidad: float, costo: float|null, nota: string|null, ubicacion?: string|null, codigo_barras?: string|null, idsteelex?: string|null, verifica?: bool}>
     */
    abstract protected function articulos(): array;

    public function run(): void
    {
        $almacen = $this->almacenDestino();

        if ($almacen === null) {
            $this->command?->error("No existe el almacén {$this->etiqueta()}: dalo de alta antes de cargarle saldo.");

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
            $this->command?->warn("{$this->etiqueta()} ya tiene carga inicial ({$abierto->folio}); no se vuelve a cargar.");

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
            $this->etiqueta(),
            count($this->articulos()),
            number_format($this->valuacion(), 2),
            $folio,
        ));
    }

    /**
     * El almacén al que entra la carga. Sin obra es el central, y la clave de un
     * central es única en todo el sistema; con obra, la clave se repite entre
     * obras y hace falta la pareja para no cargarle a la equivocada.
     */
    private function almacenDestino(): ?Almacen
    {
        $query = Almacen::query()->where('clave', $this->almacen());

        $obra = $this->obra();

        if ($obra === null) {
            return $query->whereNull('obra_id')->first();
        }

        return $query
            ->whereHas('obra', fn ($q) => $q->where('no', $obra))
            ->first();
    }

    private function etiqueta(): string
    {
        return $this->obra() === null
            ? "central {$this->almacen()}"
            : "{$this->almacen()} de {$this->obra()}";
    }

    /**
     * El lugar dentro del almacén, dado de alta al vuelo. El layout trae el
     * código tal como lo dice el almacenista ("RACK 10", "1.1-1.3") y no una
     * jerarquía: se guardan planos, que es como se buscan.
     */
    private function ubicacion(Almacen $almacen, ?string $codigo): ?int
    {
        if ($codigo === null || trim($codigo) === '') {
            return null;
        }

        return Ubicacion::firstOrCreate(
            ['almacen_id' => $almacen->id, 'codigo' => trim($codigo)],
            ['nombre' => trim($codigo), 'tipo' => UbicacionTipo::Zona, 'activa' => true],
        )->id;
    }

    private function cargar(Almacen $almacen, Usuario $autoriza): ?string
    {
        return DB::transaction(function () use ($almacen, $autoriza): ?string {
            $areas = Area::query()->pluck('id', 'descripcion');
            $generador = app(GeneradorCodigoArticulo::class);
            $maestro = app(CatalogoMaestro::class);
            $renglones = [];
            $ubicaciones = [];

            foreach ($this->articulos() as $articulo) {
                if ($articulo['area'] !== null && ! $areas->has($articulo['area'])) {
                    throw new RuntimeException("El área \"{$articulo['area']}\" no está en el catálogo; corre primero el seeder de áreas.");
                }

                $areaId = $articulo['area'] === null ? null : $areas[$articulo['area']];

                // El maestro decide si esto es nuevo. Un segundo almacén con
                // "TALADRO MAGNETICO" reutiliza el artículo del primero en vez
                // de estrenar otro código: la existencia se parte por almacén,
                // no la identidad.
                $item = $maestro->buscarOCrear($articulo['descripcion'], $articulo['unidad'], null, $autoriza->getKey());
                $existente = $item->articulo()->first();

                if ($existente !== null) {
                    $ubicaciones[$existente->id] = $this->ubicacion($almacen, $articulo['ubicacion'] ?? null);
                    $renglones[] = [
                        'articulo_id' => $existente->id,
                        'cantidad_contada' => $articulo['cantidad'],
                        'costo_unitario' => $articulo['costo'],
                        'observaciones' => $articulo['nota'],
                    ];

                    continue;
                }

                if ($item->codigo === null) {
                    $item->update(['codigo' => $generador->siguiente()]);
                }

                $codigo = $item->codigo;

                $nuevo = Articulo::create([
                    'item_id' => $item->id,
                    // Sin `producto_id` salvo que Compras ya lo compre: el
                    // modelo lo liga al producto del item si existe.
                    'producto_id' => $item->producto()->value('id'),
                    'codigo' => $codigo,
                    // La etiqueta que se imprime es la nuestra, salvo que la
                    // caja ya traiga la del fabricante.
                    'codigo_barras' => $articulo['codigo_barras'] ?? $codigo,
                    'descripcion' => $item->descripcion,
                    'unidad' => $item->unidad,
                    'idsteelex' => $articulo['idsteelex'] ?? null,
                    'area_id' => $areaId,
                    'clasificacion_abc' => $articulo['abc'],
                    'stock_minimo' => $articulo['stock_minimo'],
                    'tipo' => ProductoTipo::Insumo,
                    'se_controla_por_pieza' => false,
                    'requiere_verificacion' => $articulo['verifica'] ?? false,
                    'activo' => true,
                    'creado_por' => $autoriza->getKey(),
                ]);

                $ubicaciones[$nuevo->id] = $this->ubicacion($almacen, $articulo['ubicacion'] ?? null);

                $renglones[] = [
                    'articulo_id' => $nuevo->id,
                    'cantidad_contada' => $articulo['cantidad'],
                    'costo_unitario' => $articulo['costo'],
                    'observaciones' => $articulo['nota'],
                ];
            }

            $ajuste = app(RegistradorAjuste::class)->registrar(
                cabecera: [
                    'almacen_id' => $almacen->id,
                    'motivo' => AjusteMotivo::CargaInicial->value,
                    'fecha' => now()->toDateString(),
                    'observaciones' => 'Carga inicial del almacén.',
                    'autorizado_por' => $autoriza->getKey(),
                ],
                renglones: $renglones,
                userId: $autoriza->getKey(),
            );

            // El acomodo va después del ajuste porque la existencia la abre el
            // ledger: la ubicación no es saldo, es dónde quedó guardado.
            foreach (array_filter($ubicaciones) as $articuloId => $ubicacionId) {
                Existencia::query()
                    ->where('almacen_id', $almacen->id)
                    ->where('articulo_id', $articuloId)
                    ->update(['ubicacion_id' => $ubicacionId]);
            }

            $this->command?->line('  Sus artículos entran sin ligar a Compras: se emparejan después, a mano.');

            return $ajuste->folio;
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
