<?php

namespace App\Services\Costos;

use App\Models\Costos\Permiso;
use Illuminate\Support\Collection;

/**
 * Construye las columnas de firma para los PDF de costos (solicitud de pago y
 * comparativo de requisición/OC).
 *
 * Solo incluye los niveles de firma que realmente aplican al documento: los del
 * tipo de aprobación correspondiente y asignados al departamento del documento
 * (un permiso por nivel), intersectados con las aprobaciones generadas en la
 * cadena. Así el PDF no pinta espacios de firma de otros tipos de documento ni
 * de departamentos ajenos.
 */
class FirmasPdfBuilder
{
    /**
     * @param  Collection<int, object>  $aprobaciones  Aprobaciones del documento (con `aprobador` cargado).
     * @return Collection<int, object{permiso: Permiso, aprobador: mixed, aprobada: bool, fecha: ?string}>
     */
    public function build(string $tipoAprobacion, ?int $departamentoId, Collection $aprobaciones): Collection
    {
        $aprobacionesPorNivel = $aprobaciones->groupBy('nivel');

        return Permiso::query()
            ->where('tipo_aprobacion', $tipoAprobacion)
            ->whereHas('aprobacionesDepartamento', fn ($q) => $q->where('departamento_id', $departamentoId))
            ->orderBy('nivel')
            ->get()
            ->unique('nivel')
            ->filter(fn (Permiso $permiso) => $aprobacionesPorNivel->has($permiso->nivel))
            ->map(function (Permiso $permiso) use ($aprobacionesPorNivel) {
                $aprobada = $aprobacionesPorNivel->get($permiso->nivel)->firstWhere('estatus', 'aprobada');

                return (object) [
                    'permiso' => $permiso,
                    'aprobador' => $aprobada?->aprobador,
                    'aprobada' => $aprobada !== null,
                    'fecha' => $aprobada?->fecha_respuesta?->format('d/m/Y H:i'),
                ];
            })
            ->values();
    }
}
