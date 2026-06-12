<?php

namespace App\Models\Cotiz;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Zona/sección de obra para el Análisis de Montaje (Nave Principal, Mezzanine, …).
 *
 * @use HasFactory<\Database\Factories\Cotiz\SeccionMontajeFactory>
 */
class SeccionMontaje extends Model
{
    use HasFactory;

    protected $table = 'cotiz_secciones_montaje';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'obra_id',
        'nombre',
        'area_m2',
        'orden',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'area_m2' => 'decimal:4',
            'orden' => 'integer',
        ];
    }

    public function obra(): BelongsTo
    {
        return $this->belongsTo(Obra::class, 'obra_id');
    }

    public function personal(): HasMany
    {
        return $this->hasMany(SeccionPersonal::class, 'seccion_id');
    }

    public function rendimientos(): HasMany
    {
        return $this->hasMany(SeccionFaseRendimiento::class, 'seccion_id');
    }
}
