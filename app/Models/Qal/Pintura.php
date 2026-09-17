<?php

namespace App\Models\Qal;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Los espesores de pintura de una inspección de 3ª (SSPC-PA2), con el promedio
 * y la aceptación ya calculados.
 */
class Pintura extends Model
{
    protected $table = 'qal_pintura';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'inspeccion_id',
        'espesor_requerido_mils',
        'metodo',
        'area_m2',
        'mediciones_visibles',
        'promedio_mils',
        'cumple',
        'mediciones_bajas',
        'revision',
        'accion',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'espesor_requerido_mils' => 'decimal:2',
            'area_m2' => 'decimal:2',
            'mediciones_visibles' => 'integer',
            'promedio_mils' => 'decimal:2',
            'cumple' => 'boolean',
            'mediciones_bajas' => 'array',
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
     * @return HasMany<PinturaLectura, $this>
     */
    public function lecturas(): HasMany
    {
        return $this->hasMany(PinturaLectura::class, 'pintura_id');
    }
}
