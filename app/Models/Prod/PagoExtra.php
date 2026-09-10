<?php

namespace App\Models\Prod;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PagoExtra extends Model
{
    /** @use HasFactory<\Database\Factories\Prod\PagoExtraFactory> */
    use HasFactory;

    protected $table = 'prod_pagos_extra';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'descripcion',
        'tipo_id',
        'destajo_id',
        'grupo_trabajo_id',
        'precio',
        'dias',
        'personas',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'precio' => 'decimal:2',
            // Fraccionable: media jornada de horas extra es medio dia, y
            // redondearla a uno le regala al grupo el doble de lo que hizo.
            'dias' => 'float',
            'personas' => 'integer',
        ];
    }

    protected $appends = ['monto'];

    /**
     * Importe con signo: los tipos marcados como descuento restan.
     *
     * El precio siempre se captura en positivo; quien decide el signo es el
     * catalogo de tipos, no quien captura. Asi un mismo concepto no se puede
     * cobrar en una semana y descontar en otra por un teclazo.
     */
    public function getMontoAttribute(): float
    {
        $monto = round($this->precio * $this->dias * $this->personas, 2);

        return $this->esDescuento() ? -$monto : $monto;
    }

    public function esDescuento(): bool
    {
        return (bool) $this->tipo?->es_descuento;
    }

    public function tipo(): BelongsTo
    {
        return $this->belongsTo(TipoPagoExtra::class, 'tipo_id');
    }

    public function destajo(): BelongsTo
    {
        return $this->belongsTo(Destajo::class, 'destajo_id');
    }

    public function grupoTrabajo(): BelongsTo
    {
        return $this->belongsTo(GrupoTrabajo::class, 'grupo_trabajo_id');
    }
}
