<?php

namespace App\Models\Infra;

use App\Models\Usuario;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\DB;

class Transformador extends Model
{
    /** @use HasFactory<\Database\Factories\Infra\TransformadorFactory> */
    use HasFactory;

    protected $table = 'infra_transformadores';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'usuario_id',
        'linea_A',
        'linea_A_max',
        'date_A',
        'linea_B',
        'linea_B_max',
        'date_B',
        'linea_C',
        'linea_C_max',
        'date_C',
        'voltaje_a',
        'voltaje_b',
        'voltaje_c',
        'total_1',
        'total_5',
        'lectura_5y5',
        'registro_a',
        'registro_b',
        'registro_c',
        'tarifa',
        'observaciones',
        'infra_turno_id',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'date_A' => 'datetime',
            'date_B' => 'datetime',
            'date_C' => 'datetime',
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
     * SUM de total_1, total_5, lectura_5y5 agrupado por año-mes.
     *
     * @return array<int, array{mes: string, total_1: float, total_5: float, lectura_5y5: float}>
     */
    public static function consumosMensuales(int $year): array
    {
        $monthExpr = DB::connection()->getDriverName() === 'sqlite'
            ? "strftime('%m', created_at)"
            : "to_char(created_at, 'MM')";

        $rows = static::query()
            ->select(
                DB::raw("{$monthExpr} as mes"),
                DB::raw('SUM(total_1) as total_1'),
                DB::raw('SUM(total_5) as total_5'),
                DB::raw('SUM(lectura_5y5) as lectura_5y5'),
            )
            ->whereYear('created_at', $year)
            ->groupBy(DB::raw($monthExpr))
            ->orderBy('mes')
            ->get();

        $meses = [];
        for ($m = 1; $m <= 12; $m++) {
            $key = str_pad((string) $m, 2, '0', STR_PAD_LEFT);
            $meses[$key] = ['mes' => $key, 'total_1' => 0, 'total_5' => 0, 'lectura_5y5' => 0];
        }

        foreach ($rows as $row) {
            $meses[$row->mes] = [
                'mes' => $row->mes,
                'total_1' => round((float) $row->total_1, 2),
                'total_5' => round((float) $row->total_5, 2),
                'lectura_5y5' => round((float) $row->lectura_5y5, 2),
            ];
        }

        return array_values($meses);
    }
}
