<?php

namespace App\Services\Costos;

use App\Contracts\Costos\Aprobable;
use App\Enums\Costos\AprobacionEstatus;
use App\Models\Costos\AprobacionDepartamento;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

/**
 * Construye la cadena de aprobaciones de un documento aprobable (Requisición,
 * SolicitudPago): por cada (nivel, aprobador) configurado en el departamento
 * para el `tipoAprobacion()` del documento, crea un registro Aprobacion
 * pendiente. Multiusuario por nivel (lógica OR: la primera firma cierra el nivel).
 *
 * El aprobable debe ser un modelo Eloquent con `departamento_id`.
 */
class ApprovalChainService
{
    /**
     * Crea las aprobaciones pendientes del aprobable. Devuelve cuántos registros
     * se crearon (0 si el departamento no tiene cadena configurada para el tipo).
     */
    public function crearCadenaAprobaciones(Aprobable&Model $aprobable): int
    {
        $cadena = $this->cadenaDepartamento($aprobable);

        foreach ($cadena as $asignacion) {
            $aprobable->cadenaAprobacion()->create([
                'nivel' => $asignacion->permiso->nivel,
                'aprobador_id' => $asignacion->aprobador_id,
                'estatus' => AprobacionEstatus::Pendiente->value,
            ]);
        }

        return $cadena->count();
    }

    /**
     * Asignaciones (AprobacionDepartamento) del departamento del aprobable para
     * su tipo de aprobación, ordenadas por nivel.
     *
     * @return Collection<int, AprobacionDepartamento>
     */
    public function cadenaDepartamento(Aprobable&Model $aprobable): Collection
    {
        return AprobacionDepartamento::query()
            ->where('departamento_id', $aprobable->departamento_id)
            ->whereHas('permiso', fn ($q) => $q->where('tipo_aprobacion', $aprobable->tipoAprobacion()))
            ->with('permiso')
            ->get()
            ->sortBy('permiso.nivel')
            ->values();
    }
}
