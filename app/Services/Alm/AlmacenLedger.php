<?php

namespace App\Services\Alm;

use App\Enums\Alm\MovimientoTipo;
use App\Exceptions\Alm\AsignacionAjenaException;
use App\Exceptions\Alm\ExistenciaInsuficienteException;
use App\Models\Alm\Articulo;
use App\Models\Alm\Asignacion;
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
     * @param  int|null  $obraId  de quién es el material; `null` = libre
     * @param  bool  $permitirAjena  deja pasar sobre lo comprometido con otra obra
     *
     * **Puede dejar más de un asiento.** Una descarga que cruza orígenes —20 de
     * su obra y 10 de lo libre— deja un asiento por cada uno, no uno con la
     * suma: es lo que permite que la cancelación devuelva cada parte a donde
     * estaba sin adivinar. Devuelve el primero; todos comparten `documento` y
     * `referencia`, y el `costo_unitario` es el mismo porque la descarga no
     * mueve el promedio.
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
        ?int $obraId = null,
        bool $permitirAjena = false,
    ): Movimiento {
        $this->validarSigno($tipo, $cantidad, $esReverso);

        $epsilon = (float) config('costos.epsilon_cantidad');

        // Lo que anda afuera en resguardo sigue en el saldo, pero no está en
        // el anaquel: una salida o una transferencia no lo pueden sacar. Sólo
        // el ajuste y los reversos (`permitirNegativo`) pasan por encima.
        $enAnaquel = (float) $existencia->cantidad - (float) $existencia->prestado;

        if ($cantidad < 0 && $enAnaquel + $cantidad < -$epsilon && ! $permitirNegativo) {
            throw new ExistenciaInsuficienteException(
                almacenId: (int) $existencia->almacen_id,
                productoId: (int) ($existencia->producto_id ?? 0),
                disponible: $enAnaquel,
                solicitado: abs($cantidad),
                descripcionProducto: (string) ($existencia->articulo?->descripcion ?? $existencia->producto?->descripcion ?? ''),
            );
        }

        $tramos = $cantidad > 0
            ? [[$obraId, $cantidad]]
            : $this->repartirDescarga($existencia, abs($cantidad), $obraId, $permitirAjena, $permitirNegativo, $epsilon);

        $asientos = [];

        foreach ($tramos as [$obraDelTramo, $cantidadDelTramo]) {
            $asientos[] = $this->asentar(
                existencia: $existencia,
                tipo: $tipo,
                cantidad: $cantidad > 0 ? $cantidadDelTramo : -$cantidadDelTramo,
                obraId: $obraDelTramo,
                costoUnitario: $costoUnitario,
                documento: $documento,
                referencia: $referencia,
                ubicacionId: $ubicacionId,
                activoId: $activoId,
                observaciones: $observaciones,
                userId: $userId,
                esReverso: $esReverso,
                epsilon: $epsilon,
            );
        }

        return $asientos[0];
    }

    /**
     * Un asiento: mueve el saldo, mueve la partición y deja el renglón del
     * kardex. Las tres cosas juntas o ninguna — por eso vive dentro de la
     * transacción que el llamador ya abrió.
     */
    private function asentar(
        Existencia $existencia,
        MovimientoTipo $tipo,
        float $cantidad,
        ?int $obraId,
        ?float $costoUnitario,
        ?Model $documento,
        ?string $referencia,
        ?int $ubicacionId,
        ?int $activoId,
        ?string $observaciones,
        ?string $userId,
        bool $esReverso,
        float $epsilon,
    ): Movimiento {
        $cantidadAntes = (float) $existencia->cantidad;
        $valorAntes = (float) $existencia->valor;
        $promedioAntes = (float) $existencia->costo_promedio;

        $cantidadDespues = $cantidadAntes + $cantidad;

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

        // Lo libre no tiene renglón que mover: es lo que sobra de repartir.
        if ($obraId !== null) {
            $this->ajustarParticion($existencia, $obraId, $cantidad);
        }

        return Movimiento::create([
            'existencia_id' => $existencia->getKey(),
            'almacen_id' => $existencia->almacen_id,
            'producto_id' => $existencia->producto_id,
            'articulo_id' => $existencia->articulo_id,
            'obra_id' => $obraId,
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
        ?int $obraId = null,
        bool $permitirAjena = false,
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
            $obraId,
            $permitirAjena,
        ));
    }

    /**
     * De dónde sale el material de una descarga, en orden: lo que tiene
     * asignado su propia obra, luego lo que está libre, y sólo con permiso lo
     * que está comprometido con otras.
     *
     * El orden no es arbitrario: gastar primero lo propio es lo que hace que la
     * asignación sirva de algo, y gastar lo libre antes que lo ajeno es lo que
     * evita quitarle material a un tercero mientras hay material sin dueño en el
     * mismo anaquel.
     *
     * @return list<array{0: int|null, 1: float}> obra del tramo (`null` = libre) y cuánto sale de ahí
     */
    private function repartirDescarga(
        Existencia $existencia,
        float $cantidad,
        ?int $obraId,
        bool $permitirAjena,
        bool $permitirNegativo,
        float $epsilon,
    ): array {
        $asignaciones = Asignacion::query()
            ->where('existencia_id', $existencia->getKey())
            ->vivas()
            ->orderBy('id')
            ->get();

        $asignado = (float) $asignaciones->sum('cantidad');
        // Lo prestado tampoco está libre: anda afuera en resguardo.
        $libre = (float) $existencia->cantidad - $asignado - (float) $existencia->prestado;

        $tramos = [];
        $resto = $cantidad;

        $propia = $obraId === null
            ? null
            : $asignaciones->firstWhere('obra_id', $obraId);

        if ($propia !== null && (float) $propia->cantidad > $epsilon) {
            $tramo = min($resto, (float) $propia->cantidad);
            $tramos[] = [$obraId, $tramo];
            $resto -= $tramo;
        }

        // Lo libre, y con `permitirNegativo` también lo que falte: un ajuste o
        // el reverso de una entrada cancelada pueden dejar la existencia bajo
        // cero, y ese faltante sale de aquí aunque lo libre ya no alcance. Va en
        // un solo tramo —no uno por lo que había y otro por lo que faltó—
        // porque los dos vienen del mismo origen y partirlos dejaría dos
        // asientos donde ocurrió una sola cosa.
        if ($resto > $epsilon) {
            $delLibre = $permitirNegativo ? $resto : min($resto, $libre);

            if ($delLibre > $epsilon) {
                $tramos[] = [null, $delLibre];
                $resto -= $delLibre;
            }
        }

        if ($resto <= $epsilon) {
            return $tramos === [] ? [[$obraId, $cantidad]] : $tramos;
        }

        if (! $permitirAjena) {
            throw new AsignacionAjenaException(
                almacenId: (int) $existencia->almacen_id,
                productoId: (int) ($existencia->producto_id ?? 0),
                libre: max(0.0, $libre),
                solicitado: $cantidad,
                asignadoAOtras: $asignado - (float) ($propia->cantidad ?? 0),
                descripcionProducto: (string) ($existencia->articulo?->descripcion ?? $existencia->producto?->descripcion ?? ''),
            );
        }

        foreach ($asignaciones as $asignacion) {
            if ($resto <= $epsilon) {
                break;
            }

            if ((int) $asignacion->obra_id === $obraId) {
                continue;
            }

            $tramo = min($resto, (float) $asignacion->cantidad);
            $tramos[] = [(int) $asignacion->obra_id, $tramo];
            $resto -= $tramo;
        }

        return $tramos;
    }

    /**
     * Mueve el renglón de una obra dentro de la partición.
     *
     * No borra el renglón que llega a cero: esa obra va a volver a recibir
     * material y recrearlo sería trabajo para nada. `Asignacion::vivas()` es
     * quien decide qué cuenta.
     *
     * Va sin `lockForUpdate` a propósito: el llamador ya tiene bloqueada la
     * existencia, y todo lo que toca esta partición pasa por ahí primero.
     */
    private function ajustarParticion(Existencia $existencia, int $obraId, float $delta): void
    {
        $asignacion = Asignacion::firstOrCreate([
            'existencia_id' => $existencia->getKey(),
            'obra_id' => $obraId,
        ]);

        $asignacion->forceFill([
            'cantidad' => max(0.0, (float) $asignacion->cantidad + $delta),
        ])->save();
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
     * Un asiento pedido por artículo, abriendo la existencia si hace falta.
     *
     * Gemelo de `registrarPorProducto` sin su única diferencia: aquí no hay que
     * preguntar si lleva kardex, así que nunca devuelve null. Es el camino del
     * material que abre un almacén, que todavía no tiene identidad de compra.
     */
    public function registrarPorArticulo(
        int $almacenId,
        int $articuloId,
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
        ?int $obraId = null,
        bool $permitirAjena = false,
    ): Movimiento {
        return DB::transaction(fn (): Movimiento => $this->registrar(
            $this->bloquearPorArticulo($almacenId, $articuloId),
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
            $obraId,
            $permitirAjena,
        ));
    }

    /**
     * La misma fila del saldo, pero pedida por artículo.
     *
     * Es la puerta para el material que todavía no tiene identidad de compra:
     * el que abre un almacén. Aquí no se pregunta si lleva kardex —tener
     * renglón en `alm_articulos` es llevarlo—, y por eso este camino no puede
     * devolver null como el de producto.
     */
    public function bloquearPorArticulo(int $almacenId, int $articuloId): Existencia
    {
        $existencia = Existencia::query()
            ->where('almacen_id', $almacenId)
            ->where('articulo_id', $articuloId)
            ->lockForUpdate()
            ->first();

        if ($existencia !== null) {
            return $existencia;
        }

        try {
            $articulo = Articulo::findOrFail($articuloId);

            Existencia::create([
                'almacen_id' => $almacenId,
                'articulo_id' => $articuloId,
                // Mientras conviven las dos columnas, la vieja se llena con lo
                // que el artículo tenga. Un artículo suelto la deja en null, que
                // es lo que la migración de nullable acaba de permitir.
                'producto_id' => $articulo->producto_id,
            ]);
        } catch (QueryException $e) {
            if (! $this->esChoqueDeUnique($e)) {
                throw $e;
            }
        }

        return Existencia::query()
            ->where('almacen_id', $almacenId)
            ->where('articulo_id', $articuloId)
            ->lockForUpdate()
            ->firstOrFail();
    }

    /**
     * Lo que quedaría de saldo. Sirve para validar antes de abrir la
     * transacción y poder devolver los errores de todos los renglones juntos,
     * en vez de rebotar el formulario de uno en uno.
     */
    public function disponible(int $almacenId, int $articuloId): float
    {
        return (float) Existencia::query()
            ->where('almacen_id', $almacenId)
            ->where('articulo_id', $articuloId)
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
