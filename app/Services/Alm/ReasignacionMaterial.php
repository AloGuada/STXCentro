<?php

namespace App\Services\Alm;

use App\Enums\Alm\MovimientoTipo;
use App\Models\Alm\Asignacion;
use App\Models\Alm\Existencia;
use App\Models\Obra;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use RuntimeException;

/**
 * Pasa material de una obra a otra —o de lo libre a una obra, y de vuelta— sin
 * moverlo de bodega.
 *
 * Cambia de dueño, no de lugar: el saldo de la existencia queda exactamente
 * igual. Deja **dos asientos** de suma cero, uno descargando el origen y otro
 * cargando el destino, y por eso el kardex sigue explicando la partición
 * completa sin necesidad de un segundo libro. Los totales de la pantalla del
 * kardex excluyen este tipo a propósito: sumarlo a entradas y salidas inflaría
 * los dos lados con material que nunca se movió.
 *
 * Es el gemelo material de `App\Services\Costos\ReasignacionCentroCostos`, que
 * hace lo propio con el dinero. **No mueve el gasto**: si el material se compró
 * contra el presupuesto de una obra, el cargo ya se hizo al recibirlo y
 * corregirlo toca documentos aprobados o pagados. Son dos actos distintos y
 * éste sólo hace el primero.
 */
class ReasignacionMaterial
{
    public function __construct(private readonly AlmacenLedger $ledger) {}

    /**
     * @param  int|null  $deObraId  de quién sale; `null` = de lo libre
     * @param  int|null  $aObraId  a quién va; `null` = se suelta a lo libre
     *
     * @throws InvalidArgumentException|RuntimeException
     */
    public function reasignar(
        Existencia $existencia,
        ?int $deObraId,
        ?int $aObraId,
        float $cantidad,
        string $motivo,
        ?string $userId = null,
    ): void {
        if ($cantidad <= 0) {
            throw new InvalidArgumentException('La cantidad a reasignar tiene que ser mayor que cero.');
        }

        if ($deObraId === $aObraId) {
            throw new InvalidArgumentException('El origen y el destino son el mismo: no hay nada que reasignar.');
        }

        DB::transaction(function () use ($existencia, $deObraId, $aObraId, $cantidad, $motivo, $userId): void {
            // Se relee bloqueada aunque venga cargada: entre que la pantalla
            // pintó el desglose y llegó este POST, una salida pudo consumir lo
            // que se está repartiendo.
            $existencia = Existencia::query()->whereKey($existencia->getKey())->lockForUpdate()->firstOrFail();

            $this->verificarOrigen($existencia, $deObraId, $cantidad);

            $observaciones = $this->narrar($deObraId, $aObraId, $motivo);

            // El costo viaja con el material: la descarga sale al promedio
            // vigente y la carga entra al mismo, así el valor del inventario
            // termina donde empezó. Sin sellarlo, la carga movería el promedio.
            $promedio = (float) $existencia->costo_promedio;

            $this->ledger->registrar(
                existencia: $existencia,
                tipo: MovimientoTipo::Reasignacion,
                cantidad: -$cantidad,
                observaciones: $observaciones,
                userId: $userId,
                obraId: $deObraId,
            );

            $this->ledger->registrar(
                existencia: $existencia,
                tipo: MovimientoTipo::Reasignacion,
                cantidad: $cantidad,
                costoUnitario: $promedio > 0 ? $promedio : null,
                observaciones: $observaciones,
                userId: $userId,
                obraId: $aObraId,
            );
        });
    }

    /**
     * Que el origen tenga de verdad lo que se quiere mover.
     *
     * Sin esto el ledger haría lo suyo —consumir lo de la obra y luego lo
     * libre— y una reasignación de 30 sobre una obra que sólo tiene 20 acabaría
     * llevándose 10 de lo libre en silencio. Aquí eso no es un reparto, es un
     * error de captura.
     */
    private function verificarOrigen(Existencia $existencia, ?int $deObraId, float $cantidad): void
    {
        $epsilon = (float) config('costos.epsilon_cantidad');

        $enOrigen = $deObraId === null
            ? $existencia->libre()
            : (float) Asignacion::query()
                ->where('existencia_id', $existencia->getKey())
                ->where('obra_id', $deObraId)
                ->value('cantidad');

        if ($cantidad - $enOrigen > $epsilon) {
            throw new RuntimeException(sprintf(
                '%s sólo tiene %s: no se pueden reasignar %s.',
                $deObraId === null ? 'Lo libre' : 'Esa obra',
                rtrim(rtrim(number_format($enOrigen, 4, '.', ''), '0'), '.') ?: '0',
                rtrim(rtrim(number_format($cantidad, 4, '.', ''), '0'), '.') ?: '0',
            ));
        }
    }

    /**
     * El rastro que queda en los dos asientos. Va en `observaciones` y no en
     * `referencia` porque no hay documento: la reasignación es un acto, no un
     * papel con folio.
     */
    private function narrar(?int $deObraId, ?int $aObraId, string $motivo): string
    {
        $nombre = fn (?int $id): string => $id === null
            ? 'libre'
            : (string) (Obra::whereKey($id)->value('no') ?? "obra #{$id}");

        return sprintf('Reasignación · de %s a %s · %s', $nombre($deObraId), $nombre($aObraId), $motivo);
    }
}
