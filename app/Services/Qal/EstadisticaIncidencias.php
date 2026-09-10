<?php

namespace App\Services\Qal;

use App\Enums\Qal\AreaIncidencia;
use App\Enums\Qal\DepartamentoIncidencia;
use App\Models\Qal\Obra;
use App\Models\Qal\ObraIncidencia;
use App\Models\Qal\ObraMontaje;
use Illuminate\Support\Collection;

/**
 * Las cuentas de incidencias en obra, en un solo lugar.
 *
 * Viven aquí y no en el controlador porque las consume también el reporte
 * semanal, y la regla que parte las incidencias en columnas no puede estar
 * escrita dos veces: el día que cambie, una de las dos copias se queda atrás y
 * el documento que sale de la empresa deja de cuadrar con la pantalla.
 *
 * Dos definiciones que vienen del formato en Excel y no hay que perder:
 *
 *  - El denominador es siempre **piezas montadas**, no piezas fabricadas ni
 *    entregadas. La pregunta es «de lo que ya está en obra, cuánto dio
 *    problema».
 *  - El numerador son **piezas con defecto**, no número de incidencias. Un solo
 *    hallazgo puede afectar a diez piezas.
 */
class EstadisticaIncidencias
{
    /**
     * Las dos hojas de montaje del reporte semanal, en acumulado del año al
     * corte de la semana pedida.
     *
     * Van acumuladas y no por semana porque así se publica el formato: el
     * porcentaje de una sola semana salta demasiado para significar algo.
     *
     * El corte de cada hoja es distinto y deliberado:
     *
     *  - **Montaje** toma todas las incidencias y las parte por *área*: lo que
     *    se escapó del taller contra lo que apareció en sitio. El taller de
     *    pintura cuenta como taller, porque también salió de la nave.
     *  - **Pintura** toma sólo las de los dos departamentos de pintura y las
     *    parte por *departamento*: taller contra obra. Es un corte del mismo
     *    universo, no un universo aparte, así que sus piezas también están
     *    contadas en la hoja de montaje.
     *
     * @return array{montaje: list<array<string, mixed>>, pintura: list<array<string, mixed>>, montadas_semana: int}
     */
    public function hojasDelReporteSemanal(int $anio, int $semana): array
    {
        $montadas = $this->montadasPorObra($anio, $semana);

        if ($montadas->isEmpty()) {
            return ['montaje' => [], 'pintura' => [], 'montadas_semana' => 0];
        }

        $porArea = $this->incidenciasPorObra($anio, $semana, 'area', [
            'a' => [AreaIncidencia::Taller->value, AreaIncidencia::TallerPintura->value],
            'b' => [AreaIncidencia::Montaje->value],
        ]);

        $porPintura = $this->incidenciasPorObra($anio, $semana, 'departamento', [
            'a' => [DepartamentoIncidencia::PinturaTaller->value],
            'b' => [DepartamentoIncidencia::PinturaObra->value],
        ]);

        $obras = Obra::query()
            ->conDatosDeLaObra()
            ->whereIn('qal_obras.id', $montadas->keys())
            ->orderBy('obras.no')
            ->get();

        return [
            'montaje' => $this->filas($obras, $montadas, $porArea),
            'pintura' => $this->filas($obras, $montadas, $porPintura),
            'montadas_semana' => (int) $montadas->sum('semana'),
        ];
    }

    /**
     * Une el denominador de cada obra con sus dos columnas de incidencias.
     *
     * @param  Collection<int, Obra>  $obras
     * @param  Collection<int, array{acumulado: int, semana: int}>  $montadas
     * @param  Collection<int, array{a: int, b: int, aSemana: int, bSemana: int}>  $incidencias
     * @return list<array<string, mixed>>
     */
    private function filas(Collection $obras, Collection $montadas, Collection $incidencias): array
    {
        return $obras
            ->map(function (Obra $obra) use ($montadas, $incidencias): array {
                $suyas = $incidencias->get($obra->id, ['a' => 0, 'b' => 0, 'aSemana' => 0, 'bSemana' => 0]);

                return [
                    'obra' => $obra->no,
                    'montadas' => (int) ($montadas->get($obra->id)['acumulado'] ?? 0),
                    // Sin piezas totales no hay avance que calcular. Llega en
                    // nulo para que la hoja escriba «falta» en vez de dar un
                    // porcentaje sobre un denominador supuesto.
                    'totales' => $obra->pz_total,
                    ...$suyas,
                ];
            })
            ->values()
            ->all();
    }

    /**
     * Piezas montadas por obra: acumulado del año al corte y las de la semana.
     *
     * @return Collection<int, array{acumulado: int, semana: int}>
     */
    private function montadasPorObra(int $anio, int $semana): Collection
    {
        return ObraMontaje::query()
            ->where('anio', $anio)
            ->where('semana', '<=', $semana)
            ->groupBy('qal_obra_id')
            ->selectRaw('qal_obra_id, sum(pz_montadas) as acumulado, sum(case when semana = ? then pz_montadas else 0 end) as de_la_semana', [$semana])
            ->get()
            ->mapWithKeys(fn ($fila): array => [
                (int) $fila->qal_obra_id => [
                    'acumulado' => (int) $fila->acumulado,
                    'semana' => (int) $fila->de_la_semana,
                ],
            ]);
    }

    /**
     * Piezas con defecto por obra, partidas en dos columnas según el campo que
     * se le pase —`area` o `departamento`— y los valores de cada lado.
     *
     * Se agrupa en la base y no en PHP porque el historial de una obra crece
     * semana a semana, y traerse todos los renglones para devolver cuatro
     * números sería cargar el año entero en memoria.
     *
     * @param  array{a: list<string>, b: list<string>}  $columnas
     * @return Collection<int, array{a: int, b: int, aSemana: int, bSemana: int}>
     */
    private function incidenciasPorObra(int $anio, int $semana, string $campo, array $columnas): Collection
    {
        $ladoA = $this->huecos($columnas['a']);
        $ladoB = $this->huecos($columnas['b']);

        return ObraIncidencia::query()
            ->where('anio', $anio)
            ->where('semana', '<=', $semana)
            ->whereIn($campo, [...$columnas['a'], ...$columnas['b']])
            ->groupBy('qal_obra_id')
            ->selectRaw(
                'qal_obra_id, '.
                "sum(case when {$campo} in ({$ladoA}) then pz_defecto else 0 end) as a, ".
                "sum(case when {$campo} in ({$ladoB}) then pz_defecto else 0 end) as b, ".
                "sum(case when {$campo} in ({$ladoA}) and semana = ? then pz_defecto else 0 end) as a_semana, ".
                "sum(case when {$campo} in ({$ladoB}) and semana = ? then pz_defecto else 0 end) as b_semana",
                [
                    ...$columnas['a'],
                    ...$columnas['b'],
                    ...$columnas['a'], $semana,
                    ...$columnas['b'], $semana,
                ],
            )
            ->get()
            ->mapWithKeys(fn ($fila): array => [
                (int) $fila->qal_obra_id => [
                    'a' => (int) $fila->a,
                    'b' => (int) $fila->b,
                    'aSemana' => (int) $fila->a_semana,
                    'bSemana' => (int) $fila->b_semana,
                ],
            ]);
    }

    /**
     * Los interrogantes de un `in (...)`. Los valores siguen viajando como
     * parámetros; aquí sólo se arma el hueco donde van.
     *
     * @param  list<string>  $valores
     */
    private function huecos(array $valores): string
    {
        return implode(', ', array_fill(0, count($valores), '?'));
    }
}
