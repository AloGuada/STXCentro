<?php

namespace App\Services\Alm;

use App\Enums\Alm\MovimientoTipo;
use App\Exceptions\Alm\ExistenciaInsuficienteException;
use App\Models\Alm\Existencia;
use App\Models\Alm\Movimiento;
use App\Models\Costos\Producto;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Única puerta de escritura del saldo de almacén.
 *
 * Espeja `App\Services\Costos\AcumuladoLedger`: cada llamada deja un asiento con
 * `saldo_antes` y `saldo_despues`, de modo que el `saldo_despues` del último
 * movimiento de una existencia es, por construcción, su `cantidad` vigente.
 * Ningún otro código hace `increment`, `decrement` ni `update` sobre `cantidad`,
 * `valor` o `costo_promedio` — un ledger con puntos ciegos es peor que no
 * tenerlo, porque reportaría descuadres falsos.
 *
 * Costeo: `valor` es la verdad y `costo_promedio` la derivada. Sólo las cargas
 * con costo mueven el promedio; las descargas salen al promedio vigente, salvo
 * las piezas con número de serie, que salen al costo de *esa* pieza.
 */
class AlmacenLedger
{
    /**
     * Registra el movimiento sobre una existencia **que el llamador ya bloqueó**
     * dentro de su propia transacción.
     *
     * @param  float  $cantidad  con signo: positiva carga, negativa descarga
     * @param  float|null  $costoUnitario  nulo = se usa el promedio vigente
     * @param  Model|null  $documento  entrada, salida, transferencia, ajuste o pieza
     * @param  string|null  $referencia  folio sellado; sobrevive al documento
     * @param  bool  $permitirNegativo  sólo el ajuste y el reverso de una entrada
     */
    public function registrar(
        Existencia $existencia,
        MovimientoTipo $tipo,
        float $cantidad,
        ?float $costoUnitario = null,
        ?Model $documento = null,
        ?string $referencia = null,
        ?int $ubicacionId = null,
        ?int $activoId = null,
        ?string $observaciones = null,
        ?string $userId = null,
        bool $permitirNegativo = false,
        bool $esReverso = false,
    ): Movimiento {
        $this->validarSigno($tipo, $cantidad, $esReverso);

        $epsilon = (float) config('costos.epsilon_cantidad');

        $cantidadAntes = (float) $existencia->cantidad;
        $valorAntes = (float) $existencia->valor;
        $promedioAntes = (float) $existencia->costo_promedio;

        $cantidadDespues = $cantidadAntes + $cantidad;

        if ($cantidad < 0 && $cantidadDespues < -$epsilon && ! $permitirNegativo) {
            throw new ExistenciaInsuficienteException(
                almacenId: (int) $existencia->almacen_id,
                productoId: (int) $existencia->producto_id,
                disponible: $cantidadAntes,
                solicitado: abs($cantidad),
                descripcionProducto: (string) ($existencia->producto?->descripcion ?? ''),
            );
        }

        [$costoAplicado, $valorDespues, $promedioDespues] = $this->costear(
            $cantidad,
            $costoUnitario,
            $cantidadAntes,
            $cantidadDespues,
            $valorAntes,
            $promedioAntes,
            $epsilon,
        );

        $existencia->forceFill([
            'cantidad' => $cantidadDespues,
            'valor' => $valorDespues,
            'costo_promedio' => $promedioDespues,
            'ultimo_movimiento_at' => now(),
        ])->save();

        return Movimiento::create([
            'existencia_id' => $existencia->getKey(),
            'almacen_id' => $existencia->almacen_id,
            'producto_id' => $existencia->producto_id,
            'tipo' => $tipo,
            'cantidad' => $cantidad,
            'saldo_antes' => $cantidadAntes,
            'saldo_despues' => $cantidadDespues,
            'costo_unitario' => $costoAplicado,
            'costo_promedio_despues' => $promedioDespues,
            'valor_despues' => $valorDespues,
            'documento_type' => $documento?->getMorphClass(),
            'documento_id' => $documento?->getKey(),
            'referencia' => $referencia,
            'ubicacion_id' => $ubicacionId ?? $existencia->ubicacion_id,
            'activo_id' => $activoId,
            'es_reverso' => $esReverso,
            'observaciones' => $observaciones,
            'usuario_id' => $userId ?? Auth::id(),
        ]);
    }

    /**
     * Puerta normal: bloquea (o crea) la fila almacén+producto y registra.
     *
     * Devuelve `null` —sin excepción y sin fila— cuando el artículo no lleva
     * kardex. Es lo que permite pasarle una recepción completa sin que cada
     * llamador tenga que filtrar fletes, maniobras y lo que Compras tecleó al
     * vuelo sin código.
     */
    public function registrarPorProducto(
        int $almacenId,
        int $productoId,
        MovimientoTipo $tipo,
        float $cantidad,
        ?float $costoUnitario = null,
        ?Model $documento = null,
        ?string $referencia = null,
        ?int $ubicacionId = null,
        ?int $activoId = null,
        ?string $observaciones = null,
        ?string $userId = null,
        bool $permitirNegativo = false,
        bool $esReverso = false,
    ): ?Movimiento {
        if (! $this->llevaKardex($productoId)) {
            return null;
        }

        return DB::transaction(fn (): Movimiento => $this->registrar(
            $this->bloquear($almacenId, $productoId),
            $tipo,
            $cantidad,
            $costoUnitario,
            $documento,
            $referencia,
            $ubicacionId,
            $activoId,
            $observaciones,
            $userId,
            $permitirNegativo,
            $esReverso,
        ));
    }

    /**
     * La fila del saldo, bloqueada para el resto de la transacción.
     *
     * `firstOrCreate` no acepta `lockForUpdate`, así que se busca con lock y, si
     * no está, se inserta atrapando el choque de unique: dos recepciones
     * simultáneas del mismo artículo pueden llegar aquí a la vez, y la que
     * pierde tiene que releer la fila de la otra, no reventar.
     */
    public function bloquear(int $almacenId, int $productoId): Existencia
    {
        $existencia = Existencia::query()
            ->where('almacen_id', $almacenId)
            ->where('producto_id', $productoId)
            ->lockForUpdate()
            ->first();

        if ($existencia !== null) {
            return $existencia;
        }

        try {
            Existencia::create(['almacen_id' => $almacenId, 'producto_id' => $productoId]);
        } catch (QueryException $e) {
            if (! $this->esChoqueDeUnique($e)) {
                throw $e;
            }
        }

        return Existencia::query()
            ->where('almacen_id', $almacenId)
            ->where('producto_id', $productoId)
            ->lockForUpdate()
            ->firstOrFail();
    }

    /**
     * Lo que quedaría de saldo. Sirve para validar antes de abrir la
     * transacción y poder devolver los errores de todos los renglones juntos,
     * en vez de rebotar el formulario de uno en uno.
     */
    public function disponible(int $almacenId, int $productoId): float
    {
        return (float) Existencia::query()
            ->where('almacen_id', $almacenId)
            ->where('producto_id', $productoId)
            ->value('cantidad') ?? 0.0;
    }

    /**
     * El costo que se aplica, el valor y el promedio resultantes.
     *
     * @return array{0: float|null, 1: float, 2: float}
     */
    private function costear(
        float $cantidad,
        ?float $costoUnitario,
        float $cantidadAntes,
        float $cantidadDespues,
        float $valorAntes,
        float $promedioAntes,
        float $epsilon,
    ): array {
        // Una carga sin costo declarado no puede mover el promedio: no trae
        // información nueva sobre lo que vale el material.
        $costoAplicado = $costoUnitario ?? ($promedioAntes > 0 ? $promedioAntes : null);

        $valorDespues = $valorAntes + $cantidad * (float) ($costoAplicado ?? 0);

        // Cuando el saldo llega a cero, el valor tiene que llegar a cero también:
        // los centavos que sobran de redondear cuatro decimales convertirían un
        // almacén vacío en uno que «vale» tres pesos.
        if (abs($cantidadDespues) <= $epsilon) {
            $valorDespues = 0.0;
        }

        $promedioDespues = $promedioAntes;

        if ($cantidad > 0 && $costoUnitario !== null) {
            $promedioDespues = $cantidadDespues > $epsilon
                ? $valorDespues / $cantidadDespues
                : $costoUnitario;
        }

        return [$costoAplicado, $valorDespues, $promedioDespues];
    }

    /**
     * El artículo mueve existencia. Fletes, maniobras y servicios se compran
     * pero no se almacenan, y lo que Compras tecleó sin código queda fuera hasta
     * que Almacén lo clasifique.
     */
    private function llevaKardex(int $productoId): bool
    {
        return Producto::query()
            ->whereKey($productoId)
            ->where('controla_inventario', true)
            ->exists();
    }

    private function validarSigno(MovimientoTipo $tipo, float $cantidad, bool $esReverso): void
    {
        if (abs($cantidad) < 1e-9) {
            throw new InvalidArgumentException('Un movimiento de cantidad cero no deja nada que auditar.');
        }

        // El reverso es el espejo del movimiento original, así que va justo al
        // revés de lo que su tipo pide. Es la única excepción.
        if ($esReverso || $tipo->admiteAmbosSignos()) {
            return;
        }

        $esperado = $tipo->signo();

        if (($cantidad <=> 0) !== $esperado) {
            throw new InvalidArgumentException(sprintf(
                'Un movimiento de tipo %s debe llevar cantidad %s; se recibió %s.',
                $tipo->value,
                $esperado === 1 ? 'positiva' : 'negativa',
                $cantidad,
            ));
        }
    }

    private function esChoqueDeUnique(QueryException $e): bool
    {
        return in_array($e->getCode(), ['23000', '23505'], true);
    }
}
