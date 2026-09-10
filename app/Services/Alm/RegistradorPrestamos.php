<?php

namespace App\Services\Alm;

use App\Enums\Alm\ActivoEstatus;
use App\Enums\Alm\PrestamoEstatus;
use App\Models\Alm\Activo;
use App\Models\Alm\Almacen;
use App\Models\Alm\Articulo;
use App\Models\Alm\Existencia;
use App\Models\Alm\Pedido;
use App\Models\Alm\Prestamo;
use App\Models\Alm\PrestamoDetalle;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * El único que presta y recibe activos.
 *
 * Prestar no es un movimiento del kardex: el saldo no cambia, lo que cambia es
 * la custodia. Para una pieza con serie eso es su estatus (`prestado`); para
 * un activo por cantidad es `existencias.prestado`, el cacheado de cuánto del
 * renglón anda afuera. Las dos cosas se escriben sólo desde aquí, en la misma
 * transacción que el resguardo, para que lo que dice el documento y lo que
 * dice el almacén no se puedan desincronizar.
 */
class RegistradorPrestamos
{
    public function __construct(private readonly SurtidoPedido $surtido) {}

    /**
     * Si surte un pedido, al terminar lo recalcula: lo prestado cuenta como
     * entregado y el pedido puede quedar surtido con este mismo documento.
     *
     * @param  array<string, mixed>  $cabecera
     * @param  list<array{articulo_id: int, activo_id?: int|null, cantidad?: float|int|string|null, condicion_salida?: string|null, observaciones?: string|null}>  $renglones
     */
    public function prestar(Almacen $almacen, array $cabecera, array $renglones, ?string $userId = null): Prestamo
    {
        return DB::transaction(function () use ($almacen, $cabecera, $renglones, $userId): Prestamo {
            $prestamo = Prestamo::create([
                ...$cabecera,
                'almacen_id' => $almacen->id,
                'estatus' => PrestamoEstatus::Abierto,
                'creado_por' => $userId,
            ]);

            foreach ($renglones as $i => $renglon) {
                $activoId = isset($renglon['activo_id']) && $renglon['activo_id'] !== null && $renglon['activo_id'] !== ''
                    ? (int) $renglon['activo_id']
                    : null;

                $activoId !== null
                    ? $this->prestarPieza($prestamo, $almacen, $activoId, $renglon, $i)
                    : $this->prestarCantidad($prestamo, $almacen, (int) $renglon['articulo_id'], $renglon, $i);
            }

            if ($prestamo->pedido_id !== null) {
                $pedido = Pedido::with('detalles')->find($prestamo->pedido_id);

                if ($pedido !== null) {
                    $this->surtido->recalcular($pedido);
                }
            }

            return $prestamo->load('detalles');
        });
    }

    /**
     * Recibe lo que vuelve. Los renglones pueden ser de resguardos distintos:
     * la persona entrega lo que trae, no un vale a la vez. Cada resguardo que
     * se queda sin pendientes cierra solo.
     *
     * @param  list<array{detalle_id: int, cantidad?: float|int|string|null, condicion_retorno?: string|null, en_reparacion?: bool}>  $renglones
     * @return Collection<int, Prestamo> los resguardos tocados
     */
    public function devolver(array $renglones, CarbonInterface $fecha, ?string $recibioId, ?string $userId = null): Collection
    {
        return DB::transaction(function () use ($renglones, $fecha, $recibioId, $userId): Collection {
            $tocados = collect();

            foreach ($renglones as $i => $renglon) {
                /** @var PrestamoDetalle $detalle */
                $detalle = PrestamoDetalle::query()->lockForUpdate()->findOrFail((int) $renglon['detalle_id']);
                $pendiente = $detalle->pendiente();

                if ($pendiente <= 0) {
                    throw ValidationException::withMessages([
                        "renglones.{$i}.detalle_id" => 'Ese renglón ya está devuelto.',
                    ]);
                }

                $cantidad = $detalle->esPorPieza() ? 1.0 : (float) ($renglon['cantidad'] ?? $pendiente);

                if ($cantidad <= 0 || $cantidad > $pendiente + (float) config('costos.epsilon_cantidad')) {
                    throw ValidationException::withMessages([
                        "renglones.{$i}.cantidad" => "Se pueden devolver hasta {$pendiente}; ni cero ni más.",
                    ]);
                }

                if ($detalle->esPorPieza()) {
                    $activo = Activo::query()->lockForUpdate()->findOrFail($detalle->activo_id);
                    $activo->update([
                        'estatus' => ! empty($renglon['en_reparacion']) ? ActivoEstatus::EnReparacion : ActivoEstatus::Disponible,
                        'condicion' => $renglon['condicion_retorno'] ?: $activo->condicion,
                    ]);
                } else {
                    $existencia = $this->existenciaBloqueada($detalle->prestamo->almacen_id, $detalle->articulo_id);
                    $existencia->forceFill([
                        'prestado' => max(0.0, (float) $existencia->prestado - $cantidad),
                    ])->save();
                }

                $detalle->fill([
                    'cantidad_devuelta' => (float) $detalle->cantidad_devuelta + $cantidad,
                    'condicion_retorno' => $renglon['condicion_retorno'] ?? $detalle->condicion_retorno,
                    'devuelto_en' => $fecha,
                    'recibido_por' => $recibioId ?? $userId,
                ])->save();

                $tocados[$detalle->prestamo_id] = $detalle->prestamo;
            }

            foreach ($tocados as $prestamo) {
                $prestamo->load('detalles');

                if ($prestamo->pendiente() <= (float) config('costos.epsilon_cantidad')) {
                    $prestamo->update([
                        'estatus' => PrestamoEstatus::Cerrado,
                        'cerrado_en' => now(),
                    ]);
                }
            }

            return $tocados->values();
        });
    }

    /**
     * Lo que este almacén puede prestar hoy: las piezas disponibles y, de los
     * activos por cantidad, cuántos quedan sin resguardo.
     *
     * @return array{piezas: list<array<string, mixed>>, por_cantidad: list<array<string, mixed>>}
     */
    public function prestables(Almacen $almacen): array
    {
        $piezas = Activo::query()
            ->where('almacen_id', $almacen->id)
            ->disponibles()
            ->with(['articulo:id,codigo,descripcion', 'ubicacion.padre'])
            ->get()
            ->sortBy(fn (Activo $a): string => ($a->articulo?->descripcion ?? '').$a->no_serie)
            ->map(fn (Activo $a): array => [
                'id' => $a->id,
                'articulo_id' => $a->articulo_id,
                'codigo' => $a->articulo?->codigo,
                'descripcion' => $a->articulo?->descripcion,
                'no_serie' => $a->no_serie,
                'marca' => $a->marca,
                'modelo' => $a->modelo,
                'condicion' => $a->condicion,
                'ubicacion' => $a->ubicacion?->ruta(),
            ])
            ->values()
            ->all();

        $porCantidad = Existencia::query()
            ->where('almacen_id', $almacen->id)
            ->whereHas('articulo', fn ($q) => $q->activosPorCantidad()->where('activo', true))
            ->with('articulo:id,codigo,descripcion,unidad')
            ->get()
            ->filter(fn (Existencia $e): bool => $e->disponibleParaPrestar() > 0)
            ->sortBy(fn (Existencia $e): string => $e->articulo?->descripcion ?? '')
            ->map(fn (Existencia $e): array => [
                'articulo_id' => $e->articulo_id,
                'codigo' => $e->articulo?->codigo,
                'descripcion' => $e->articulo?->descripcion,
                'unidad' => $e->articulo?->unidad,
                'cantidad' => (float) $e->cantidad,
                'prestado' => (float) $e->prestado,
                'disponible' => $e->disponibleParaPrestar(),
            ])
            ->values()
            ->all();

        return ['piezas' => $piezas, 'por_cantidad' => $porCantidad];
    }

    /**
     * @param  array<string, mixed>  $renglon
     */
    private function prestarPieza(Prestamo $prestamo, Almacen $almacen, int $activoId, array $renglon, int $i): void
    {
        $activo = Activo::query()->lockForUpdate()->find($activoId);

        if ($activo === null || (int) $activo->almacen_id !== (int) $almacen->id) {
            throw ValidationException::withMessages([
                "renglones.{$i}.activo_id" => 'Esa pieza no está en este almacén.',
            ]);
        }

        if ($activo->estatus !== ActivoEstatus::Disponible) {
            throw ValidationException::withMessages([
                "renglones.{$i}.activo_id" => "La pieza {$activo->no_serie} no está disponible: está {$activo->estatus->etiqueta()}.",
            ]);
        }

        $activo->update(['estatus' => ActivoEstatus::Prestado]);

        $prestamo->detalles()->create([
            'articulo_id' => $activo->articulo_id,
            'activo_id' => $activo->id,
            'pedido_detalle_id' => $renglon['pedido_detalle_id'] ?? null,
            'cantidad' => 1,
            'condicion_salida' => $renglon['condicion_salida'] ?? $activo->condicion,
            'observaciones' => $renglon['observaciones'] ?? null,
        ]);
    }

    /**
     * @param  array<string, mixed>  $renglon
     */
    private function prestarCantidad(Prestamo $prestamo, Almacen $almacen, int $articuloId, array $renglon, int $i): void
    {
        $articulo = Articulo::query()->find($articuloId);

        if ($articulo === null || ! $articulo->esActivoPorCantidad()) {
            throw ValidationException::withMessages([
                "renglones.{$i}.articulo_id" => 'Ese artículo no es un activo por cantidad: si lleva serie, elige la pieza.',
            ]);
        }

        $cantidad = (float) ($renglon['cantidad'] ?? 0);

        if ($cantidad <= 0) {
            throw ValidationException::withMessages([
                "renglones.{$i}.cantidad" => 'Di cuántas se prestan.',
            ]);
        }

        $existencia = Existencia::query()
            ->where('almacen_id', $almacen->id)
            ->where('articulo_id', $articulo->id)
            ->lockForUpdate()
            ->first();

        $disponible = $existencia?->disponibleParaPrestar() ?? 0.0;

        if ($existencia === null || $cantidad > $disponible + (float) config('costos.epsilon_cantidad')) {
            throw ValidationException::withMessages([
                "renglones.{$i}.cantidad" => "De {$articulo->descripcion} sólo hay {$disponible} sin resguardo en este almacén.",
            ]);
        }

        $existencia->forceFill(['prestado' => (float) $existencia->prestado + $cantidad])->save();

        $prestamo->detalles()->create([
            'articulo_id' => $articulo->id,
            'activo_id' => null,
            'pedido_detalle_id' => $renglon['pedido_detalle_id'] ?? null,
            'cantidad' => $cantidad,
            'condicion_salida' => $renglon['condicion_salida'] ?? null,
            'observaciones' => $renglon['observaciones'] ?? null,
        ]);
    }

    private function existenciaBloqueada(int $almacenId, int $articuloId): Existencia
    {
        return Existencia::query()
            ->where('almacen_id', $almacenId)
            ->where('articulo_id', $articuloId)
            ->lockForUpdate()
            ->firstOrFail();
    }
}
