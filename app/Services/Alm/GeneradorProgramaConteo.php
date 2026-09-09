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
 * días de la semana elegidos, hasta cubrir todo el almacén.
 *
 * **Qué se decide:** cuántos artículos por día y qué días se cuenta. Nada
 * más. Cuánto va a tardar el inventario **lo calcula el sistema**: las hojas
 * que hagan falta, repartidas sobre los días elegidos, y la fecha en que cae
 * la última es la fecha en que se termina. No hay «artículos sin programar»:
 * el programa siempre cubre el almacén completo.
 *
 * **Qué entra:** los artículos activos que tienen renglón de existencia en
 * ese almacén, incluidos los que están en cero. Es lo que físicamente puede
 * estar en esa bodega; ofrecer el catálogo entero sería programar 1,600 ceros.
 * El universo se congela al generar: un artículo dado de alta a medio
 * programa no se toma en cuenta, entra al siguiente.
 *
 * **En qué orden:** al azar, por decisión del usuario. Es la práctica de
 * auditoría: nadie sabe qué se va a contar mañana, así que no hay forma de
 * "preparar" el rack. La hoja impresa no sigue una ruta física, y eso se
 * acepta a cambio.
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
        int $articulosPorDia,
        ?string $userId = null,
    ): ConteoPrograma {
        if ($articulosPorDia < 1) {
            throw new InvalidArgumentException('Hay que contar al menos un artículo por día.');
        }

        if (array_filter(array_map('intval', $diasSemana), fn (int $d): bool => $d >= 1 && $d <= 7) === []) {
            throw new InvalidArgumentException('Marca al menos un día de la semana para contar.');
        }

        $existencias = $this->existenciasDe($almacen);

        if ($existencias->isEmpty()) {
            throw new InvalidArgumentException("El almacén {$almacen->clave} no tiene artículos que contar.");
        }

        return DB::transaction(function () use ($almacen, $fechaInicio, $diasSemana, $articulosPorDia, $userId, $existencias): ConteoPrograma {
            $bloques = $existencias->shuffle()->chunk($articulosPorDia)->values();
            $fechas = self::fechasDeConteo($fechaInicio, $diasSemana, $bloques->count());

            $programa = ConteoPrograma::create([
                'almacen_id' => $almacen->id,
                'fecha_inicio' => $fechaInicio->toDateString(),
                'fecha_fin' => $fechas->last()->toDateString(),
                'dias_semana' => array_values($diasSemana),
                'articulos_por_dia' => $articulosPorDia,
                'articulos_programados' => $existencias->count(),
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
     * Qué saldría con esos números, sin escribir nada: cuántas hojas, en qué
     * fecha se termina y cuántos días naturales son. Es lo que el modal
     * enseña en vivo mientras el usuario mueve los artículos por día.
     *
     * @param  list<int>  $diasSemana
     * @return array{articulos: int, hojas: int, fecha_fin: string|null, dias_naturales: int}
     */
    public static function previsualizar(Almacen $almacen, CarbonInterface $fechaInicio, array $diasSemana, int $articulosPorDia): array
    {
        $articulos = self::articulosContables($almacen);
        $hojas = $articulosPorDia > 0 ? (int) ceil($articulos / $articulosPorDia) : 0;
        $fechas = self::fechasDeConteo($fechaInicio, $diasSemana, $hojas);
        $ultima = $fechas->last();

        return [
            'articulos' => $articulos,
            'hojas' => $hojas,
            'fecha_fin' => $ultima?->toDateString(),
            'dias_naturales' => $ultima === null
                ? 0
                : (int) CarbonImmutable::instance($fechaInicio)->startOfDay()->diffInDays($ultima) + 1,
        ];
    }

    /**
     * Las fechas en que caen N hojas: se recorre el calendario desde el inicio
     * y se toma cada día que caiga en uno de los días de la semana elegidos,
     * hasta juntar las que hacen falta.
     *
     * @param  list<int>  $diasSemana
     * @return Collection<int, CarbonImmutable>
     */
    public static function fechasDeConteo(CarbonInterface $fechaInicio, array $diasSemana, int $cuantas): Collection
    {
        $dias = array_values(array_filter(array_map('intval', $diasSemana), fn (int $d): bool => $d >= 1 && $d <= 7));

        if ($cuantas < 1 || $dias === []) {
            return collect();
        }

        $fechas = collect();
        $cursor = CarbonImmutable::instance($fechaInicio)->startOfDay();

        while ($fechas->count() < $cuantas) {
            if (in_array($cursor->isoWeekday(), $dias, true)) {
                $fechas->push($cursor);
            }

            $cursor = $cursor->addDay();
        }

        return $fechas;
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
