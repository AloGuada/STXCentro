<?php

namespace App\Services\Costos;

use App\Contracts\Costos\Aprobable;
use App\Enums\Costos\AprobacionEstatus;
use App\Models\Costos\Aprobacion;

class AprobacionService
{
    /**
     * Registra la firma de una aprobación: la marca como aprobada, cancela las
     * demás del mismo nivel (lógica OR) y, si ya no quedan pendientes, dispara
     * el callback de aprobación completa del documento.
     */
    public function aprobar(Aprobacion $aprobacion, string $observaciones, ?string $ip = null, ?string $hostname = null): void
    {
        $aprobacion->update([
            'fecha_respuesta' => now(),
            'observaciones' => $observaciones,
            'ip' => $ip,
            'hostname' => $hostname,
        ]);
        $aprobacion->transitionTo(AprobacionEstatus::Aprobada);

        $aprobable = $aprobacion->aprobable;
        $aprobable->cadenaAprobacion()
            ->where('nivel', $aprobacion->nivel)
            ->where('id', '!=', $aprobacion->id)
            ->where('estatus', 'pendiente')
            ->update([
                'estatus' => 'cancelada',
                'fecha_respuesta' => now(),
            ]);

        $quedanPendientes = $aprobable->cadenaAprobacion()
            ->where('estatus', 'pendiente')
            ->exists();

        if (! $quedanPendientes && $aprobable instanceof Aprobable) {
            $aprobable->onAprobacionCompleta(auth()->id());
        }
    }
}
