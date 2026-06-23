<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * Paraguas comercial que agrupa una o más obras (centros de costo/ejecución) y
 * concentra el cronograma (Gantt) del cobro. Los datos contractuales/financieros
 * y las estimaciones viven en cada obra.
 */
class Proyecto extends Model
{
    use HasFactory;

    protected $table = 'proyectos';

    /** @var list<string> */
    protected $fillable = [
        'no',
        'descripcion',
        'cliente_id',
        'fecha_inicio_plan',
        'estatus',
        'activa',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'fecha_inicio_plan' => 'date',
            'activa' => 'boolean',
        ];
    }

    public function cliente(): BelongsTo
    {
        return $this->belongsTo(Cliente::class, 'cliente_id');
    }

    /** Todas las obras del proyecto (base + sub-obras/adicionales). */
    public function obras(): HasMany
    {
        return $this->hasMany(Obra::class, 'proyecto_id');
    }

    public function obrasBase(): HasMany
    {
        return $this->hasMany(Obra::class, 'proyecto_id')->where('tipo', 'base');
    }

    /** La obra base (centro de costo principal) del proyecto: la más antigua. */
    public function obraBase(): HasOne
    {
        return $this->hasOne(Obra::class, 'proyecto_id')->where('tipo', 'base')->oldest('id');
    }

    public function subObras(): HasMany
    {
        return $this->hasMany(Obra::class, 'proyecto_id')->where('tipo', 'adicional');
    }

    /** Todas las estimaciones del proyecto (cualquier nivel: global, obra o partida). */
    public function estimaciones(): HasMany
    {
        return $this->hasMany(\App\Models\Cob\Estimacion::class, 'proyecto_id');
    }

    /** Cronograma planeado de cobro (periodos por estimación planeada). */
    public function planCobro(): HasMany
    {
        return $this->hasMany(\App\Models\Cob\PlanCobro::class, 'proyecto_id')->orderBy('orden');
    }
}
