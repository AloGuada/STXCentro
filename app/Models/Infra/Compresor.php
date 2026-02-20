<?php

namespace App\Models\Infra;

use App\Models\Usuario;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\DB;

class Compresor extends Model
{
    /** @use HasFactory<\Database\Factories\Infra\CompresorFactory> */
    use HasFactory;

    protected $table = 'infra_compresores';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'usuario_id',
        'compresor_1_status',
        'compresor_1_presion_aire',
        'compresor_1_tiempo_trabajo',
        'compresor_1_tiempo_marcha',
        'compresor_1_kwhr',
        'compresor_2_status',
        'compresor_2_presion_aire',
        'compresor_2_tiempo_trabajo',
        'compresor_2_tiempo_marcha',
        'compresor_2_kwhr',
        'compresor_3_status',
        'compresor_3_presion_aire',
        'compresor_3_tiempo_trabajo',
        'compresor_3_tiempo_marcha',
        'compresor_3_kwhr',
        'observaciones',
        'infra_turno_id',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'compresor_1_status' => 'boolean',
            'compresor_2_status' => 'boolean',
            'compresor_3_status' => 'boolean',
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
     * Delta MAX-MIN de tiempo_marcha por mes × 3 compresores.
     *
     * @return array<int, array{mes: string, c1: float, c2: float, c3: float}>
     */
    public static function horasLaboradasMensuales(int $year): array
    {
        $rows = static::query()
            ->select(
                DB::raw("strftime('%m', created_at) as mes"),
                DB::raw('MAX(compresor_1_tiempo_marcha) - MIN(compresor_1_tiempo_marcha) as c1'),
                DB::raw('MAX(compresor_2_tiempo_marcha) - MIN(compresor_2_tiempo_marcha) as c2'),
                DB::raw('MAX(compresor_3_tiempo_marcha) - MIN(compresor_3_tiempo_marcha) as c3'),
            )
            ->whereYear('created_at', $year)
            ->groupBy(DB::raw("strftime('%m', created_at)"))
            ->orderBy('mes')
            ->get();

        $meses = [];
        for ($m = 1; $m <= 12; $m++) {
            $key = str_pad((string) $m, 2, '0', STR_PAD_LEFT);
            $meses[$key] = ['mes' => $key, 'c1' => 0, 'c2' => 0, 'c3' => 0];
        }

        foreach ($rows as $row) {
            $meses[$row->mes] = [
                'mes' => $row->mes,
                'c1' => round((float) $row->c1, 2),
                'c2' => round((float) $row->c2, 2),
                'c3' => round((float) $row->c3, 2),
            ];
        }

        return array_values($meses);
    }
}
