<?php

namespace Database\Seeders\Alm;

use App\Enums\Alm\ActivoEstatus;
use App\Enums\Alm\ProductoTipo;
use App\Enums\Alm\UbicacionTipo;
use App\Models\Alm\Activo;
use App\Models\Alm\Almacen;
use App\Models\Alm\Area;
use App\Models\Alm\Ubicacion;
use App\Models\Costos\Producto;
use App\Models\Usuario;
use App\Services\Alm\GeneradorCodigoArticulo;
use App\Services\Alm\RegistradorPiezas;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Carga inicial de los activos de un almacén: el padrón de piezas con el que
 * arrancó.
 *
 * Es el gemelo de {@see CargaInicialSeeder} para lo que no se consume. La
 * diferencia no es de formato sino de naturaleza: el insumo se cuenta y el
 * activo se sigue pieza por pieza, así que aquí un renglón es *una pieza* y no
 * un artículo. Diez esmeriladoras iguales son diez renglones bajo el mismo
 * artículo, y es lo único que después permite saber quién trae cuál.
 *
 * El saldo no se escribe: cada pieza emite +1 al kardex por
 * {@see RegistradorPiezas}, que es el único que puede crearlas. Así se sostiene
 * el invariante de que la existencia de un artículo por pieza es exactamente el
 * número de sus piezas vigentes.
 *
 * El estatus se aplica **después** del alta. `RegistradorPiezas::alta()` las
 * nace todas `disponible` a propósito —el préstamo no es un alta— pero el
 * layout ya viene con piezas prestadas desde el primer día, y `prestado` cuenta
 * en existencia igual que `disponible`.
 */
abstract class CargaInicialActivosSeeder extends Seeder
{
    /** Clave del almacén cuyo padrón se está abriendo. */
    abstract protected function almacen(): string;

    /**
     * Número de obra cuando el almacén es de obra; null es el central.
     */
    protected function obra(): ?string
    {
        return null;
    }

    /**
     * Un renglón por artículo, con sus piezas dentro.
     *
     * @return list<array{descripcion: string, unidad: string, area: string|null, abc: string, ubicacion: string|null, piezas: list<array<string, mixed>>}>
     */
    abstract protected function articulos(): array;

    public function run(): void
    {
        $almacen = $this->almacenDestino();

        if ($almacen === null) {
            $this->command?->error("No existe el almacén {$this->etiqueta()}: dalo de alta antes de cargarle el padrón.");

            return;
        }

        // El padrón se levanta una vez. Correrlo dos veces daría de alta otro
        // juego de piezas con las mismas series bajo artículos nuevos, y el
        // unique (producto, serie) no lo impide porque el artículo sería otro.
        //
        // Se pregunta por *estos* artículos y no por si el almacén tiene piezas:
        // que MTO ya guarde una pulidora de otro lado no quiere decir que su
        // padrón esté levantado.
        if ($this->yaLevantado($almacen)) {
            $this->command?->warn("{$this->etiqueta()} ya tiene este padrón; no se vuelve a cargar.");

            return;
        }

        $autoriza = Usuario::query()->role('super-admin')->orderBy('created_at')->first();

        if ($autoriza === null) {
            $this->command?->error('No hay ningún super-admin que pueda levantar el padrón.');

            return;
        }

        [$articulos, $piezas] = $this->cargar($almacen, $autoriza);

        $this->command?->info(sprintf(
            '%s: %d artículos, %d piezas, $%s.',
            $this->etiqueta(),
            $articulos,
            $piezas,
            number_format($this->valuacion(), 2),
        ));
    }

    /**
     * @return array{0: int, 1: int}
     */
    private function cargar(Almacen $almacen, Usuario $autoriza): array
    {
        return DB::transaction(function () use ($almacen, $autoriza): array {
            $areas = Area::query()->pluck('id', 'descripcion');
            $generador = app(GeneradorCodigoArticulo::class);
            $registrador = app(RegistradorPiezas::class);
            $piezas = 0;

            foreach ($this->articulos() as $articulo) {
                if ($articulo['area'] !== null && ! $areas->has($articulo['area'])) {
                    throw new RuntimeException("El área \"{$articulo['area']}\" no está en el catálogo; corre primero el seeder de catálogo.");
                }

                $codigo = $generador->siguiente();

                $producto = Producto::create([
                    'codigo' => $codigo,
                    'codigo_barras' => $codigo,
                    'descripcion' => $articulo['descripcion'],
                    'unidad' => $articulo['unidad'],
                    'area_id' => $articulo['area'] === null ? null : $areas[$articulo['area']],
                    'clasificacion_abc' => $articulo['abc'],
                    'stock_minimo' => null,
                    'tipo' => ProductoTipo::Activo,
                    'controla_inventario' => true,
                    'se_controla_por_pieza' => true,
                    'requiere_verificacion' => false,
                    'activo' => true,
                    'creado_por' => $autoriza->getKey(),
                ]);

                $creadas = $registrador->alta(
                    producto: $producto,
                    almacen: $almacen,
                    piezas: $articulo['piezas'],
                    ubicacionId: $this->ubicacion($almacen, $articulo['ubicacion']),
                    userId: $autoriza->getKey(),
                );

                $this->aplicarEstatus($creadas, $articulo['piezas']);
                $piezas += count($creadas);
            }

            return [count($this->articulos()), $piezas];
        });
    }

    /**
     * Lo que el layout dice que anda prestado o en reparación. No toca el saldo
     * —las dos siguen contando en existencia— así que va por `update` directo y
     * no por el registrador, que sólo gobierna alta, baja y traslado.
     *
     * @param  list<Activo>  $creadas
     * @param  list<array<string, mixed>>  $piezas
     */
    private function aplicarEstatus(array $creadas, array $piezas): void
    {
        foreach ($creadas as $i => $activo) {
            $estatus = ActivoEstatus::tryFrom($piezas[$i]['estatus'] ?? '');

            if ($estatus !== null && $estatus !== ActivoEstatus::Disponible) {
                $activo->update(['estatus' => $estatus]);
            }
        }
    }

    /**
     * Si alguno de los artículos de este layout ya tiene piezas en el almacén,
     * el padrón ya entró. Basta uno: una carga a medias no debe completarse por
     * su cuenta duplicando lo que sí quedó.
     */
    private function yaLevantado(Almacen $almacen): bool
    {
        $descripciones = array_column($this->articulos(), 'descripcion');

        return Activo::query()
            ->where('almacen_id', $almacen->id)
            ->whereIn(
                'producto_id',
                Producto::query()->whereIn('descripcion', $descripciones)->select('id'),
            )
            ->exists();
    }

    private function almacenDestino(): ?Almacen
    {
        $query = Almacen::query()->where('clave', $this->almacen());

        $obra = $this->obra();

        if ($obra === null) {
            return $query->whereNull('obra_id')->first();
        }

        return $query->whereHas('obra', fn ($q) => $q->where('no', $obra))->first();
    }

    private function etiqueta(): string
    {
        return $this->obra() === null
            ? "central {$this->almacen()}"
            : "{$this->almacen()} de {$this->obra()}";
    }

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

    private function valuacion(): float
    {
        $total = 0.0;

        foreach ($this->articulos() as $articulo) {
            foreach ($articulo['piezas'] as $pieza) {
                $total += (float) ($pieza['costo'] ?? 0);
            }
        }

        return $total;
    }
}
