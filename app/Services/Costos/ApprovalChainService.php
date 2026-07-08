<?php

namespace App\Services\Costos;

use App\Contracts\Costos\Aprobable;
use App\Enums\Costos\AprobacionEstatus;
use App\Models\Costos\AprobacionDepartamento;
use App\Models\Costos\OmitirRubro;
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

        // El documento puede saltarse el primer nivel de la cadena (la
        // verificación de costos). Se descartan todas las asignaciones de ese
        // primer nivel antes de armar la cadena.
        if ($aprobable->saltaVerificacionCostos() && $cadena->isNotEmpty()) {
            $primerNivel = $cadena->first()->permiso->nivel;
            $cadena = $cadena->reject(fn ($asignacion) => $asignacion->permiso->nivel === $primerNivel)->values();
        }

        // Una asignación (departamento + nivel) marcada
        // `omitir_si_presupuesto_reservado` se salta cuando el documento tiene
        // presupuesto reservado vigente (apartado sin vencer). Se evalúa por
        // nivel, de forma independiente y por departamento.
        $reservado = $aprobable->tienePresupuestoReservado();

        // Centros de costo del documento y, por nivel, los rubros permitidos
        // para saltar. Lista vacía = aplica a todos; con lista, solo se salta
        // cuando TODOS los centros del documento están dentro de ella.
        $centrosDoc = $reservado ? $aprobable->centrosDeCostoIds() : [];
        $rubrosPermitidos = $reservado
            ? OmitirRubro::query()
                ->where('departamento_id', $aprobable->departamento_id)
                ->get()
                ->groupBy('permiso_id')
                ->map(fn ($grupo) => $grupo->pluck('rubro_id')->map(fn ($id): int => (int) $id)->all())
            : collect();

        $creados = 0;
        foreach ($cadena as $asignacion) {
            if ($reservado && $asignacion->omitir_si_presupuesto_reservado) {
                $permitidos = $rubrosPermitidos->get($asignacion->permiso_id, []);
                // Sin lista → aplica a todos. Con lista → solo si el documento
                // no toca ningún centro fuera de ella.
                $aplicaSalto = empty($permitidos) || empty(array_diff($centrosDoc, $permitidos));

                if ($aplicaSalto) {
                    continue;
                }
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
