<?php

namespace App\Models\Cob;

use App\Models\Obra;
use App\Models\Proyecto;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Estimacion extends Model
{
    use HasFactory;

    protected $table = 'cob_estimaciones';

    /** @var list<string> */
    protected $fillable = [
        'proyecto_id',
        'obra_id',
        'nivel',
        'numero_estimacion',
        'folio',
        'tipo',
        'fecha_emision',
        'inicio',
        'fin',
        'monto_estimado',
        'monto_total',
        'monto_pagado',
        'moneda',
        'estado',
        'fecha_ultimo_cambio_estado',
        'comentarios',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'fecha_emision' => 'date',
            'inicio' => 'date',
            'fin' => 'date',
            'monto_estimado' => 'decimal:2',
            'monto_total' => 'decimal:2',
            'monto_pagado' => 'decimal:2',
            'fecha_ultimo_cambio_estado' => 'datetime',
        ];
    }

    public function proyecto(): BelongsTo
    {
        return $this->belongsTo(Proyecto::class, 'proyecto_id');
    }

    public function obra(): BelongsTo
    {
        return $this->belongsTo(Obra::class, 'obra_id');
    }

    /** Partidas que cubre (solo nivel 'partida'). */
    public function partidas(): BelongsToMany
    {
        return $this->belongsToMany(Partida::class, 'cob_estimacion_partida', 'estimacion_id', 'partida_id')
            ->withTimestamps();
    }

    public function pagos(): HasMany
    {
        return $this->hasMany(EstimacionPago::class, 'estimacion_id');
    }

    public function historial(): HasMany
    {
        return $this->hasMany(EstimacionEstadoHistorial::class, 'estimacion_id');
    }

    public function retenciones(): HasMany
    {
        return $this->hasMany(Retencion::class, 'estimacion_id');
    }

    public function documentos(): HasMany
    {
        return $this->hasMany(DocumentoEstimacion::class, 'estimacion_id');
    }
}
