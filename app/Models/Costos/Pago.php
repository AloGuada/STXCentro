<?php

namespace App\Models\Costos;

use App\Enums\Costos\PagoEstatus;
use App\Models\Concerns\HasMonthlyFolio;
use App\Models\Concerns\HasStateMachine;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphOne;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class Pago extends Model
{
    /** @use HasFactory<\Database\Factories\Costos\PagoFactory> */
    use HasFactory, HasMonthlyFolio, HasStateMachine;

    protected $table = 'costos_pagos';

    protected static string $folioPrefix = 'PG';

    protected static string $stateEnum = PagoEstatus::class;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'folio',
        'pagable_type',
        'pagable_id',
        'monto_pago',
        'moneda',
        'tipo_cambio',
        'tipo_pago',
        'fecha_pago_programada',
        'fecha_pago_maxima',
        'fecha_pago_realizada',
        'referencia_pago',
        'estatus',
        'notas',
        'pago_padre_id',
        'numero_parcialidad',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'monto_pago' => 'decimal:2',
            'tipo_cambio' => 'decimal:4',
            'fecha_pago_programada' => 'date',
            'fecha_pago_maxima' => 'date',
            'fecha_pago_realizada' => 'date',
        ];
    }

    public function media(): MorphOne
    {
        return $this->morphOne(\App\Models\Media::class, 'mediable');
    }

    public function pagable(): MorphTo
    {
        return $this->morphTo();
    }

    public function pagoPadre(): BelongsTo
    {
        return $this->belongsTo(self::class, 'pago_padre_id');
    }

    public function pagosParciales(): HasMany
    {
        return $this->hasMany(self::class, 'pago_padre_id')->orderBy('numero_parcialidad');
    }

    public function esHijo(): bool
    {
        return $this->pago_padre_id !== null;
    }

    public function tieneParcialidades(): bool
    {
        return $this->pagosParciales()->exists();
    }
}
