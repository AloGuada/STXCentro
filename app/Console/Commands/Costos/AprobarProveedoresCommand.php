<?php

namespace App\Console\Commands\Costos;

use App\Enums\ProveedorEstatus;
use App\Models\Proveedor;
use Illuminate\Console\Command;

/**
 * Aprueba (activa) en bloque todos los proveedores que no estén ya activos:
 * estatus -> activo, activo -> true y sella la validación. Útil para sembrar
 * datos o regularizar altas pendientes. No reactiva proveedores rechazados a
 * menos que se pase --incluir-rechazados.
 */
class AprobarProveedoresCommand extends Command
{
    protected $signature = 'costos:aprobar-proveedores {--force : Omitir confirmación} {--incluir-rechazados : Aprobar también los rechazados}';

    protected $description = 'Aprueba (activa) en bloque todos los proveedores pendientes de validación.';

    public function handle(): int
    {
        $query = Proveedor::query()->where('estatus', '!=', ProveedorEstatus::Activo->value);

        if (! $this->option('incluir-rechazados')) {
            $query->where('estatus', '!=', ProveedorEstatus::Rechazado->value);
        }

        $total = (clone $query)->count();

        if ($total === 0) {
            $this->info('No hay proveedores por aprobar.');

            return self::SUCCESS;
        }

        if (! $this->option('force') && ! $this->confirm("Se aprobarán {$total} proveedores. ¿Continuar?")) {
            $this->info('Cancelado.');

            return self::SUCCESS;
        }

        $aprobados = $query->update([
            'estatus' => ProveedorEstatus::Activo->value,
            'activo' => true,
            'validado_at' => now(),
            'observacion_validacion' => 'Aprobación masiva por comando.',
        ]);

        $this->info("Proveedores aprobados: {$aprobados}.");

        return self::SUCCESS;
    }
}
