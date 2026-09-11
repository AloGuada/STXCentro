<?php

namespace App\Services\Qal;

use App\Enums\Qal\FaseTransformacion;
use App\Models\Qal\Programacion;
use App\Models\Qal\ProgramacionMarca;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

/**
 * El cruce entre lo que producción programó y lo que calidad vio.
 *
 * Es el `calculo.ts` de la maqueta, portado: la parte del módulo que no es dato
 * ni pantalla, sino criterio. Lo que se escribe es sólo lo que no se puede
 * deducir —qué se piensa hacer—; todo lo demás sale de las inspecciones.
 *
 * Las dos decisiones que resuelven lo difícil:
 *
 *  - El arrastre es una lista de piezas, no un número: una marca no puede
 *    contarse dos veces, y siempre se puede señalar cuál lleva tres semanas sin
 *    hacerse.
 *  - Las reparaciones no vuelven al plan: una pieza rechazada ya está
 *    fabricada. Reprogramarla la contaría dos veces, y repararla no cuesta lo
 *    mismo que armarla. Vive en su propio bloque, con su antigüedad.
 */
class AvanceProduccion
{
    public function __construct(
        private readonly HistorialDePiezas $historial,
        private readonly LectorDeProgramacion $lector,
    ) {}

    /** La semana ISO como la escribe la pantalla: `2026-S37`. */
    public static function claveSemana(CarbonInterface $fecha): string
    {
        return sprintf('%04d-S%02d', $fecha->isoWeekYear(), $fecha->isoWeek());
    }

    /**
     * @return array{0: int, 1: int} año y número de semana de una clave `2026-S37`
     */
    public static function partesDeSemana(string $clave): array
    {
        preg_match('/^(\d{4})-S(\d{2})$/', $clave, $partes);

        return [(int) ($partes[1] ?? 0), (int) ($partes[2] ?? 0)];
    }

    /** `2` o `3`, como lo manda la pantalla, a la transformación. */
    public static function fase(string $fase): FaseTransformacion
    {
        return $fase === '3' ? FaseTransformacion::Tercera : FaseTransformacion::Segunda;
    }

    public function semanaActual(): string
    {
        return self::claveSemana(now());
    }

    /**
     * Las que ofrece el selector: una adelante —programar es un acto hacia
     * adelante— y ocho atrás.
     *
     * @return list<string>
     */
    public function semanasDisponibles(): array
    {
        return array_map(fn (int $salto): string => self::claveSemana(now()->addWeeks($salto)), range(1, -8));
    }

    /**
     * Una obra, una semana, una transformación.
     *
     * @param  '2'|'3'  $fase
     * @return array<string, mixed>
     */
    public function deObra(int $obraId, string $fase, string $semana): array
    {
        $piezas = $this->historial->piezas([$obraId], $fase);
        $planes = $this->planes([$obraId], $fase);
        $lineas = $this->lineas($planes, $this->indexar($piezas), $this->historial->enProceso([$obraId], $fase), $obraId, $semana);
        $plan = $planes->first(fn (Programacion $programacion): bool => $programacion->clave() === $semana);

        return [
            'plan' => $plan ? [
                'marcas' => $plan->marcas
                    ->where('es_baja', false)
                    ->map(fn (ProgramacionMarca $marca): string => $marca->cantidad > 1 ? "{$marca->marca} x{$marca->cantidad}" : $marca->marca)
                    ->implode("\n"),
                'bajas' => $plan->marcas
                    ->where('es_baja', true)
                    ->map(fn (ProgramacionMarca $baja): string => $baja->motivo_baja ? "{$baja->marca}: {$baja->motivo_baja}" : $baja->marca)
                    ->implode("\n"),
                'notas' => $plan->notas,
            ] : null,
            'lineas' => $lineas,
            'total' => $this->totalizar($lineas),
            'tipos' => $this->porTipo($lineas),
            'reparaciones' => $this->deReparacion($piezas, $semana),
        ];
    }

    /**
     * Todas las obras a la vez, sin sumar sus planes: una obra adelantada
     * taparía a otra retrasada y el total parecería sano estando las dos mal.
     * Lo que sí se junta es la cola de reparación: una pieza parada frena al
     * taller sin importar de qué obra sea.
     *
     * @param  list<array{id: int, no: string|null, descripcion: string|null}>  $obras
     * @return array{resumenes: list<array<string, mixed>>, reparaciones: list<array<string, mixed>>}
     */
    public function comparativa(array $obras, string $semana): array
    {
        $ids = array_map(fn (array $obra): int => (int) $obra['id'], $obras);
        $porFase = [];

        foreach (['2', '3'] as $fase) {
            $piezas = $this->historial->piezas($ids, $fase);
            $porFase[$fase] = [
                'piezas' => $piezas,
                'porObra' => collect($piezas)->groupBy('obra_id'),
                'indice' => $this->indexar($piezas),
                'enProceso' => $this->historial->enProceso($ids, $fase),
                'planes' => $this->planes($ids, $fase),
            ];
        }

        $resumenes = array_map(function (array $obra) use ($porFase, $semana): array {
            $fases = [];

            foreach ($porFase as $fase => $datos) {
                $total = $this->totalizar($this->lineas($datos['planes'], $datos['indice'], $datos['enProceso'], (int) $obra['id'], $semana));
                // Lo de la obra entera, no sólo lo del plan: una pieza rechazada
                // puede venir de un plan de hace un mes y sigue siendo su carga.
                $suyas = $datos['porObra']->get($obra['id'], collect());

                $fases[$fase] = [
                    'programadas' => $total['programadas'],
                    'fabricadas' => $total['fabricadas'],
                    'pendientes' => $total['pendientes'],
                    'empezadas' => $total['empezadas'],
                    'arrastre' => $total['arrastre'],
                    'enReparacion' => $suyas->where('estatus', 'Rechazado')->count(),
                    'fabricadasSemana' => $suyas->where('semanaFabricada', $semana)->count(),
                    'cumplimiento' => $total['cumplimiento'],
                    'salieron' => $total['salieron'],
                ];
            }

            return [
                'obra_id' => (int) $obra['id'],
                'obra' => trim(($obra['no'] ?? '').' — '.($obra['descripcion'] ?? ''), ' —'),
                'fases' => $fases,
                // ¿Pasó algo esta semana? Programado, fabricado, arrastrado o en reparación.
                'viva' => collect($fases)->contains(fn (array $resumen): bool => $resumen['programadas'] > 0
                    || $resumen['fabricadasSemana'] > 0
                    || $resumen['enReparacion'] > 0
                    || $resumen['arrastre'] > 0),
            ];
        }, $obras);

        usort($resumenes, fn (array $a, array $b): int => strnatcasecmp($a['obra'], $b['obra']));

        return ['resumenes' => $resumenes, 'reparaciones' => $this->deReparacion($porFase['2']['piezas'], $semana)];
    }

    /**
     * @param  list<int>  $obras
     * @return Collection<int, Programacion>
     */
    private function planes(array $obras, string $fase): Collection
    {
        return Programacion::query()
            ->whereIn('obra_id', $obras)
            ->where('fase', self::fase($fase)->value)
            ->with(['marcas' => fn ($consulta) => $consulta->orderBy('id')])
            ->get();
    }

    /**
     * Las piezas por `marca|obra_id`, que es como viene el plan.
     *
     * @param  list<array<string, mixed>>  $piezas
     * @return array<string, list<array<string, mixed>>>
     */
    private function indexar(array $piezas): array
    {
        $indice = [];

        foreach ($piezas as $pieza) {
            $indice[$pieza['marca'].'|'.$pieza['obra_id']][] = $pieza;
        }

        return $indice;
    }

    /**
     * Las líneas de la semana: lo programado ahora más lo que se arrastra.
     *
     * El arrastre recorre los planes anteriores de esa obra y esa
     * transformación, y se queda con las marcas que siguen sin fabricarse y que
     * no vuelven a estar en el plan de esta semana. Las dadas de baja se caen:
     * declarar que una pieza ya no se va a hacer es la única forma de que deje
     * de arrastrarse para siempre.
     *
     * @param  Collection<int, Programacion>  $planes
     * @param  array<string, list<array<string, mixed>>>  $indice
     * @param  array<string, int>  $enProceso
     * @return list<array<string, mixed>>
     */
    private function lineas(Collection $planes, array $indice, array $enProceso, int $obraId, string $semana): array
    {
        $suyos = $planes->where('obra_id', $obraId);
        $plan = $suyos->first(fn (Programacion $programacion): bool => $programacion->clave() === $semana);
        $bajas = $suyos->flatMap(fn (Programacion $programacion) => $programacion->marcas->where('es_baja', true)->pluck('marca'))->flip();

        $deEstaSemana = $plan
            ? $plan->marcas->where('es_baja', false)->reject(fn (ProgramacionMarca $marca): bool => $bajas->has($marca->marca))
            : collect();
        $enPlan = $deEstaSemana->pluck('marca')->flip();

        $lineas = $deEstaSemana
            ->map(fn (ProgramacionMarca $marca): array => [
                ...$this->estadoDeLinea($indice, $enProceso, $marca->marca, $obraId, $marca->cantidad, $semana),
                'arrastrada' => false,
                'desde' => $semana,
            ])
            ->values()
            ->all();

        $yaArrastradas = [];
        $anteriores = $suyos
            ->filter(fn (Programacion $programacion): bool => $programacion->clave() < $semana)
            ->sortBy(fn (Programacion $programacion): string => $programacion->clave());

        foreach ($anteriores as $anterior) {
            foreach ($anterior->marcas->where('es_baja', false) as $marca) {
                if ($bajas->has($marca->marca) || $enPlan->has($marca->marca) || isset($yaArrastradas[$marca->marca])) {
                    continue;
                }

                $estado = $this->estadoDeLinea($indice, $enProceso, $marca->marca, $obraId, $marca->cantidad, $semana);

                if ($estado['pendientes'] > 0) {
                    $yaArrastradas[$marca->marca] = true;
                    $lineas[] = [...$estado, 'arrastrada' => true, 'desde' => $anterior->clave()];
                }
            }
        }

        // Lo atrasado primero: es de lo que se habla en la reunión.
        usort($lineas, fn (array $a, array $b): int => $a['arrastrada'] !== $b['arrastrada']
            ? ($a['arrastrada'] ? -1 : 1)
            : strnatcasecmp($a['marca'], $b['marca']));

        return $lineas;
    }

    /**
     * Cruza una marca del plan con sus piezas.
     *
     * Los rechazos se cuentan hasta esta semana, no sólo los de la semana en
     * curso: del plan importa qué ha pasado con sus piezas, no qué pasó en siete
     * días. Lo empezado nunca pasa de lo pendiente: una pieza ya fabricada no
     * sigue «en proceso» por mucho que su marca tenga otras a medias.
     *
     * @param  array<string, list<array<string, mixed>>>  $indice
     * @param  array<string, int>  $enProceso
     * @return array<string, mixed>
     */
    private function estadoDeLinea(array $indice, array $enProceso, string $marca, int $obraId, int $cantidad, string $semana): array
    {
        $clave = "{$marca}|{$obraId}";
        $piezas = collect($indice[$clave] ?? []);
        $fabricadas = $piezas->filter(fn (array $pieza): bool => $pieza['semanaFabricada'] <= $semana)->count();
        $liberadas = $piezas->filter(fn (array $pieza): bool => $pieza['semanaLiberada'] !== '' && $pieza['semanaLiberada'] <= $semana)->count();
        $pendientes = max(0, $cantidad - $fabricadas);
        $empezadas = min($pendientes, $enProceso[$clave] ?? 0);
        $primera = $piezas->first();

        return [
            'marca' => $marca,
            'tipo' => $this->lector->tipoDeMarca($marca),
            'cantidad' => $cantidad,
            'fabricadas' => $fabricadas,
            'fabricadasSemana' => $piezas->where('semanaFabricada', $semana)->count(),
            'liberadas' => $liberadas,
            'liberadasSemana' => $piezas->where('semanaLiberada', $semana)->count(),
            'rechazadas' => $piezas->filter(fn (array $pieza): bool => collect($pieza['semanasRechazada'])->contains(fn (string $rechazo): bool => $rechazo <= $semana))->count(),
            'rechazadasSemana' => $piezas->filter(fn (array $pieza): bool => in_array($semana, $pieza['semanasRechazada'], true))->count(),
            'enReparacion' => $piezas->where('estatus', 'Rechazado')->count(),
            'pendientes' => $pendientes,
            'empezadas' => $empezadas,
            'sinEmpezar' => max(0, $pendientes - $empezadas),
            'primera' => $primera ? [
                'semanaFabricada' => $primera['semanaFabricada'],
                'semanaLiberada' => $primera['semanaLiberada'],
                'inspecciones' => $primera['inspecciones'],
            ] : null,
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $lineas
     * @return array<string, int|null>
     */
    private function totalizar(array $lineas): array
    {
        $suma = fn (string $campo): int => (int) array_sum(array_column($lineas, $campo));
        $programadas = $suma('cantidad');
        $fabricadas = $suma('fabricadas');
        $liberadas = $suma('liberadas');
        $rechazadas = $suma('rechazadas');

        return [
            'programadas' => $programadas,
            'nuevas' => (int) array_sum(array_column(array_filter($lineas, fn (array $linea): bool => ! $linea['arrastrada']), 'cantidad')),
            'arrastre' => (int) array_sum(array_column(array_filter($lineas, fn (array $linea): bool => $linea['arrastrada']), 'pendientes')),
            'fabricadas' => $fabricadas,
            'fabricadasSemana' => $suma('fabricadasSemana'),
            'pendientes' => $suma('pendientes'),
            'liberadas' => $liberadas,
            'liberadasSemana' => $suma('liberadasSemana'),
            'rechazadas' => $rechazadas,
            'rechazadasSemana' => $suma('rechazadasSemana'),
            'enReparacion' => $suma('enReparacion'),
            'empezadas' => $suma('empezadas'),
            'sinEmpezar' => $suma('sinEmpezar'),
            // Se fabricó lo que se dijo: mide a producción.
            'cumplimiento' => $this->porcentaje($fabricadas, $programadas),
            'tasaRechazo' => $this->porcentaje($rechazadas, $fabricadas),
            'tasaLiberacion' => $this->porcentaje($liberadas, $fabricadas),
            // De lo planeado, cuánto terminó saliendo: el número de la reunión.
            'salieron' => $this->porcentaje($liberadas, $programadas),
        ];
    }

    /**
     * El corte por tipo de pieza, que es como está escrita la pizarra del taller.
     *
     * @param  list<array<string, mixed>>  $lineas
     * @return list<array<string, int|string>>
     */
    private function porTipo(array $lineas): array
    {
        $grupos = [];

        foreach ($lineas as $linea) {
            $tipo = $linea['tipo'] ?: '—';
            $grupos[$tipo] ??= ['tipo' => $tipo, 'programadas' => 0, 'fabricadas' => 0, 'pendientes' => 0, 'liberadas' => 0, 'rechazadas' => 0, 'empezadas' => 0];
            $grupos[$tipo]['programadas'] += $linea['cantidad'];
            $grupos[$tipo]['fabricadas'] += $linea['fabricadas'];
            $grupos[$tipo]['pendientes'] += $linea['pendientes'];
            $grupos[$tipo]['liberadas'] += $linea['liberadas'];
            $grupos[$tipo]['rechazadas'] += $linea['rechazadas'];
            $grupos[$tipo]['empezadas'] += $linea['empezadas'];
        }

        ksort($grupos, SORT_NATURAL | SORT_FLAG_CASE);

        return array_values($grupos);
    }

    /**
     * Lo que cuenta para la cola de reparación: las que siguen rechazadas, las
     * que se rechazaron esta semana y las que salieron esta semana tras
     * retrabajo. El resto de las piezas no hace falta mandarlo a la pantalla.
     *
     * @param  list<array<string, mixed>>  $piezas
     * @return list<array<string, mixed>>
     */
    private function deReparacion(array $piezas, string $semana): array
    {
        return array_values(array_filter($piezas, fn (array $pieza): bool => $pieza['estatus'] === 'Rechazado'
            || in_array($semana, $pieza['semanasRechazada'], true)
            || ($pieza['semanaLiberada'] === $semana && ($pieza['inspeccionLiberada'] ?? 1) > 1)));
    }

    /** Porcentaje entero, o nulo cuando el denominador es cero. */
    private function porcentaje(int $parte, int $total): ?int
    {
        return $total > 0 ? (int) round($parte * 100 / $total) : null;
    }
}
