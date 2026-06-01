<?php

namespace Database\Seeders;

use App\Models\Costos\TipoSolicitud;
use App\Services\Costos\SolicitudPagoDesdeOrdenCompra;
use Illuminate\Database\Seeder;

class CostosTipoSolicitudSeeder extends Seeder
{
    /**
     * Tipo de solicitud usado por las solicitudes de pago generadas
     * automáticamente al liberar una OC de contado. Idempotente: no toca los
     * tipos capturados manualmente.
     */
    public function run(): void
    {
        TipoSolicitud::firstOrCreate(
            ['titulo' => SolicitudPagoDesdeOrdenCompra::TIPO_TITULO],
            [
                'descripcion' => 'Generada automáticamente al liberar una OC de contado.',
                'rubros' => true,
            ],
        );
    }
}
