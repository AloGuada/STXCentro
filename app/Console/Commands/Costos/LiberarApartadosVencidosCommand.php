<?php

namespace App\Console\Commands\Costos;

use App\Services\Costos\ApartadoPresupuestal;
use Illuminate\Console\Command;

class LiberarApartadosVencidosCommand extends Command
{
    protected $signature = 'costos:liberar-apartados-vencidos';

    protected $description = 'Libera los apartados temporales de presupuesto cuya fecha apartado_hasta ya pasó. Decrementa el acumulado del rubro y marca el RubroAfectado como Vencido.';

    public function handle(ApartadoPresupuestal $servicio): int
    {
        $count = $servicio->liberarVencidos();

        if ($count === 0) {
            $this->info('No hay apartados vencidos para liberar.');
        } else {
            $this->info("Liberados {$count} apartado(s) vencido(s).");
        }

        return self::SUCCESS;
    }
}
