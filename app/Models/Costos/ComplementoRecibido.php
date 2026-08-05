<?php

namespace App\Models\Costos;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Un REP aplicado a una obligación de complemento.
 *
 * Existe porque un pago se puede complementar en partes: cada parcialidad trae
 * su propio CFDI y cubre una porción del monto. La suma de estos renglones es lo
 * que decide si la obligación queda cumplida.
 */
class ComplementoRecibido extends Model
{
    /** @use HasFactory<\Database\Factories\Costos\ComplementoRecibidoFactory> */
    use HasFactory;

    protected $table = 'costos_complementos_recibidos';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'complemento_pago_id',
        'uuid',
        'imp_pagado',
        'recibido_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'imp_pagado' => 'decimal:2',
            'recibido_at' => 'datetime',
        ];
    }

    public function complementoPago(): BelongsTo
    {
        return $this->belongsTo(ComplementoPago::class, 'complemento_pago_id');
    }
}
