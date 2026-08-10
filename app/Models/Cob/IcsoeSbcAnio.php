<?php

namespace App\Models\Cob;

use App\Services\Cob\SbcResolver;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Parámetros del IMSS de un año: SBC diario, costo del DOF por m² y prima de
 * riesgo de la empresa.
 */
class IcsoeSbcAnio extends Model
{
    use HasFactory;

    protected $table = 'cob_icsoe_sbc_anios';

    /** @var list<string> */
    protected $fillable = [
        'anio',
        'sbc',
        'costo_m2',
        'prima_riesgo',
        'notas',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'anio' => 'integer',
            'sbc' => 'decimal:2',
            'costo_m2' => 'decimal:2',
            'prima_riesgo' => 'decimal:5',
        ];
    }

    /**
     * Resolver con el catálogo completo, cargado de una sola consulta.
     */
    public static function resolver(): SbcResolver
    {
        return new SbcResolver(
            self::query()->orderBy('anio')->get(['anio', 'sbc', 'costo_m2', 'prima_riesgo'])->toArray()
        );
    }
}
