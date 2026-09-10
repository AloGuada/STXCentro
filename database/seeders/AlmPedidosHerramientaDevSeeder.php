<?php

namespace Database\Seeders;

use App\Enums\Alm\AjusteMotivo;
use App\Enums\Alm\PedidoEstatus;
use App\Enums\Alm\ProductoTipo;
use App\Models\Alm\Activo;
use App\Models\Alm\Almacen;
use App\Models\Alm\Articulo;
use App\Models\Alm\Existencia;
use App\Models\Alm\Pedido;
use App\Models\Costos\Producto;
use App\Models\Departamento;
use App\Models\Obra;
use App\Models\User;
use App\Services\Alm\RegistradorAjuste;
use App\Services\Alm\RegistradorPiezas;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Seeder;

/**
 * Pedidos de ejemplo para probar cómo se surte la herramienta.
 *
 *   php artisan db:seed --class=AlmPedidosHerramientaDevSeeder
 *
 * Deja tres pedidos aprobados sobre el almacén que tiene piezas con serie
 * disponibles: uno sólo de insumos (se surte con salida), uno sólo de
 * herramienta (se surte con préstamo) y uno mixto que ofrece los dos caminos.
 * Para que haya un activo **por cantidad** que prestar, da de alta una
 * extensión eléctrica sin serie con doce unidades, por
 * {@see RegistradorPiezas::altaPorCantidad()}: nunca con un insert.
 *
 * Idempotente: cada pedido lleva {@see self::MARCA} en sus observaciones y si
 * ya están, se omite. Solo para desarrollo.
 */
class AlmPedidosHerramientaDevSeeder extends Seeder
{
    private const MARCA = '[demo-alm-herramienta]';

    public function run(): void
    {
        $usuario = User::first();

        if ($usuario === null) {
            $this->command?->error('No hay usuarios: corre primero db:seed.');

            return;
        }

        if (Pedido::query()->where('observaciones', 'like', '%'.self::MARCA.'%')->exists()) {
            $this->command?->warn('· Pedidos de herramienta: ya sembrados, se omiten.');

            return;
        }

        $almacen = $this->almacenConHerramienta();

        if ($almacen === null) {
            $this->command?->error('No hay piezas con serie disponibles: corre antes AlmMovimientosDevSeeder.');

            return;
        }

        $usuarioId = $usuario->getAuthIdentifier();
        $pulidora = Activo::query()->where('almacen_id', $almacen->id)->disponibles()->with('articulo')->first()?->articulo;
        $extension = $this->extensionPorCantidad($almacen, $usuarioId);
        $insumos = $this->insumosDe($almacen, $usuarioId);

        $obra = Obra::where('no', 'MBP')->first() ?? Obra::first();
        $departamento = Departamento::where('descripcion', 'Montaje')->first() ?? Departamento::first();

        $escenarios = [
            [
                'motivo' => 'Consumible para la semana (sólo insumos)',
                'obra' => null,
                'renglones' => $insumos->map(fn (Existencia $e): array => [
                    'articulo_id' => $e->articulo_id,
                    'cantidad_solicitada' => min(10, (float) $e->cantidad),
                ])->all(),
            ],
            [
                'motivo' => 'Herramienta para el frente 3 (sólo herramienta)',
                'obra' => null,
                'renglones' => array_values(array_filter([
                    $pulidora === null ? null : ['articulo_id' => $pulidora->id, 'cantidad_solicitada' => 2],
                    ['articulo_id' => $extension->id, 'cantidad_solicitada' => 4],
                ])),
            ],
            [
                'motivo' => 'Arranque de obra: material y herramienta (mixto)',
                'obra' => $obra,
                'renglones' => array_values(array_filter([
                    $insumos->first() === null ? null : [
                        'articulo_id' => $insumos->first()->articulo_id,
                        'cantidad_solicitada' => min(5, (float) $insumos->first()->cantidad),
                    ],
                    $pulidora === null ? null : ['articulo_id' => $pulidora->id, 'cantidad_solicitada' => 1],
                    ['articulo_id' => $extension->id, 'cantidad_solicitada' => 3],
                ])),
            ],
        ];

        $sembrados = 0;

        foreach ($escenarios as $i => $escenario) {
            if ($escenario['renglones'] === []) {
                continue;
            }

            $pedido = Pedido::create([
                'almacen_id' => $almacen->id,
                'departamento_id' => $departamento?->id,
                'obra_id' => $escenario['obra']?->id,
                'solicitante_id' => $usuarioId,
                'recibe_nombre' => 'Cuadrilla de montaje',
                'grupo_trabajo_id' => null,
                'fecha' => now()->subDays(3 - $i)->toDateString(),
                'fecha_requerida' => now()->addDays(2 + $i)->toDateString(),
                'motivo' => $escenario['motivo'],
                'estatus' => PedidoEstatus::Aprobado,
                'aprobado_por' => $usuarioId,
                'aprobado_at' => now()->subDays(3 - $i),
                'observaciones' => self::MARCA,
            ]);

            foreach ($escenario['renglones'] as $renglon) {
                $pedido->detalles()->create([...$renglon, 'cantidad_surtida' => 0]);
            }

            $sembrados++;
        }

        $this->command?->info(sprintf(
            '· Pedidos de herramienta: %d sembrados sobre %s (insumos, herramienta, mixto).',
            $sembrados,
            $almacen->clave,
        ));
    }

    /**
     * Dos insumos con saldo en el almacén, para el pedido de consumibles y el
     * mixto. El almacén de herramienta suele no tener insumos: entonces se le
     * abre uno con un ajuste de carga inicial, que es como se abre existencia
     * donde no había.
     *
     * @return Collection<int, Existencia>
     */
    private function insumosDe(Almacen $almacen, string $usuarioId): Collection
    {
        $consulta = fn () => Existencia::query()
            ->where('almacen_id', $almacen->id)
            ->where('cantidad', '>', 0)
            ->whereHas('articulo', fn ($q) => $q->where('tipo', ProductoTipo::Insumo)->where('activo', true))
            ->orderByDesc('cantidad')
            ->limit(2)
            ->get();

        $insumos = $consulta();

        if ($insumos->isNotEmpty()) {
            return $insumos;
        }

        $articulo = Articulo::query()
            ->where('tipo', ProductoTipo::Insumo)
            ->where('activo', true)
            ->orderBy('id')
            ->first();

        if ($articulo === null) {
            return $insumos;
        }

        app(RegistradorAjuste::class)->registrar(
            cabecera: [
                'almacen_id' => $almacen->id,
                'motivo' => AjusteMotivo::CargaInicial->value,
                'fecha' => today(),
                'observaciones' => self::MARCA.' insumo para el pedido de ejemplo',
                'autorizado_por' => $usuarioId,
            ],
            renglones: [['articulo_id' => $articulo->id, 'cantidad_contada' => 50, 'costo_unitario' => 10]],
            userId: $usuarioId,
        );

        return $consulta();
    }

    /** El almacén con piezas con serie disponibles para prestar. */
    private function almacenConHerramienta(): ?Almacen
    {
        $almacenId = Activo::query()->disponibles()->value('almacen_id');

        return $almacenId === null ? null : Almacen::find($almacenId);
    }

    /**
     * Un activo sin serie con existencia en el almacén: es lo que se presta por
     * cantidad. Se dan de alta las dos caras del catálogo, como lo hace la
     * pantalla de artículos, y doce unidades por el registrador.
     */
    private function extensionPorCantidad(Almacen $almacen, string $usuarioId): Articulo
    {
        $producto = Producto::firstOrCreate(
            ['codigo' => 'ART-000202'],
            ['descripcion' => 'Extensión eléctrica 25 m calibre 12', 'unidad' => 'PZA', 'activo' => true],
        );

        $articulo = Articulo::firstOrCreate(
            ['producto_id' => $producto->id],
            [
                'codigo' => $producto->codigo,
                'descripcion' => $producto->descripcion,
                'unidad' => $producto->unidad,
                'tipo' => ProductoTipo::Activo,
                'se_controla_por_pieza' => false,
                'activo' => true,
            ],
        );

        if ($articulo->tipo !== ProductoTipo::Activo || $articulo->se_controla_por_pieza) {
            $articulo->update(['tipo' => ProductoTipo::Activo, 'se_controla_por_pieza' => false]);
        }

        $existencia = Existencia::query()
            ->where('almacen_id', $almacen->id)
            ->where('articulo_id', $articulo->id)
            ->first();

        if ($existencia === null || (float) $existencia->cantidad <= 0) {
            app(RegistradorPiezas::class)->altaPorCantidad(
                articulo: $articulo,
                almacen: $almacen,
                cantidad: 12,
                costo: 1240,
                userId: $usuarioId,
                observaciones: self::MARCA,
            );
        }

        return $articulo;
    }
}
