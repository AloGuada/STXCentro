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

        // Un nivel marcado `omitir_si_presupuesto_reservado` se salta cuando el
        // documento tiene presupuesto reservado vigente (apartado sin vencer).
        // Se evalúa por nivel, de forma independiente.
        $reservado = $aprobable->tienePresupuestoReservado();

        $creados = 0;
        foreach ($cadena as $asignacion) {
            if ($reservado && $asignacion->permiso->omitir_si_presupuesto_reservado) {
                continue;
            }

            $aprobable->cadenaAprobacion()->create([
                'nivel' => $asignacion->permiso->nivel,
                'aprobador_id' => $asignacion->aprobador_id,
                'estatus' => AprobacionEstatus::Pendiente->value,
            ]);
            $creados++;
        }

        return $creados;
    }

    /**
     * ¿El departamento tiene al menos un nivel configurado para el tipo del
     * aprobable? Sirve para distinguir "todos los niveles se saltaron" (auto
     * aprobar) de "no hay cadena configurada" (queda pendiente).
     */
    public function tieneCadenaConfigurada(Aprobable&Model $aprobable): bool
    {
        return $this->cadenaDepartamento($aprobable)->isNotEmpty();
    }

    /**
     * ¿Se saltó al menos un nivel configurado? Es decir, hay niveles en la
     * configuración del departamento sin ningún registro de Aprobacion en el
     * documento. Lo usa la liberación para reevaluar si el salto sigue válido.
     */
    public function huboNivelesSaltados(Aprobable&Model $aprobable): bool
    {
        $configurados = $this->cadenaDepartamento($aprobable)
            ->pluck('permiso.nivel')
            ->map(fn ($n) => (int) $n)
            ->unique();

        $conRegistro = $aprobable->cadenaAprobacion()
            ->pluck('nivel')
            ->map(fn ($n) => (int) $n)
            ->unique();

        return $configurados->diff($conRegistro)->isNotEmpty();
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
