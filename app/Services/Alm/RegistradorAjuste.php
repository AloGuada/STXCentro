<?php

namespace App\Services\Alm;

use App\Enums\Alm\MovimientoTipo;
use App\Models\Alm\Ajuste;
use App\Models\Costos\Producto;
use Illuminate\Support\Facades\DB;

/**
 * Graba un ajuste y lo aplica al kardex en la misma transacción.
 *
 * Vive fuera del controlador porque el cierre de un conteo cíclico va a levantar
 * exactamente el mismo documento: el conteo no toca el saldo por su cuenta,
 * genera un ajuste.
 */
class RegistradorAjuste
{
    public function __construct(private readonly AlmacenLedger $ledger) {}

    /**
     * El saldo del sistema se lee **con la fila ya bloqueada**, no antes: entre
     * que el almacenista abrió la pantalla y guardó pudo entrar una recepción, y
     * ajustar contra un saldo viejo inventaría una diferencia que no existe.
     *
     * Cada renglón viene con `articulo_id` o con `producto_id`. Lo primero es
     * lo nuevo y lo que queda; lo segundo es el camino que sigue vivo mientras
     * las dos columnas convivan.
     *
     * @param  array<string, mixed>  $cabecera
     * @param  list<array<string, mixed>>  $renglones
     */
    public function registrar(array $cabecera, array $renglones, ?string $userId = null): Ajuste
    {
        return DB::transaction(function () use ($cabecera, $renglones, $userId): Ajuste {
            $ajuste = Ajuste::create($cabecera);

            foreach ($renglones as $renglon) {
                $articuloId = isset($renglon['articulo_id']) ? (int) $renglon['articulo_id'] : null;
                $productoId = isset($renglon['producto_id']) ? (int) $renglon['producto_id'] : null;
                $contada = (float) $renglon['cantidad_contada'];
                $costoUnitario = isset($renglon['costo_unitario']) && $renglon['costo_unitario'] !== null
                    ? (float) $renglon['costo_unitario']
                    : null;

                // Por artículo no se pregunta nada: tener renglón en el catálogo
                // de Almacén es llevar kardex. Por producto sí, y se pregunta
                // antes de bloquear porque `bloquear()` crea la fila si no
                // existe, y algo sin kardex no debe estrenar existencia sólo
                // porque alguien lo metió en la hoja.
                $llevaKardex = $articuloId !== null || $this->llevaKardex($productoId);

                $existencia = match (true) {
                    $articuloId !== null => $this->ledger->bloquearPorArticulo($ajuste->almacen_id, $articuloId),
                    $llevaKardex => $this->ledger->bloquear($ajuste->almacen_id, $productoId),
                    default => null,
                };

                $sistema = $existencia === null ? 0.0 : (float) $existencia->cantidad;
                $diferencia = $llevaKardex ? $contada - $sistema : 0.0;

                $ajuste->detalles()->create([
                    'articulo_id' => $articuloId,
                    'producto_id' => $productoId,
                    'cantidad_contada' => $contada,
                    'cantidad_sistema' => $sistema,
                    'diferencia' => $diferencia,
                    'costo_unitario' => $costoUnitario,
                    'observaciones' => $renglon['observaciones'] ?? null,
                ]);

                // Contar exacto es información —queda el renglón, con su cero—,
                // pero no es un movimiento: el kardex sólo lista lo que cambia
                // el saldo.
                if ($existencia === null || abs($diferencia) <= (float) config('costos.epsilon_cantidad')) {
                    continue;
                }

                $this->ledger->registrar(
                    existencia: $existencia,
                    tipo: MovimientoTipo::Ajuste,
                    cantidad: $diferencia,
                    // Sólo lo que aparece se valúa: lo que falta sale al costo
                    // promedio con el que había entrado.
                    costoUnitario: $diferencia > 0 ? $costoUnitario : null,
                    documento: $ajuste,
                    referencia: $ajuste->folio,
                    observaciones: $renglon['observaciones'] ?? null,
                    userId: $userId,
                    // El único documento al que el ledger le permite dejar el
                    // saldo en negativo: si se contó menos que cero, eso es
                    // justo lo que hay que poder registrar y explicar.
                    permitirNegativo: true,
                );
            }

            return $ajuste;
        });
    }

    private function llevaKardex(?int $productoId): bool
    {
        if ($productoId === null) {
            return false;
        }

        return Producto::query()
            ->whereKey($productoId)
            ->where('controla_inventario', true)
            ->exists();
    }
}
