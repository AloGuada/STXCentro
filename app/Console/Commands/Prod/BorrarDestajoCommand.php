<?php

namespace App\Console\Commands\Prod;

use App\Models\Prod\Asistencia;
use App\Models\Prod\Destajo;
use App\Models\Prod\Liquidacion;
use App\Models\Prod\PagoExtra;
use App\Models\Prod\Registro;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Elimina un destajo semanal y todo lo que cuelga de él: liquidaciones (con su
 * detalle y su reparto por empleado), pagos extra y asistencia.
 *
 * Los registros de producción NO cuelgan del destajo por llave foránea: se le
 * asocian por rango de fechas, así que sólo se borran si se pide con
 * --con-registros. Sin esa bandera la captura de producción de la semana
 * sobrevive y el destajo se puede volver a generar.
 *
 * Por defecto corre en modo dry-run (sólo reporta). Requiere --force para borrar.
 */
class BorrarDestajoCommand extends Command
{
    protected $signature = 'prod:borrar-destajo
        {destajo : Id del destajo, o año/semana (ej. 2026/31)}
        {--con-registros : Borra también los registros de producción capturados en el rango de fechas}
        {--force : Ejecuta el borrado; sin esta bandera sólo muestra el dry-run}';

    protected $description = 'Elimina un destajo y todo lo relacionado (liquidaciones, pagos extra, asistencia y opcionalmente registros)';

    public function handle(): int
    {
        $destajo = $this->resolver((string) $this->argument('destajo'));

        if ($destajo === null) {
            return self::FAILURE;
        }

        $plan = $this->construirPlan($destajo);
        $total = array_sum($plan);

        $this->info(sprintf(
            'Destajo %d/%d (id %d, %s a %s, %s)',
            $destajo->anio,
            $destajo->semana,
            $destajo->id,
            $destajo->fecha_inicio->toDateString(),
            $destajo->fecha_fin->toDateString(),
            $destajo->cerrado ? 'CERRADO' : 'abierto',
        ));
        $this->newLine();
        $this->table(
            ['Tabla / entidad', 'Registros'],
            collect($plan)->map(fn (int $n, string $t): array => [$t, (string) $n])->values()->all(),
        );

        if (! $this->option('con-registros')) {
            $enRango = $this->registrosEnRango($destajo)->count();

            if ($enRango > 0) {
                $this->newLine();
                $this->line("Se conservan {$enRango} registros de producción de esa semana (usa --con-registros para borrarlos también).");
            }
        }

        if (! $this->option('force')) {
            $this->newLine();
            $this->warn("DRY-RUN: no se borró nada. Se eliminarían {$total} registros.");
            $this->line('Vuelve a ejecutar con --force para borrar definitivamente.');

            return self::SUCCESS;
        }

        if ($destajo->cerrado && ! $this->confirmarCerrado($destajo)) {
            return self::FAILURE;
        }

        DB::transaction(function () use ($destajo): void {
            // Los hijos se borran explícitamente: sólo `prod_asistencias` tiene
            // ON DELETE CASCADE, liquidaciones y pagos extra son RESTRICT.
            // La liquidación sí cascadea a su detalle y a su reparto por empleado.
            Liquidacion::where('destajo_id', $destajo->id)->delete();
            PagoExtra::where('destajo_id', $destajo->id)->delete();
            Asistencia::where('destajo_id', $destajo->id)->delete();

            if ($this->option('con-registros')) {
                $this->registrosEnRango($destajo)->delete();
            }

            Destajo::whereKey($destajo->id)->delete();
        });

        $this->newLine();
        $this->info("Destajo {$destajo->anio}/{$destajo->semana} y {$total} registros relacionados eliminados.");

        return self::SUCCESS;
    }

    /** Acepta el id directo o el par año/semana, que es como lo nombra la gente. */
    private function resolver(string $identificador): ?Destajo
    {
        if (str_contains($identificador, '/')) {
            [$anio, $semana] = array_map('intval', explode('/', $identificador, 2));

            $destajo = Destajo::where('anio', $anio)->where('semana', $semana)->first();

            if ($destajo === null) {
                $this->error("No existe el destajo {$anio}/{$semana}.");
            }

            return $destajo;
        }

        $destajo = Destajo::find((int) $identificador);

        if ($destajo === null) {
            $this->error("No existe ningún destajo con id '{$identificador}'.");
        }

        return $destajo;
    }

    /**
     * @return array<string, int>
     */
    private function construirPlan(Destajo $destajo): array
    {
        $liquidaciones = Liquidacion::where('destajo_id', $destajo->id)->pluck('id');

        $plan = [
            'Destajo' => 1,
            'Liquidaciones' => $liquidaciones->count(),
            'Detalle de liquidación' => DB::table('prod_liquidacion_detalle')->whereIn('liquidacion_id', $liquidaciones)->count(),
            'Liquidación por empleado' => DB::table('prod_liquidacion_empleados')->whereIn('liquidacion_id', $liquidaciones)->count(),
            'Pagos extra' => PagoExtra::where('destajo_id', $destajo->id)->count(),
            'Asistencias' => Asistencia::where('destajo_id', $destajo->id)->count(),
        ];

        if ($this->option('con-registros')) {
            $plan['Registros de producción'] = $this->registrosEnRango($destajo)->count();
        }

        return array_filter($plan);
    }

    /**
     * Los registros de la semana. Se identifican por fecha porque no guardan
     * `destajo_id`.
     *
     * @return \Illuminate\Database\Eloquent\Builder<Registro>
     */
    private function registrosEnRango(Destajo $destajo)
    {
        return Registro::whereBetween('fecha', [$destajo->fecha_inicio, $destajo->fecha_fin]);
    }

    private function confirmarCerrado(Destajo $destajo): bool
    {
        if ($this->confirm("El destajo {$destajo->anio}/{$destajo->semana} está CERRADO y su liquidación es definitiva. ¿Borrarlo de todos modos?", false)) {
            return true;
        }

        $this->warn('Cancelado.');

        return false;
    }
}
