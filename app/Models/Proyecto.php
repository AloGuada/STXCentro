<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\MorphOne;

/**
 * Paraguas comercial que agrupa una o más obras (centros de costo/ejecución) y
 * concentra el cronograma (Gantt) del cobro. Los datos contractuales/financieros
 * y las estimaciones viven en cada obra.
 */
class Proyecto extends Model
{
    use HasFactory;

    protected $table = 'proyectos';

    /**
     * Cerrar el proyecto cierra su ICSOE: el badge del sidebar solo filtra por
     * su propia tabla, así que un proyecto cerrado seguiría pidiendo
     * verificación para siempre.
     */
    protected static function booted(): void
    {
        static::saved(function (self $proyecto) {
            if (! $proyecto->wasChanged('estatus')) {
                return;
            }

            $icsoe = app(\App\Services\Cob\IcsoeService::class);

            $proyecto->estatus === 'cerrada'
                ? $icsoe->cerrarPorProyecto($proyecto->id)
                : $icsoe->reabrirPorProyecto($proyecto->id);
        });
    }

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

    /**
     * Presupuesto de costos ligado a este proyecto, si existe.
     */
    public function presupuesto(): MorphOne
    {
        return $this->morphOne(Costos\Presupuesto::class, 'presupuestable');
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

    /** Carpetas del expediente documental del proyecto. */
    public function documentoCarpetas(): HasMany
    {
        return $this->hasMany(\App\Models\Cob\DocumentoCarpeta::class, 'proyecto_id');
    }

    /** Archivos del expediente documental del proyecto. */
    public function documentoArchivos(): HasMany
    {
        return $this->hasMany(\App\Models\Cob\DocumentoArchivo::class, 'proyecto_id');
    }

    /** Estatus (pendiente/completado) de cada sección del expediente para este proyecto. */
    public function seccionEstatus(): HasMany
    {
        return $this->hasMany(\App\Models\Cob\DocumentoSeccionProyecto::class, 'proyecto_id');
    }

    /** Comparativos de ingeniería del proyecto (re-evaluación del presupuesto). */
    public function comparativos(): HasMany
    {
        return $this->hasMany(\App\Models\Cob\Comparativo::class, 'proyecto_id');
    }

    /** Seguimiento ICSOE ante el IMSS (uno por proyecto). */
    public function icsoe(): HasOne
    {
        return $this->hasOne(\App\Models\Cob\IcsoeSeguimiento::class, 'proyecto_id');
    }
}
