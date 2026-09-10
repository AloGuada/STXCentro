<?php

namespace App\Exports\Costos;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;

/**
 * Versión en Excel del reporte PDF de la bandeja de solicitudes de pago: una
 * hoja por cada tabla del formato impreso (solicitudes y requisiciones). Recibe
 * las colecciones ya filtradas por el controlador para respetar la visibilidad
 * del usuario.
 */
class SolicitudesRequisicionesExport implements WithMultipleSheets
{
    /**
     * @param  Collection<int, \App\Models\Costos\SolicitudPago>  $solicitudes
     * @param  Collection<int, \App\Models\Costos\Requisicion>  $requisiciones
     */
    public function __construct(private Collection $solicitudes, private Collection $requisiciones) {}

    /**
     * @return array<int, object>
     */
    public function sheets(): array
    {
        return [
            new SolicitudesPagoSheet($this->solicitudes),
            new RequisicionesSheet($this->requisiciones),
        ];
    }
}
