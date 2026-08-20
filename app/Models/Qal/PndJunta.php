<?php

namespace App\Models\Qal;

use App\Enums\Qal\ResultadoPnd;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Un renglón de la rejilla del informe: **un punto examinado, no una junta**.
 *
 * `J-18-1-2` es el segundo punto de examen de la junta `18-1`. El denominador
 * del porcentaje de rechazo es el spot; contar juntas subestima el volumen
 * ensayado.
 *
 * `marca` conserva el texto del laboratorio aunque `qal_pieza_id` quede nulo:
 * el informe llega antes de que la pieza esté dada de alta, y el texto sigue
 * siendo lo que dice el documento firmado.
 *
 * @use HasFactory<\Database\Factories\Qal\PndJuntaFactory>
 */
class PndJunta extends Model
{
    use HasFactory;

    protected $table = 'qal_pnd_juntas';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'qal_pnd_reporte_id',
        'qal_pieza_id',
        'marca',
        'junta',
        'modulo',
        'spot',
        'resultado',
        'discontinuidad',
        'longitud_discontinuidad',
        'espesor',
        'soldador_id',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'resultado' => ResultadoPnd::class,
            'spot' => 'integer',
            'longitud_discontinuidad' => 'decimal:2',
            'espesor' => 'decimal:2',
        ];
    }

    /**
     * @return BelongsTo<PndReporte, $this>
     */
    public function reporte(): BelongsTo
    {
        return $this->belongsTo(PndReporte::class, 'qal_pnd_reporte_id');
    }

    /**
     * La pieza, cuando la marca del laboratorio ya existe dada de alta.
     *
     * @return BelongsTo<Pieza, $this>
     */
    public function pieza(): BelongsTo
    {
        return $this->belongsTo(Pieza::class, 'qal_pieza_id');
    }

    /**
     * @return BelongsTo<Soldador, $this>
     */
    public function soldador(): BelongsTo
    {
        return $this->belongsTo(Soldador::class, 'soldador_id');
    }

    /**
     * Parte la referencia del laboratorio en junta y spot.
     *
     * `J-18-1-2` → junta `18-1`, spot `2`. La `J` inicial es decorativa y el
     * último segmento es el punto de examen.
     *
     * Con menos de tres segmentos **no se separa nada**: `J-18-1` se guarda
     * como junta `18-1`, spot 1. La junta se nombra `módulo-junta`, así que
     * partir dos segmentos convertiría el número de junta en un spot y dejaría
     * cada junta contada como una junta distinta. Es deducción, no imposición:
     * el capturista puede corregir el spot en la rejilla, como con el tipo de
     * pieza deducido de la marca.
     *
     * @return array{junta: string, spot: int}
     */
    public static function descomponerReferencia(string $referencia): array
    {
        $limpia = trim($referencia);
        $limpia = (string) preg_replace('/^[Jj]\s*[-_\s]\s*/', '', $limpia);

        $segmentos = preg_split('/[-_\s]+/', $limpia, -1, PREG_SPLIT_NO_EMPTY) ?: [];

        if (count($segmentos) >= 3 && ctype_digit((string) end($segmentos))) {
            $spot = (int) array_pop($segmentos);

            return [
                'junta' => implode('-', $segmentos),
                'spot' => max($spot, 1),
            ];
        }

        return [
            'junta' => implode('-', $segmentos) ?: $limpia,
            'spot' => 1,
        ];
    }
}
