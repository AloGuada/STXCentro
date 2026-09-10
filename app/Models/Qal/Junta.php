<?php

namespace App\Models\Qal;

use App\Enums\Qal\ResultadoJunta;
use App\Enums\Qal\TipoJunta;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Una junta del mapeo de soldadura, revisada en una inspección de 2ª en
 * soldado.
 *
 * El resultado no se teclea: sale de sus puntos. Y un filete por debajo del
 * nominal marca solo el punto de perfil como defecto.
 *
 * @use HasFactory<\Database\Factories\Qal\JuntaFactory>
 */
class Junta extends Model
{
    use HasFactory;

    protected $table = 'qal_juntas';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'inspeccion_id',
        'cordon_id',
        'identificador',
        'tipo',
        'soldador_id',
        'espesor_requerido_mm',
        'espesor_medido_mm',
        'espesor_cumple',
        'es_empate',
        'intento',
        'resultado',
        'observaciones',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'tipo' => TipoJunta::class,
            'resultado' => ResultadoJunta::class,
            'espesor_requerido_mm' => 'decimal:2',
            'espesor_medido_mm' => 'decimal:2',
            'espesor_cumple' => 'boolean',
            'es_empate' => 'boolean',
            'intento' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<Inspeccion, $this>
     */
    public function inspeccion(): BelongsTo
    {
        return $this->belongsTo(Inspeccion::class, 'inspeccion_id');
    }

    /**
     * @return BelongsTo<Soldador, $this>
     */
    public function soldador(): BelongsTo
    {
        return $this->belongsTo(Soldador::class, 'soldador_id');
    }

    /**
     * @return HasMany<JuntaPunto, $this>
     */
    public function puntos(): HasMany
    {
        return $this->hasMany(JuntaPunto::class, 'junta_id');
    }
}
