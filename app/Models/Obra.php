<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\MorphOne;

class Obra extends Model
{
    use HasFactory;

    protected $table = 'obras';

    /**
     * La obra la usan varios módulos, así que el hook sale de inmediato salvo
     * que cambie algo que altere el valor a ejecutar del ICSOE: el tipo de
     * contrato (define si manda el comparativo o las partidas) o el proyecto al
     * que pertenece.
     */
    protected static function booted(): void
    {
        static::saved(function (self $obra) {
            if (! $obra->wasChanged(['tipo_contrato', 'proyecto_id'])) {
                return;
            }

            $icsoe = app(\App\Services\Cob\IcsoeService::class);
            $icsoe->programarRecalculo($obra->proyecto_id, 'Cambió el contrato de una obra del proyecto');

            $anterior = $obra->getOriginal('proyecto_id');

            if ($anterior !== null && $anterior !== $obra->proyecto_id) {
                $icsoe->programarRecalculo((int) $anterior, 'Una obra salió del proyecto');
            }
        });
    }

    /**
     * @var list<string>
     */
    protected $fillable = [
        'proyecto_id',
        'tipo',
        'no',
        'descripcion',
        'fecha_inicio',
        'fecha_fin',
        'presupuesto_total',
        'ingreso_real',
        'estatus',
        'cliente_id',
        'tipo_contrato',
        'monto',
        'monto_iva',
        'anticipo',
        'garantia',
        'peso',
        'porcentaje_fabricacion',
        'porcentaje_montaje',
        'porcentaje_otros',
        'descripcion_otros',
        'activa',
        'es_planta',
        'porcentaje_obra',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'fecha_inicio' => 'date',
            'fecha_fin' => 'date',
            'presupuesto_total' => 'decimal:2',
            'ingreso_real' => 'decimal:4',
            'monto' => 'decimal:2',
            'monto_iva' => 'decimal:2',
            'anticipo' => 'decimal:2',
            'garantia' => 'decimal:2',
            'peso' => 'decimal:2',
            'porcentaje_fabricacion' => 'decimal:2',
            'porcentaje_montaje' => 'decimal:2',
            'porcentaje_otros' => 'decimal:2',
            'activa' => 'boolean',
            'es_planta' => 'boolean',
            'porcentaje_obra' => 'decimal:2',
        ];
    }

    /**
     * Excluye el proyecto de planta. Usar en todos los listados y
     * selectores de obras fuera del módulo de costos.
     *
     * @param  \Illuminate\Database\Eloquent\Builder<self>  $query
     * @return \Illuminate\Database\Eloquent\Builder<self>
     */
    public function scopeSinPlanta($query)
    {
        return $query->where('es_planta', false);
    }

    public function conceptos(): HasMany
    {
        return $this->hasMany(Concepto::class, 'obra_id');
    }

    public function catalogos(): HasMany
    {
        return $this->hasMany(Prod\Catalogo::class, 'obra_id');
    }

    /** Catálogo de piezas en uso; las versiones anteriores quedan de historia. */
    public function catalogoVigente(): HasOne
    {
        return $this->hasOne(Prod\Catalogo::class, 'obra_id')->where('vigente', true);
    }

    public function gruposPrecios(): HasMany
    {
        return $this->hasMany(Prod\GrupoPrecio::class, 'obra_id');
    }

    /**
     * Procesos que se pagan como destajo en esta obra. Una obra que sólo suelda
     * no ofrece pintura en la captura ni le pide tarifa al grupo de precios.
     */
    public function procesos(): BelongsToMany
    {
        return $this->belongsToMany(Prod\Proceso::class, 'prod_obra_procesos', 'obra_id', 'proceso_id')
            ->withTimestamps()
            ->orderBy('prod_procesos.orden');
    }

    /**
     * Renglones de presupuesto (centro de costo) ligados por obra_id.
     *
     * @deprecated Se conserva durante la transición (reporte PDF). El
     * presupuesto ahora se modela con Costos\Presupuesto vía presupuesto().
     */
    public function obraRubros(): HasMany
    {
        return $this->hasMany(Costos\ObraRubro::class, 'obra_id');
    }

    /**
     * Presupuesto de costos de esta obra (centro de costo), si existe.
     */
    public function presupuesto(): MorphOne
    {
        return $this->morphOne(Costos\Presupuesto::class, 'presupuestable');
    }

    // Proyecto / jerarquía

    public function proyecto(): BelongsTo
    {
        return $this->belongsTo(Proyecto::class, 'proyecto_id');
    }

    // Cobranza relations

    public function cliente(): BelongsTo
    {
        return $this->belongsTo(Cliente::class, 'cliente_id');
    }

    public function partidas(): HasMany
    {
        return $this->hasMany(Cob\Partida::class, 'obra_id');
    }

    /** Etapas PMO planificadas (suministro / montaje) con sus fechas plan. */
    public function etapasPmo(): HasMany
    {
        return $this->hasMany(Cob\ObraEtapa::class, 'obra_id');
    }

    public function estimaciones(): HasMany
    {
        return $this->hasMany(Cob\Estimacion::class, 'obra_id');
    }

    public function anticipos(): HasMany
    {
        return $this->hasMany(Cob\Anticipo::class, 'obra_id');
    }

    public function adendas(): HasMany
    {
        return $this->hasMany(Cob\Adenda::class, 'obra_id');
    }

    public function deducciones(): HasMany
    {
        return $this->hasMany(Cob\Deduccion::class, 'obra_id');
    }

    public function eventos(): HasMany
    {
        return $this->hasMany(Cob\Evento::class, 'obra_id');
    }

    public function disputas(): HasMany
    {
        return $this->hasMany(Cob\Disputa::class, 'obra_id');
    }

    public function penalizaciones(): HasMany
    {
        return $this->hasMany(Cob\Penalizacion::class, 'obra_id');
    }

    // Calidad relations

    public function etapas(): HasMany
    {
        return $this->hasMany(Cal\Etapa::class, 'obra_id');
    }
}
