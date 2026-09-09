<?php

namespace App\Services\Alm;

use App\Enums\Alm\ActivoEstatus;
use App\Enums\Alm\MovimientoTipo;
use App\Models\Alm\Activo;
use App\Models\Alm\Almacen;
use App\Models\Alm\Articulo;
use App\Models\Alm\Existencia;
use App\Models\Alm\Movimiento;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;

/**
 * El único que crea, mueve o da de baja una pieza.
 *
 * Existe para sostener el invariante que hace confiable a Existencias: para un
 * artículo marcado «por pieza», la cantidad del saldo es exactamente el número
 * de piezas vigentes en ese almacén. Cada alta emite +1 al kardex y cada baja
 * −1, **en la misma transacción** — un alta que no emitiera dejaría dos
 * inventarios que nadie sabe reconciliar.
 *
 * El préstamo no pasa por aquí en lo que toca al saldo: cambia el estatus de la
 * pieza y nada más. Una pulidora prestada sigue siendo del almacén.
 *
 * También da de alta y de baja los activos **por cantidad** —los que el
 * catálogo marca como activo sin control por pieza—. Ahí no hay padrón: el
 * renglón de existencia es el registro, y el alta es un solo asiento por N.
 */
class RegistradorPiezas
{
    public function __construct(private readonly AlmacenLedger $ledger) {}

    /**
     * Alta de un activo sin serie: N unidades en un solo asiento. Es la misma
     * entrada que haría una carga inicial, con el motivo dicho.
     */
    public function altaPorCantidad(
        Articulo $articulo,
        Almacen $almacen,
        float $cantidad,
        ?float $costo = null,
        ?int $ubicacionId = null,
        ?string $userId = null,
        ?string $observaciones = null,
    ): Movimiento {
        if (! $articulo->esActivoPorCantidad()) {
            throw new InvalidArgumentException("El artículo {$articulo->codigo} no es un activo por cantidad.");
        }

        return $this->ledger->registrarPorArticulo(
            almacenId: $almacen->id,
            articuloId: $articulo->id,
            tipo: MovimientoTipo::Entrada,
            cantidad: $cantidad,
            costoUnitario: $costo !== null && $costo > 0 ? $costo : null,
            referencia: 'Alta de activo',
            ubicacionId: $ubicacionId,
            observaciones: $observaciones === null || $observaciones === ''
                ? 'Alta de activo por cantidad'
                : "Alta de activo por cantidad · {$observaciones}",
            userId: $userId,
        );
    }

    /**
     * Baja de N unidades de un activo sin serie. Descarga al costo promedio:
     * sin serie no hay «esta pieza» que valga distinto de las demás.
     */
    public function bajaPorCantidad(Existencia $existencia, float $cantidad, string $motivo, ?string $userId = null): Movimiento
    {
        $articulo = $existencia->articulo;

        if ($articulo === null || ! $articulo->esActivoPorCantidad()) {
            throw new InvalidArgumentException('Ese renglón no es de un activo por cantidad.');
        }

        return $this->ledger->registrarPorArticulo(
            almacenId: $existencia->almacen_id,
            articuloId: $existencia->articulo_id,
            tipo: MovimientoTipo::Salida,
            cantidad: -abs($cantidad),
            referencia: 'Baja de activo',
            observaciones: "Baja de activo por cantidad · {$motivo}",
            userId: $userId,
        );
    }

    /**
     * Da de alta varias piezas del mismo artículo de un golpe: así es como se
     * carga el almacén el primer día, y renglón por renglón nadie lo termina.
     *
     * @param  list<array<string, mixed>>  $piezas
     * @return list<Activo>
     */
    public function alta(
        Articulo $articulo,
        Almacen $almacen,
        array $piezas,
        ?int $ubicacionId = null,
        ?string $userId = null,
    ): array {
        return DB::transaction(function () use ($articulo, $almacen, $piezas, $ubicacionId, $userId): array {
            $creadas = [];

            foreach ($piezas as $datos) {
                $costo = (float) ($datos['costo'] ?? 0);

                $activo = Activo::create([
                    'articulo_id' => $articulo->id,
                    // Mientras conviven las dos columnas. Un artículo suelto la
                    // deja en null, que es lo que abre un almacén nuevo.
                    'producto_id' => $articulo->producto_id,
                    'no_serie' => trim((string) $datos['no_serie']),
                    // El de la pieza, no el del artículo: es lo que permite
                    // saber cuál de las catorce pulidoras volvió del préstamo.
                    'codigo_barras' => $this->codigoDeBarras($articulo, $datos),
                    'marca' => $datos['marca'] ?? null,
                    'modelo' => $datos['modelo'] ?? null,
                    'id_mantenimiento' => $datos['id_mantenimiento'] ?? null,
                    'almacen_id' => $almacen->id,
                    'ubicacion_id' => $ubicacionId,
                    'costo' => $costo,
                    'estatus' => ActivoEstatus::Disponible,
                    'condicion' => $datos['condicion'] ?? null,
                    'observaciones' => $datos['observaciones'] ?? null,
                ]);

                $this->ledger->registrarPorArticulo(
                    almacenId: $almacen->id,
                    articuloId: $articulo->id,
                    tipo: MovimientoTipo::Entrada,
                    cantidad: 1,
                    // Al costo de *esta* pieza: es lo que hace que el promedio
                    // del renglón sea el promedio real de sus piezas.
                    costoUnitario: $costo > 0 ? $costo : null,
                    documento: $activo,
                    referencia: $activo->no_serie,
                    ubicacionId: $ubicacionId,
                    activoId: $activo->id,
                    observaciones: 'Alta de pieza',
                    userId: $userId,
                );

                $creadas[] = $activo;
            }

            return $creadas;
        });
    }

    /**
     * Retira la pieza del almacén. Descarga al costo con el que entró, no al
     * promedio: si no, dar de baja la más cara dejaría valor que no existe.
     */
    public function baja(Activo $activo, string $motivo, ?string $userId = null): Activo
    {
        return DB::transaction(function () use ($activo, $motivo, $userId): Activo {
            if ($activo->estatus === ActivoEstatus::Baja) {
                return $activo;
            }

            $this->ledger->registrarPorArticulo(
                almacenId: $activo->almacen_id,
                articuloId: $activo->articulo_id,
                tipo: MovimientoTipo::Salida,
                cantidad: -1,
                costoUnitario: (float) $activo->costo ?: null,
                documento: $activo,
                referencia: $activo->no_serie,
                activoId: $activo->id,
                observaciones: "Baja de pieza · {$motivo}",
                userId: $userId,
                // Una pieza que se dio de baja se fue de verdad, aunque el saldo
                // estuviera descuadrado por otra razón: bloquear la baja dejaría
                // el padrón mintiendo sobre lo que hay en el anaquel.
                permitirNegativo: true,
            );

            $activo->update([
                'estatus' => ActivoEstatus::Baja,
                'observaciones' => $motivo,
            ]);

            return $activo;
        });
    }

    /**
     * Cambia la pieza de almacén, dejando los dos asientos del kardex.
     *
     * Se usa desde la recepción de una transferencia: el material y su identidad
     * tienen que llegar juntos, o el padrón diría que la pulidora sigue en
     * planta cuando ya está en la obra.
     */
    public function mover(Activo $activo, Almacen $destino, ?string $referencia = null, ?string $userId = null): Activo
    {
        return DB::transaction(function () use ($activo, $destino, $referencia, $userId): Activo {
            $origen = $activo->almacen_id;

            if ((int) $origen === (int) $destino->id) {
                return $activo;
            }

            $costo = (float) $activo->costo ?: null;

            $this->ledger->registrarPorArticulo(
                almacenId: $origen,
                articuloId: $activo->articulo_id,
                tipo: MovimientoTipo::TransferenciaSalida,
                cantidad: -1,
                costoUnitario: $costo,
                documento: $activo,
                referencia: $referencia ?? $activo->no_serie,
                activoId: $activo->id,
                userId: $userId,
            );

            // La ubicación no viaja: el «Rack A-1» del origen no existe en el
            // destino, así que la pieza llega sin acomodar.
            $activo->update(['almacen_id' => $destino->id, 'ubicacion_id' => null]);

            $this->ledger->registrarPorArticulo(
                almacenId: $destino->id,
                articuloId: $activo->articulo_id,
                tipo: MovimientoTipo::TransferenciaEntrada,
                cantidad: 1,
                costoUnitario: $costo,
                documento: $activo,
                referencia: $referencia ?? $activo->no_serie,
                activoId: $activo->id,
                userId: $userId,
            );

            return $activo;
        });
    }

    /**
     * Nace igual a la serie cuando trae algo escaneable, y si no, con el código
     * del artículo más un consecutivo: un VIN con letras raras no lo lee el
     * lector, y la pieza igual necesita etiqueta.
     *
     * @param  array<string, mixed>  $datos
     */
    private function codigoDeBarras(Articulo $articulo, array $datos): ?string
    {
        $capturado = trim((string) ($datos['codigo_barras'] ?? ''));

        if ($capturado !== '') {
            return $capturado;
        }

        $serie = trim((string) $datos['no_serie']);

        if (Str::of($serie)->isMatch('/^[A-Z0-9\-. $\/+%]+$/i')) {
            return $serie;
        }

        $consecutivo = Activo::where('articulo_id', $articulo->id)->count() + 1;

        return sprintf('%s-%02d', $articulo->codigo ?? 'PZA', $consecutivo);
    }
}
