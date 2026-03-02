<?php

namespace App\Models\Infra;

use App\Models\Usuario;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Tanque extends Model
{
    /** @use HasFactory<\Database\Factories\Infra\TanqueFactory> */
    use HasFactory;

    protected $table = 'infra_tanques';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'usuario_id',
        'pa_sistema_oxigeno',
        'presion_sistema_oxigeno',
        'presion_tanque_oxigeno',
        'lt_tanque_oxigeno',
        'kg_tanque_oxigeno',
        'pa_sistema_argon',
        'presion_sistema_argon',
        'presion_tanque_argon',
        'lt_tanque_argon',
        'kg_tanque_argon',
        'pa_sistema_co2',
        'presion_sistema_co2',
        'presion_tanque_co2',
        'lt_tanque_co2',
        'kg_tanque_co2',
        'pa_sistema_lp',
        'presion_sistema_lp',
        'nivel_tanque_lp',
        'numero_tanque_lp',
        'lt_tanque_lp',
        'kg_tanque_lp',
        'observaciones',
        'infra_turno_id',
    ];

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(Usuario::class, 'usuario_id');
    }

    public function turno(): BelongsTo
    {
        return $this->belongsTo(Turno::class, 'infra_turno_id');
    }

    /**
     * Consumo mensual acumulado de kg por gas.
     * Solo cuenta caídas de kg (ignora refills donde kg sube).
     *
     * @return array<int, array{mes: string, oxigeno: float, argon: float, co2: float, lp: float, oxigeno_acum: float, argon_acum: float, co2_acum: float, lp_acum: float}>
     */
    public static function consumoMensualAcumulado(int $year): array
    {
        $records = static::query()
            ->select(
                'kg_tanque_oxigeno',
                'kg_tanque_argon',
                'kg_tanque_co2',
                'kg_tanque_lp',
                'created_at',
            )
            ->whereYear('created_at', $year)
            ->orderBy('created_at')
            ->get();

        // Calcular deltas diarios (solo caídas) agrupados por mes
        $monthlyConsumption = [];
        for ($m = 1; $m <= 12; $m++) {
            $key = str_pad((string) $m, 2, '0', STR_PAD_LEFT);
            $monthlyConsumption[$key] = ['oxigeno' => 0, 'argon' => 0, 'co2' => 0, 'lp' => 0];
        }

        $gases = ['oxigeno', 'argon', 'co2', 'lp'];
        $prev = [];

        foreach ($records as $record) {
            $mes = $record->created_at->format('m');

            foreach ($gases as $gas) {
                $field = "kg_tanque_{$gas}";
                $current = (float) $record->{$field};

                if (isset($prev[$gas]) && $current < $prev[$gas]) {
                    $monthlyConsumption[$mes][$gas] += round($prev[$gas] - $current, 2);
                }

                $prev[$gas] = $current;
            }
        }

        // Acumulado progresivo
        $result = [];
        $acum = ['oxigeno' => 0, 'argon' => 0, 'co2' => 0, 'lp' => 0];

        for ($m = 1; $m <= 12; $m++) {
            $key = str_pad((string) $m, 2, '0', STR_PAD_LEFT);
            $row = $monthlyConsumption[$key];

            foreach ($gases as $gas) {
                $acum[$gas] += $row[$gas];
            }

            $result[] = [
                'mes' => $key,
                'oxigeno' => round($row['oxigeno'], 2),
                'argon' => round($row['argon'], 2),
                'co2' => round($row['co2'], 2),
                'lp' => round($row['lp'], 2),
                'oxigeno_acum' => round($acum['oxigeno'], 2),
                'argon_acum' => round($acum['argon'], 2),
                'co2_acum' => round($acum['co2'], 2),
                'lp_acum' => round($acum['lp'], 2),
            ];
        }

        return $result;
    }
}
