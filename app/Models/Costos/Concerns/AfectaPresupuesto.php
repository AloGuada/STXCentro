<?php

namespace App\Models\Costos\Concerns;

use App\Models\Costos\ObraRubro;
use App\Services\Costos\ValidadorPresupuesto;
use Illuminate\Support\Facades\Auth;

/**
 * Aplica impacto presupuestal permanente: por cada detalle incrementa el
 * acumulado del obra_rubro (validando sobregiro) y registra un RubroAfectado
 * con estatus 'aplicado'. El modelo que use el trait debe exponer las
 * relaciones `detalles` y `rubrosAfectados`, y definir cómo describir la
 * afectación de cada detalle.
 */
trait AfectaPresupuesto
{
    public function aplicarImpactoPresupuestal(?string $userId = null): void
    {
        $userId = $userId ?? Auth::id();
        $validador = app(ValidadorPresupuesto::class);

        foreach ($this->detalles as $detalle) {
            $obraRubro = ObraRubro::find($detalle->obra_rubro_id);
            $validador->validar($obraRubro, (float) $detalle->subtotal, $this);

            ObraRubro::where('id', $detalle->obra_rubro_id)
                ->increment('acumulado', (float) $detalle->subtotal);

            $obraRubro->refresh();
            $disponible = $obraRubro->disponible;

            $this->rubrosAfectados()->create([
                'obra_rubro_id' => $detalle->obra_rubro_id,
                'monto' => $detalle->subtotal,
                'sobre_giro' => $disponible < 0,
                'descripcion' => $this->descripcionAfectacion($detalle, $obraRubro),
                'tipo_movimiento' => 'cargo',
                'estatus' => 'aplicado',
                'usuario_aplica_id' => $userId,
                'fecha_aplicacion' => now(),
            ]);
        }
    }

    abstract protected function descripcionAfectacion(object $detalle, ObraRubro $obraRubro): ?string;
}
