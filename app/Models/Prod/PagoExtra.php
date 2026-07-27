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
            'dias' => 'integer',
            'personas' => 'integer',
        ];
    }

    protected $appends = ['monto'];

    public function getMontoAttribute(): float
    {
        return round($this->precio * $this->dias * $this->personas, 2);
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
