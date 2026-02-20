<?php

namespace App\Models\Infra;

use App\Models\Usuario;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\DB;

class Bomba extends Model
{
    /** @use HasFactory<\Database\Factories\Infra\BombaFactory> */
    use HasFactory;

    protected $table = 'infra_bombas';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'usuario_id',
        'bomba_posos_1',
        'bomba_posos_2',
        'bomba_planta_1',
        'bomba_planta_2',
        'bomba_planta_3',
        'nivel_salmuera',
        'nivel_tinaco',
        'nivel_sisterna',
        'presion_tuberia',
        'nivel_hipoclorito',
        'nivel_anticongelante',
        'aceite_del_motor',
        'tanque_diesel',
        'voltaje_bateria',
        'bomba_jockey',
        'bomba_electrica',
        'bomba_diesel',
        'presion_tuberia_incendio',
        'observaciones',
        'infra_turno_id',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'bomba_posos_1' => 'boolean',
            'bomba_posos_2' => 'boolean',
            'bomba_planta_1' => 'boolean',
            'bomba_planta_2' => 'boolean',
            'bomba_planta_3' => 'boolean',
            'bomba_jockey' => 'boolean',
            'bomba_electrica' => 'boolean',
            'bomba_diesel' => 'boolean',
        ];
    }

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(Usuario::class, 'usuario_id');
    }

    public function turno(): BelongsTo
    {
        return $this->belongsTo(Turno::class, 'infra_turno_id');
    }

    /**
     * AVG, STDDEV (PHP), MAX, MIN de presiones por mes.
     * Primero promedia por día (para múltiples turnos), luego agrega por mes.
     *
     * @return array<int, array{mes: string, avg_presion: float, stddev: float, max_presion: float, min_presion: float}>
     */
    public static function estadisticasMensuales(int $year): array
    {
        // Paso 1: promediar presion_tuberia por día
        $dailyAvgs = static::query()
            ->select(
                DB::raw("strftime('%Y-%m-%d', created_at) as dia"),
                DB::raw("strftime('%m', created_at) as mes"),
                DB::raw('AVG(presion_tuberia) as avg_dia'),
            )
            ->whereYear('created_at', $year)
            ->whereNotNull('presion_tuberia')
            ->groupBy(DB::raw("strftime('%Y-%m-%d', created_at)"), DB::raw("strftime('%m', created_at)"))
            ->get();

        // Agrupar promedios diarios por mes
        /** @var array<string, list<float>> $monthValues */
        $monthValues = [];
        foreach ($dailyAvgs as $row) {
            $monthValues[$row->mes][] = (float) $row->avg_dia;
        }

        $meses = [];
        for ($m = 1; $m <= 12; $m++) {
            $key = str_pad((string) $m, 2, '0', STR_PAD_LEFT);
            $meses[$key] = ['mes' => $key, 'avg_presion' => 0, 'stddev' => 0, 'max_presion' => 0, 'min_presion' => 0];
        }

        // Paso 2: calcular stats mensuales desde promedios diarios
        foreach ($monthValues as $mes => $values) {
            $avg = array_sum($values) / count($values);
            $max = max($values);
            $min = min($values);

            $stddev = 0;
            if (count($values) > 1) {
                $sumSquares = array_sum(array_map(fn ($v) => ($v - $avg) ** 2, $values));
                $stddev = round(sqrt($sumSquares / count($values)), 2);
            }

            $meses[$mes] = [
                'mes' => $mes,
                'avg_presion' => round($avg, 2),
                'stddev' => $stddev,
                'max_presion' => round($max, 2),
                'min_presion' => round($min, 2),
            ];
        }

        return array_values($meses);
    }
}
