<?php

namespace App\Models\Concerns;

use App\Models\Costos\Cancelacion;
use Illuminate\Database\Eloquent\Relations\MorphOne;

/**
 * Registra la cancelacion de un modelo (quien cancelo, cuando, por que).
 * La transicion a estatus cancelado sigue pasando por HasStateMachine;
 * este trait solo persiste la bitacora polimorfica en costos_cancelaciones.
 */
trait HasCancelacion
{
    public function cancelacion(): MorphOne
    {
        return $this->morphOne(Cancelacion::class, 'cancelable');
    }

    public function registrarCancelacion(string $motivo, string $usuarioId): Cancelacion
    {
        return $this->cancelacion()->create([
            'motivo' => $motivo,
            'usuario_id' => $usuarioId,
            'cancelado_at' => now(),
        ]);
    }
}
