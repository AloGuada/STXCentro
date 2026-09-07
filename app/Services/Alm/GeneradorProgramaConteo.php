<?php

namespace App\Services\Alm;

use App\Enums\Alm\ConteoEstatus;
use App\Enums\Alm\ConteoOrigen;
use App\Models\Alm\Almacen;
use App\Models\Alm\Conteo;
use App\Models\Alm\ConteoPrograma;
use App\Models\Alm\Existencia;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Reparte un almacén en hojas de conteo: tantos artículos por día, en los
 * días de la semana elegidos, durante tantos días.
 *
 * **Qué entra:** los artículos activos que tienen renglón de existencia en
 * ese almacén, incluidos los que están en cero. Es lo que físicamente puede
 * estar en esa bodega; ofrecer el catálogo entero sería programar 1,600 ceros.
 *
 * **En qué orden:** al azar, por decisión del usuario. Es la práctica de
 * auditoría: nadie sabe qué se va a contar mañana, así que no hay forma de
 * "preparar" el rack. La hoja impresa no sigue una ruta física, y eso se
 * acepta a cambio.
 *
 * **Cuando no cabe:** si el almacén tiene más artículos de los que caben en
 * los días elegidos, se programan los que caben y el resto queda contado en
 * `articulos_sin_programar`, a la vista, para abrir otro programa. Si sobran
 * días, sólo se generan las hojas necesarias: una hoja vacía no es un conteo.
 */
class GeneradorProgramaConteo
{
    /**
     * @param  list<int>  $diasSemana  1 = lunes … 7 = domingo (ISO)
     */
    public function generar(
        Almacen $almacen,
        CarbonInterface $fechaInicio,
        array $diasSemana,
        int $duracionDias,
        int $articulosPorDia,
        ?string $userId = null,
    ): ConteoPrograma {
        $fechas = self::fechasDeConteo($fechaInicio, $diasSemana, $duracionDias);

        if ($fechas->isEmpty()) {
            throw new InvalidArgumentException('Con esos días de la semana y esa duración no cae ningún día de conteo.');
        }

        $existencias = $this->existenciasDe($almacen);

        if ($existencias->isEmpty()) {
            throw new InvalidArgumentException("El almacén {$almacen->clave} no tiene artículos que contar.");
        }

        return DB::transaction(function () use ($almacen, $fechaInicio, $diasSemana, $duracionDias, $articulosPorDia, $userId, $fechas, $existencias): ConteoPrograma {
            $bloques = $existencias->shuffle()->chunk($articulosPorDia)->take($fechas->count())->values();
            $programados = $bloques->sum(fn (Collection $b): int => $b->count());

            $programa = ConteoPrograma::create([
                'almacen_id' => $almacen->id,
                'fecha_inicio' => $fechaInicio->toDateString(),
                'dias_semana' => array_values($diasSemana),
                'duracion_dias' => $duracionDias,
                'articulos_por_dia' => $articulosPorDia,
                'articulos_programados' => $programados,
                'articulos_sin_programar' => $existencias->count() - $programados,
                'creado_por' => $userId,
            ]);

            foreach ($bloques as $i => $bloque) {
                $conteo = Conteo::create([
                    'programa_id' => $programa->id,
                    'almacen_id' => $almacen->id,
                    'origen' => ConteoOrigen::Programado,
                    'fecha_programada' => $fechas[$i]->toDateString(),
                    'estatus' => ConteoEstatus::Pendiente,
                    'creado_por' => $userId,
                ]);

                $ahora = now();

                $conteo->detalles()->insert(
                    $bloque->values()->map(fn (Existencia $e, int $orden): array => [
                        'conteo_id' => $conteo->id,
                        'articulo_id' => $e->articulo_id,
                        'existencia_id' => $e->id,
                        'orden' => $orden + 1,
                        'created_at' => $ahora,
                        'updated_at' => $ahora,
                    ])->all()
                );
            }

            return $programa->load('conteos');
        });
    }

    /**
     * Los días en que se cuenta: cada fecha desde el inicio, durante la
     * duración, que caiga en uno de los días de la semana elegidos.
     *
     * @param  list<int>  $diasSemana
     * @return Collection<int, CarbonImmutable>
     */
    public static function fechasDeConteo(CarbonInterface $fechaInicio, array $diasSemana, int $duracionDias): Collection
    {
        $inicio = CarbonImmutable::instance($fechaInicio)->startOfDay();
        $dias = array_map('intval', $diasSemana);

        return collect(range(0, max($duracionDias, 1) - 1))
            ->map(fn (int $offset): CarbonImmutable => $inicio->addDays($offset))
            ->filter(fn (CarbonImmutable $fecha): bool => in_array($fecha->isoWeekday(), $dias, true))
            ->values();
    }

    /**
     * Cuántos artículos tiene el almacén para contar. Es lo que el modal
     * enseña para calcular cuántas hojas van a salir.
     */
    public static function articulosContables(Almacen $almacen): int
    {
        return (new self)->existenciasDe($almacen)->count();
    }

    /**
     * @return Collection<int, Existencia>
     */
    private function existenciasDe(Almacen $almacen): Collection
    {
        return Existencia::query()
            ->where('almacen_id', $almacen->id)
            ->whereNotNull('articulo_id')
            ->whereHas('articulo', fn ($q) => $q->where('activo', true))
            ->get(['id', 'articulo_id']);
    }
}
